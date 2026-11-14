<?php

use App\Models\EmployeeProfile;
use App\Models\Festival;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organisation;
use App\Models\SalesCrm\Country;
use App\Models\SalesCrm\Employee;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->hrUser = createHrmsLoginUser('hr');
    $this->withToken($this->hrUser->createToken('festival-tests')->plainTextToken);
    $this->country = Country::query()->firstOrCreate(
        ['code' => 'FT'],
        ['name' => 'Festival Test Country', 'status' => 1],
    );
    $organisation = Organisation::query()->firstOrCreate(
        ['id' => 1],
        ['org_name' => 'Festival Organisation', 'short_name' => 'FEST', 'status' => 1],
    );
    $this->employee = Employee::query()->create([
        'user_id' => $this->hrUser->id,
        'organisation_id' => $organisation->id,
        'emp_num' => fake()->unique()->bothify('FEST-#####'),
        'designation' => 'Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    $this->profile = EmployeeProfile::query()->create([
        'employee_id' => $this->employee->id,
        'nationality' => $this->country->name,
        'religion' => 'Muslim',
    ]);
    $this->leaveType = LeaveType::query()->create([
        'leave_name' => 'Festival Leave',
        'code' => 'FESTIVAL',
        'requires_approval' => true,
        'is_paid' => true,
        'status' => 1,
    ]);
    $this->policy = LeavePolicy::query()->create([
        'leave_type_id' => $this->leaveType->id,
        'organisation_id' => $organisation->id,
        'annual_limit' => 5,
        'allow_balance' => false,
        'probation_applicable' => true,
    ]);
    $this->festival = Festival::query()->create([
        'name' => 'Eligible Festival',
        'type' => 'festival',
        'religion' => 'Islam',
        'shortcode' => fake()->unique()->bothify('FST-#####'),
        'is_active' => true,
        'start_date' => '2027-04-01',
        'end_date' => '2027-04-02',
    ]);
    DB::table('festival_nationality')->insert([
        'festival_id' => $this->festival->id,
        'country_id' => $this->country->id,
    ]);
    $this->leaveData = [
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'festival_id' => $this->festival->id,
        'start_date' => '2027-04-01',
        'end_date' => '2027-04-02',
        'request_type' => 'fullday',
        'status' => 'applied',
    ];
});

test('festival rules are consistent across admin and API entry points', function (string $flow, string $condition) {
    $data = $this->leaveData;
    $existing = null;
    if (str_ends_with($flow, 'update')) {
        $existing = LeaveRequest::query()->create([
            ...collect($data)->except('festival_id')->all(),
            'total_days' => 2,
        ]);
        $existing->details()->create([
            'field_key' => 'festival_id',
            'field_name' => 'Festival ID',
            'field_value' => (string) $this->festival->id,
        ]);
    }

    $errorField = 'festival_id';
    switch ($condition) {
        case 'missing':
            $data['festival_id'] = null;
            break;
        case 'nonexistent':
            $data['festival_id'] = $this->festival->id + 100000;
            break;
        case 'inactive':
            $this->festival->update(['is_active' => false]);
            break;
        case 'holiday':
            $this->festival->update(['type' => 'holiday']);
            break;
        case 'religion':
            $this->profile->update(['religion' => 'Hindu']);
            break;
        case 'nationality':
            $this->profile->update(['nationality' => null, 'home_country' => null]);
            break;
        case 'missing_profile':
            $this->profile->delete();
            break;
        case 'before_range':
            $data['start_date'] = '2027-03-31';
            $errorField = 'start_date';
            break;
        case 'after_range':
            $data['end_date'] = '2027-04-05';
            $errorField = 'end_date';
            break;
        case 'single_date':
            $this->festival->update(['end_date' => null]);
            $errorField = 'end_date';
            break;
        case 'annual_limit':
        case 'cancelled_history':
        case 'previous_year':
            LeaveRequest::query()->create([
                'employee_id' => $this->employee->id,
                'leave_type_id' => $this->leaveType->id,
                'start_date' => $condition === 'previous_year' ? '2026-02-01' : '2027-02-01',
                'end_date' => $condition === 'previous_year' ? '2026-02-01' : '2027-02-01',
                'total_days' => 1,
                'status' => $condition === 'cancelled_history' ? 'cancelled' : 'approved',
            ]);
            break;
    }
    $valid = in_array($condition, ['eligible', 'cancelled_history', 'previous_year'], true);

    $this->actingAs($this->hrUser);
    $response = match ($flow) {
        'admin validate' => $this->postJson(route('leave-requests.validate'), [
            ...$data,
            'ignore_leave_request_id' => $existing?->id,
        ]),
        'admin create' => $this->from(route('leave-requests.create'))->post(route('leave-requests.store'), $data),
        'admin update' => $this->from(route('leave-requests.edit', $existing))->put(route('leave-requests.update', $existing), $data),
        'api validate' => $this->postJson('/api/v1/leaves/validate', $data),
        'api create' => $this->postJson('/api/v1/leaves', $data),
        'api update' => $this->patchJson('/api/v1/leaves/'.$existing->id, $data),
    };

    if ($flow === 'admin validate') {
        if ($condition === 'nonexistent') {
            $response->assertUnprocessable()->assertJsonValidationErrors([$errorField]);
        } else {
            $response->assertOk()->assertJsonPath('valid', $valid);
            if (! $valid) {
                $response->assertJsonStructure(['errors' => [$errorField]]);
            }
        }
    } elseif (str_starts_with($flow, 'admin')) {
        if ($valid) {
            $response->assertSessionHasNoErrors()->assertRedirect(route('leave-requests.index'));
        } else {
            $response->assertSessionHasErrors([$errorField]);
        }
    } elseif (! $valid) {
        $response->assertUnprocessable()->assertJsonValidationErrors([$errorField]);
    } elseif ($flow === 'api create') {
        $response->assertCreated();
    } else {
        $response->assertOk();
    }

    if ($valid && ! str_ends_with($flow, 'validate')) {
        $saved = $existing ?? LeaveRequest::query()
            ->where('employee_id', $this->employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertDatabaseHas('leave_request_details', [
            'leave_request_id' => $saved->id,
            'field_key' => 'festival_id',
            'field_value' => (string) $this->festival->id,
        ]);
        expect($saved->details()->where('field_key', 'festival_id')->count())->toBe(1);
    } elseif (! $valid && $existing !== null) {
        expect($existing->details()->where('field_key', 'festival_id')->value('field_value'))
            ->toBe((string) $this->festival->id);
    }
})->with([
    'admin validate', 'admin create', 'admin update',
    'api validate', 'api create', 'api update',
])->with([
    'eligible', 'missing', 'nonexistent', 'inactive', 'holiday',
    'religion', 'nationality', 'missing_profile', 'before_range', 'after_range',
    'single_date', 'annual_limit', 'cancelled_history', 'previous_year',
]);

test('festival dropdowns and API listings share eligibility normalization', function (string $countrySource) {
    $this->profile->update([
        'nationality' => match ($countrySource) {
            'name' => $this->country->name,
            'numeric' => (string) $this->country->id,
            default => null,
        },
        'home_country' => $countrySource === 'home_country' ? $this->country->id : null,
    ]);

    $this->actingAs($this->hrUser)
        ->withoutVite()
        ->get(route('leave-requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leave-requests/create')
            ->where('festivals', fn ($festivals) => collect($festivals)->contains(
                fn ($festival) => $festival['id'] === $this->festival->id
                    && in_array($this->employee->id, $festival['eligible_employee_ids'], true),
            ))
        );

    $this->getJson('/api/v1/leaves/festivals?year=2027')
        ->assertOk()
        ->assertJsonFragment(['name' => $this->festival->name]);
})->with(['name', 'numeric', 'home_country']);

test('festival update replaces the selection and preserves it when omitted', function (string $flow) {
    $leave = LeaveRequest::query()->create([
        ...collect($this->leaveData)->except('festival_id')->all(),
        'total_days' => 2,
    ]);
    $leave->details()->create([
        'field_key' => 'festival_id',
        'field_name' => 'Festival ID',
        'field_value' => (string) $this->festival->id,
    ]);
    $replacement = Festival::query()->create([
        'name' => 'Replacement Festival',
        'type' => 'festival',
        'shortcode' => fake()->unique()->bothify('REP-#####'),
        'is_active' => true,
        'start_date' => '2027-05-03',
        'end_date' => '2027-05-04',
    ]);
    $data = [
        ...$this->leaveData,
        'festival_id' => $replacement->id,
        'start_date' => '2027-05-03',
        'end_date' => '2027-05-04',
    ];
    $this->actingAs($this->hrUser);

    foreach ([true, false] as $includeSelection) {
        if (! $includeSelection) {
            unset($data['festival_id']);
        }
        if ($flow === 'admin') {
            $this->put(route('leave-requests.update', $leave), $data)
                ->assertSessionHasNoErrors()->assertRedirect(route('leave-requests.index'));
        } else {
            $this->patchJson('/api/v1/leaves/'.$leave->id, $includeSelection ? $data : ['remarks' => 'Updated remarks'])
                ->assertOk();
        }

        expect($leave->details()->where('field_key', 'festival_id')->count())->toBe(1)
            ->and($leave->details()->where('field_key', 'festival_id')->value('field_value'))->toBe((string) $replacement->id);
    }
})->with(['admin', 'api']);

test('festival validation preserves configured annual day limits and balance checks', function (string $rule) {
    if ($rule === 'annual_days') {
        $this->policy->update(['annual_limit' => 1]);
    } else {
        $this->policy->update(['allow_balance' => true]);
        LeaveBalance::query()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => 2027,
            'allocated' => 1,
            'used' => 0,
            'balance' => 1,
            'pending' => 0,
        ]);
    }

    $this->actingAs($this->hrUser)
        ->postJson(route('leave-requests.validate'), $this->leaveData)
        ->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonStructure(['errors' => ['total_days']]);

    $this->postJson('/api/v1/leaves/validate', $this->leaveData)
        ->assertUnprocessable()->assertJsonValidationErrors(['total_days']);
    $this->postJson('/api/v1/leaves', $this->leaveData)
        ->assertUnprocessable()->assertJsonValidationErrors(['total_days']);
})->with(['annual_days', 'balance']);

test('festival previews exclude the request being edited from the annual use check', function () {
    $leave = LeaveRequest::query()->create([
        ...collect($this->leaveData)->except('festival_id')->all(),
        'total_days' => 2,
    ]);
    $leave->details()->create([
        'field_key' => 'festival_id',
        'field_name' => 'Festival ID',
        'field_value' => (string) $this->festival->id,
    ]);
    $data = [...$this->leaveData, 'ignore_leave_request_id' => $leave->id];

    $this->actingAs($this->hrUser)
        ->postJson(route('leave-requests.validate'), $data)
        ->assertOk()->assertJsonPath('valid', true);
    $this->postJson('/api/v1/leaves/validate', $data)
        ->assertOk()->assertJsonPath('data.eligible', true);

    $this->getJson('/api/v1/leaves/festivals?year=2027')
        ->assertOk()->assertJsonPath('data.limit_reached', true);
});

test('festival quick status updates enforce eligibility only for active statuses', function (string $condition) {
    $leave = LeaveRequest::query()->create([
        ...collect($this->leaveData)->except('festival_id')->all(),
        'total_days' => 2,
    ]);
    $leave->details()->create([
        'field_key' => 'festival_id',
        'field_name' => 'Festival ID',
        'field_value' => (string) $this->festival->id,
    ]);
    if ($condition !== 'eligible') {
        $this->festival->update(['is_active' => false]);
    }

    $status = $condition === 'cancel' ? 'cancelled' : 'approved';
    $response = $this->actingAs($this->hrUser)
        ->put(route('leave-requests.update', $leave), ['status' => $status]);

    if ($condition === 'ineligible') {
        $response->assertSessionHasErrors(['festival_id']);
        expect($leave->fresh()->status)->toBe('applied');
    } else {
        $response->assertSessionHasNoErrors();
        expect($leave->fresh()->status)->toBe($status);
    }
})->with(['eligible', 'ineligible', 'cancel']);

test('festival restrictions remain scoped to festival leave types', function () {
    $this->leaveType->update(['code' => 'GENERAL', 'leave_name' => 'General Leave']);
    $data = collect($this->leaveData)->except('festival_id')->all();
    $this->actingAs($this->hrUser)
        ->postJson(route('leave-requests.validate'), $data)
        ->assertOk()->assertJsonPath('valid', true);
    $this->postJson('/api/v1/leaves/validate', $data)
        ->assertOk()->assertJsonPath('data.eligible', true);
});
