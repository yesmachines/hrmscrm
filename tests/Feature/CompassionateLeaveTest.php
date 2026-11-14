<?php

use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organisation;
use App\Models\SalesCrm\Employee;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = createHrmsLoginUser('hr');
    $this->withToken($this->user->createToken('compassionate-tests')->plainTextToken);
    $organisation = Organisation::query()->firstOrCreate(
        ['id' => 1],
        ['org_name' => 'Test Organisation', 'short_name' => 'TEST', 'status' => 1],
    );
    $this->employee = Employee::query()->create([
        'user_id' => $this->user->id,
        'emp_num' => 'COMP-TEST-'.$this->user->id,
        'organisation_id' => $organisation->id,
        'designation' => 'Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    $this->leaveType = LeaveType::query()->create([
        'leave_name' => 'Compassionate Leave',
        'code' => 'COMPASSIONATE',
        'status' => 1,
        'requires_approval' => true,
        'is_paid' => true,
    ]);
    $this->policy = LeavePolicy::query()->create([
        'leave_type_id' => $this->leaveType->id,
        'organisation_id' => $organisation->id,
        'allow_balance' => false,
        'probation_applicable' => true,
    ]);
    $this->data = [
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2027-04-05',
        'end_date' => '2027-04-09',
        'total_days' => 0.5,
        'status' => 'applied',
        'family_relationship' => 'spouse',
    ];
});

test('compassionate limits apply consistently to every relative and flow', function (string $flow, string $relationship, int $maximum, bool $exceeds) {
    $this->actingAs($this->user);
    $existing = null;
    if (str_ends_with($flow, 'update')) {
        $existing = LeaveRequest::query()->create([
            ...collect($this->data)->except('family_relationship')->all(),
            'total_days' => 1,
            'end_date' => '2027-04-05',
        ]);
        $existing->details()->create([
            'field_key' => 'family_relationship',
            'field_name' => 'Family Relationship',
            'field_value' => 'spouse',
        ]);
    }
    $data = [
        ...$this->data,
        'family_relationship' => $relationship,
        'end_date' => $maximum === 5
            ? ($exceeds ? '2027-04-12' : '2027-04-09')
            : ($exceeds ? '2027-04-08' : '2027-04-07'),
    ];
    $response = match ($flow) {
        'admin validate' => $this->postJson(route('leave-requests.validate'), $data),
        'admin create' => $this->post(route('leave-requests.store'), $data),
        'admin update' => $this->put(route('leave-requests.update', $existing), $data),
        'api validate' => $this->postJson('/api/v1/leaves/validate', $data),
        'api create' => $this->postJson('/api/v1/leaves', $data),
        'api update' => $this->patchJson('/api/v1/leaves/'.$existing->id, $data),
    };
    if ($flow === 'admin validate') {
        $response->assertOk()->assertJsonPath('valid', ! $exceeds);
        if ($exceeds) {
            $response->assertJsonStructure(['errors' => ['total_days']]);
        }
    } elseif (str_starts_with($flow, 'admin')) {
        if ($exceeds) {
            $response->assertSessionHasErrors(['total_days']);
        } else {
            $response->assertSessionHasNoErrors()->assertRedirect(route('leave-requests.index'));
        }
    } elseif ($exceeds) {
        $response->assertUnprocessable()->assertJsonValidationErrors(['total_days']);
    } elseif ($flow === 'api create') {
        $response->assertCreated();
    } else {
        $response->assertOk();
    }
    if (! $exceeds && ! str_ends_with($flow, 'validate')) {
        $leave = $existing ?? LeaveRequest::query()
            ->where('employee_id', $this->employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->firstOrFail();
        $this->assertDatabaseHas('leave_request_details', [
            'leave_request_id' => $leave->id,
            'field_name' => 'Family Relationship',
            'field_key' => 'family_relationship',
            'field_value' => $relationship,
        ]);
        expect((float) $leave->fresh()->total_days)->toBe((float) $maximum);
    }
})->with([
    'admin validate', 'admin create', 'admin update', 'api validate', 'api create', 'api update',
])->with([
    ['spouse', 5], ['child', 5], ['mother', 5], ['father', 5], ['sibling', 5],
    ['grandparent', 3], ['grandchild', 3], ['in_laws', 3],
])->with([false, true]);

test('compassionate relationships are required and allow only configured values', function (string $flow, mixed $relationship) {
    $this->actingAs($this->user);
    $data = [...$this->data, 'family_relationship' => $relationship];
    if ($flow === 'admin') {
        $this->post(route('leave-requests.store'), $data)
            ->assertSessionHasErrors(['family_relationship']);
        $this->postJson(route('leave-requests.validate'), $data)
            ->assertOk()->assertJsonPath('valid', false)
            ->assertJsonStructure(['errors' => ['family_relationship']]);
    } else {
        $this->postJson('/api/v1/leaves', $data)
            ->assertUnprocessable()->assertJsonValidationErrors(['family_relationship']);
        $this->postJson('/api/v1/leaves/validate', $data)
            ->assertUnprocessable()->assertJsonValidationErrors(['family_relationship']);
    }
    $this->assertDatabaseMissing('leave_requests', ['employee_id' => $this->employee->id]);
})->with(['admin', 'api'])->with([null, '', 'cousin']);

test('compassionate options and saved relationship are available to admin and app forms', function () {
    $this->actingAs($this->user);
    $this->post(route('leave-requests.store'), $this->data)->assertSessionHasNoErrors();
    $leave = LeaveRequest::query()->where('employee_id', $this->employee->id)->firstOrFail();
    $options = config('params.leave_type.compassionate_relationships');

    $this->withoutVite()->get(route('leave-requests.create'))
        ->assertInertia(fn (Assert $page) => $page->component('leave-requests/create')
            ->where('compassionateRelationships', $options));
    $this->get(route('leave-requests.edit', $leave))
        ->assertInertia(fn (Assert $page) => $page->component('leave-requests/edit')
            ->where('compassionateRelationships', $options)
            ->where('leaveRequest.family_relationship', 'spouse'));
    $this->getJson('/api/v1/leaves/meta')
        ->assertOk()->assertJsonFragment(['family_relationship_options' => $options]);
    $this->getJson('/api/v1/leaves/'.$leave->id)->assertOk()
        ->assertJsonFragment(['field_key' => 'family_relationship', 'field_value' => 'spouse']);
});

test('compassionate updates retain omitted relationship and reject clearing or lowering the limit', function (string $flow) {
    $this->actingAs($this->user);
    $this->post(route('leave-requests.store'), $this->data)->assertSessionHasNoErrors();
    $leave = LeaveRequest::query()->where('employee_id', $this->employee->id)->firstOrFail();
    foreach (['omit', 'clear', 'change'] as $action) {
        $data = collect($this->data)->except('family_relationship')->all();
        if ($action !== 'omit') {
            $data['family_relationship'] = $action === 'clear' ? null : 'grandparent';
        }
        $response = $flow === 'admin'
            ? $this->put(route('leave-requests.update', $leave), $data)
            : $this->patchJson('/api/v1/leaves/'.$leave->id, $data);
        if ($action === 'omit') {
            $flow === 'admin' ? $response->assertSessionHasNoErrors() : $response->assertOk();
        } else {
            $field = $action === 'clear' ? 'family_relationship' : 'total_days';
            $flow === 'admin' ? $response->assertSessionHasErrors([$field]) : $response->assertUnprocessable()->assertJsonValidationErrors([$field]);
        }
        expect($leave->details()->where('field_key', 'family_relationship')->value('field_value'))->toBe('spouse');
    }
})->with(['admin', 'api']);

test('compassionate group caps do not replace configured annual limits', function () {
    $this->policy->update(['annual_limit' => 4]);
    $this->actingAs($this->user)
        ->post(route('leave-requests.store'), $this->data)->assertSessionHasErrors(['total_days']);
    $this->postJson('/api/v1/leaves', $this->data)
        ->assertUnprocessable()->assertJsonValidationErrors(['total_days']);
});

test('compassionate caps use configured values and the existing leave day calculation', function () {
    $this->actingAs($this->user);
    $data = [
        ...$this->data,
        'family_relationship' => 'grandparent',
        'start_date' => '2027-04-09',
        'end_date' => '2027-04-13',
    ];
    $this->postJson('/api/v1/leaves/validate', $data)
        ->assertOk()->assertJsonPath('data.total_days', 3);

    config(['params.leave_type.compassionate_relationships.grandparent.max_days' => 2]);
    $this->postJson('/api/v1/leaves/validate', $data)
        ->assertUnprocessable()->assertJsonValidationErrors(['total_days']);

    $this->postJson('/api/v1/leaves/validate', [
        ...$data,
        'end_date' => '2027-04-09',
        'request_type' => 'firsthalf',
    ])->assertOk()->assertJsonPath('data.total_days', 0.5);
});

test('other leave types do not require a family relationship', function () {
    $this->leaveType->update(['leave_name' => 'General Leave', 'code' => 'GENERAL']);
    $data = collect($this->data)->except('family_relationship')->all();
    $this->actingAs($this->user)
        ->postJson(route('leave-requests.validate'), $data)
        ->assertOk()->assertJsonPath('valid', true);
    $this->postJson('/api/v1/leaves/validate', $data)->assertOk();
});
