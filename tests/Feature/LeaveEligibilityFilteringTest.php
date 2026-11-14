<?php

use App\Models\EmployeeProfile;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organisation;
use App\Models\SalesCrm\Employee;
use App\Services\LeaveValidationService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = createHrmsLoginUser('hr');
    $this->actingAs($this->user);
    $this->withToken($this->user->createToken('eligibility-tests')->plainTextToken);
    $organisation = Organisation::query()->firstOrCreate(
        ['id' => 1],
        ['org_name' => 'Eligibility Organisation', 'short_name' => 'ELIG', 'status' => 1],
    );
    $this->employee = Employee::query()->create([
        'user_id' => $this->user->id,
        'emp_num' => 'ELIG-'.$this->user->id,
        'organisation_id' => $organisation->id,
        'designation' => 'Engineer',
        'division' => 'Technology',
        'employment_status' => 'fulltime',
        'joining_date' => '2026-01-05',
        'status' => 1,
        'has_report' => false,
    ]);
    $this->profile = EmployeeProfile::query()->create([
        'employee_id' => $this->employee->id,
        'gender' => 'F',
        'religion' => 'Muslim',
        'marital_status' => 'married',
    ]);
    $this->leaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL',
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
        'end_date' => '2027-04-05',
        'status' => 'applied',
        'due_date' => '2027-04-05',
        'child_birth_date' => '2027-04-01',
    ];
});

test('admin and API dropdowns share profile and organisation policy filtering', function (string $condition, bool $eligible) {
    match ($condition) {
        'gender mismatch' => $this->leaveType->update(['gender' => 'male']),
        'gender alias', 'missing profile' => $this->leaveType->update(['gender' => 'female']),
        'religion mismatch' => $this->leaveType->update(['religion' => 'Hinduism']),
        'religion alias' => $this->leaveType->update(['religion' => 'Islam']),
        'inactive' => $this->leaveType->update(['status' => 0]),
        'single maternity', 'missing maternity', 'married maternity' => $this->leaveType->update(['code' => 'MATERNITY']),
        'single parental' => $this->leaveType->update(['code' => 'PARENTAL']),
        'single paternity' => $this->leaveType->update(['code' => 'PATERNITY']),
        default => null,
    };
    if (str_starts_with($condition, 'single')) {
        $this->profile->update(['marital_status' => 'single']);
    }
    if ($condition === 'missing maternity') {
        $this->profile->update(['marital_status' => null]);
    }
    if ($condition === 'missing profile') {
        $this->profile->delete();
    }
    if ($condition === 'different organisation') {
        $other = Organisation::query()->create(['org_name' => 'Other', 'short_name' => 'OTHER', 'status' => 1]);
        $this->employee->update(['organisation_id' => $other->id]);
    }

    $types = $this->getJson('/api/v1/leaves/meta')->assertOk()->json('data.leave_types');
    expect(collect($types)->pluck('id')->contains($this->leaveType->id))->toBe($eligible);
    $leave = LeaveRequest::query()->create([...$this->data, 'total_days' => 1]);
    foreach ([route('leave-requests.create'), route('leave-requests.edit', $leave)] as $url) {
        $this->withoutVite()->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('leaveTypes', function ($types) use ($eligible): bool {
                $type = collect($types)->firstWhere('id', $this->leaveType->id);

                return $type !== null && in_array($this->employee->id, $type['eligible_employee_ids'], true) === $eligible;
            }));
    }
})->with([
    ['gender mismatch', false], ['gender alias', true], ['missing profile', false],
    ['religion mismatch', false], ['religion alias', true], ['inactive', false],
    ['single maternity', false], ['missing maternity', false], ['married maternity', true],
    ['single parental', false], ['single paternity', true],
    ['different organisation', true],
]);

test('profile restrictions cannot be bypassed in active leave flows', function (string $flow, string $condition) {
    match ($condition) {
        'marital' => $this->leaveType->update(['code' => 'PARENTAL']),
        'gender' => $this->leaveType->update(['gender' => 'male']),
        'religion' => $this->leaveType->update(['religion' => 'Hinduism']),
        'inactive' => $this->leaveType->update(['status' => 0]),
    };
    $this->profile->update(['marital_status' => 'single']);
    $existing = str_ends_with($flow, 'update')
        ? LeaveRequest::query()->create([...$this->data, 'total_days' => 1])
        : null;
    $response = match ($flow) {
        'admin validate' => $this->postJson(route('leave-requests.validate'), $this->data),
        'admin create' => $this->post(route('leave-requests.store'), $this->data),
        'admin update' => $this->put(route('leave-requests.update', $existing), $this->data),
        'api validate' => $this->postJson('/api/v1/leaves/validate', $this->data),
        'api create' => $this->postJson('/api/v1/leaves', $this->data),
        'api update' => $this->patchJson('/api/v1/leaves/'.$existing->id, $this->data),
    };
    if ($flow === 'admin validate') {
        $response->assertOk()->assertJsonPath('valid', false)->assertJsonStructure(['errors' => ['leave_type_id']]);
    } elseif (str_starts_with($flow, 'admin')) {
        $response->assertSessionHasErrors(['leave_type_id']);
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors(['leave_type_id']);
    }
})->with(['admin validate', 'admin create', 'admin update', 'api validate', 'api create', 'api update'])
    ->with(['marital', 'gender', 'religion', 'inactive']);

test('minimum service stays visible initially and is enforced against selected dates', function (string $flow, string $joiningDate, string $startDate, bool $eligible) {
    $this->policy->update(['minimum_service_months' => 3]);
    $this->employee->update(['joining_date' => $joiningDate]);
    $types = $this->getJson('/api/v1/leaves/meta')->assertOk()->json('data.leave_types');
    expect(collect($types)->pluck('id'))->toContain($this->leaveType->id);
    $this->withoutVite()->get(route('leave-requests.create'))->assertInertia(fn (Assert $page) => $page
        ->where('leaveTypes', fn ($types) => in_array($this->employee->id, collect($types)->firstWhere('id', $this->leaveType->id)['eligible_employee_ids'], true)));

    $data = [...$this->data, 'start_date' => $startDate, 'end_date' => $startDate];
    $existing = str_ends_with($flow, 'update')
        ? LeaveRequest::query()->create([...$data, 'total_days' => 1])
        : null;
    $response = match ($flow) {
        'admin validate' => $this->postJson(route('leave-requests.validate'), $data),
        'admin create' => $this->post(route('leave-requests.store'), $data),
        'admin update' => $this->put(route('leave-requests.update', $existing), $data),
        'api validate' => $this->postJson('/api/v1/leaves/validate', $data),
        'api create' => $this->postJson('/api/v1/leaves', $data),
        'api update' => $this->patchJson('/api/v1/leaves/'.$existing->id, $data),
    };
    if ($flow === 'admin validate') {
        $response->assertOk()->assertJsonPath('valid', $eligible);
    } elseif (str_starts_with($flow, 'admin')) {
        $eligible ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('leave_type_id');
    } elseif ($eligible) {
        $response->assertSuccessful();
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('leave_type_id');
    }
})->with(['admin validate', 'admin create', 'admin update', 'api validate', 'api create', 'api update'])->with([
    ['2027-01-05', '2027-04-02', false],
    ['2027-01-05', '2027-04-05', true],
    ['2027-07-05', '2027-04-05', false],
]);

test('metadata exposes festival details and every configured policy setting', function () {
    $this->leaveType->update(['code' => 'FESTIVAL']);
    $this->policy->update(['annual_limit' => 5, 'allow_once' => true, 'carry_forward_weeks' => 4, 'exclude_rules' => ['custom' => true]]);
    $this->policy->tiers()->create(['from_day' => 1, 'to_day' => 5, 'pay_percentage' => 100]);
    $type = collect($this->getJson('/api/v1/leaves/meta')->assertOk()->json('data.leave_types'))->firstWhere('id', $this->leaveType->id);
    expect($type['detail_fields'])->toBe(['festival_id'])
        ->and($type['policy']['annual_limit'])->toBe(5)
        ->and($type['policy']['allow_once'])->toBeTrue()
        ->and($type['policy']['carry_forward_weeks'])->toBe(4)
        ->and($type['policy']['exclude_rules'])->toBe(['custom' => true])
        ->and($type['policy']['tiers'][0]['from_day'])->toBe(1);
});

test('probation remains metadata only and does not restrict eligibility', function () {
    $this->policy->update(['probation_applicable' => false]);
    $type = collect($this->getJson('/api/v1/leaves/meta')->assertOk()->json('data.leave_types'))->firstWhere('id', $this->leaveType->id);
    expect($type['policy']['probation_applicable'])->toBeFalse();
    $this->employee->setAttribute('employment_status', 'probation');
    app(LeaveValidationService::class)->validatePolicyEligibility(
        $this->employee,
        $this->leaveType,
        $this->policy,
        Carbon::parse($this->data['start_date']),
        1,
    );
    $this->postJson('/api/v1/leaves/validate', $this->data)->assertOk();
    $this->postJson(route('leave-requests.validate'), $this->data)->assertOk()->assertJsonPath('valid', true);
});

test('annual quota and balance restrictions remain later form checks', function (string $restriction) {
    $this->policy->update($restriction === 'balance' ? ['allow_balance' => true] : ['annual_limit' => 0]);
    $types = $this->getJson('/api/v1/leaves/meta')->assertOk()->json('data.leave_types');
    expect(collect($types)->pluck('id'))->toContain($this->leaveType->id);
    $this->postJson('/api/v1/leaves/validate', $this->data)->assertUnprocessable()->assertJsonValidationErrors('total_days');
    $this->postJson('/api/v1/leaves', $this->data)->assertUnprocessable()->assertJsonValidationErrors('total_days');
})->with(['balance', 'annual quota']);

test('weekend documents are enforced on admin creation editing and approval', function (string $flow) {
    $this->policy->update(['requires_weekend_document' => true]);
    $data = [...$this->data, 'start_date' => '2027-04-09', 'end_date' => '2027-04-12'];
    $leave = LeaveRequest::query()->create([...$data, 'total_days' => 2]);
    $response = match ($flow) {
        'create' => $this->post(route('leave-requests.store'), $data),
        'update' => $this->put(route('leave-requests.update', $leave), $data),
        'approve' => $this->put(route('leave-requests.update', $leave), ['status' => 'approved']),
    };
    $response->assertSessionHasErrors('certificate');
})->with(['create', 'update', 'approve']);

test('edit preview preserves owned attachments and omitted special details', function () {
    $this->leaveType->update(['code' => 'MATERNITY']);
    $this->policy->update(['requires_attachment' => true]);
    $leave = LeaveRequest::query()->create([...$this->data, 'total_days' => 1]);
    $leave->details()->create(['field_key' => 'due_date', 'field_name' => 'Expected Due Date', 'field_value' => '2027-04-01']);
    $leave->files()->create(['file_path' => 'leave-certificates/existing.pdf', 'uploaded_by' => $this->user->id, 'uploaded_date' => now()]);
    $data = collect($this->data)->except('due_date')->all();
    $this->postJson('/api/v1/leaves/validate', [...$data, 'ignore_leave_request_id' => $leave->id])
        ->assertOk()->assertJsonPath('data.attachment_required', true)->assertJsonPath('data.detail_fields', ['due_date']);
    $this->postJson(route('leave-requests.validate'), [...$data, 'ignore_leave_request_id' => $leave->id])
        ->assertOk()->assertJsonPath('valid', true)->assertJsonPath('attachment_required', true);
});

test('edit preview cannot ignore another employees request or an approved request', function (bool $owned) {
    $other = Employee::query()->create(['user_id' => $this->user->id, 'emp_num' => 'ELIG-OTHER-'.$this->user->id, 'designation' => 'Other', 'division' => 'Tech', 'status' => 1]);
    $leave = LeaveRequest::query()->create([
        ...$this->data,
        'employee_id' => $owned ? $this->employee->id : $other->id,
        'status' => $owned ? 'approved' : 'applied',
        'total_days' => 1,
    ]);
    $this->postJson('/api/v1/leaves/validate', [...$this->data, 'ignore_leave_request_id' => $leave->id])
        ->assertUnprocessable()->assertJsonValidationErrors('ignore_leave_request_id');
})->with([true, false]);

test('unsupported certificate uploads cannot satisfy a policy requirement', function () {
    $this->policy->update(['requires_attachment' => true]);
    $data = [...$this->data, 'certificate' => UploadedFile::fake()->create('certificate.txt', 10, 'text/plain')];
    $this->postJson('/api/v1/leaves/validate', $data)->assertUnprocessable()->assertJsonValidationErrors('certificate');
    $this->postJson('/api/v1/leaves', $data)->assertUnprocessable()->assertJsonValidationErrors('certificate');
});

test('travel details are validated even when no handover is required', function (bool $hasDestination) {
    $data = [...$this->data, 'traveling_outside_country' => true];
    if ($hasDestination) {
        $data['destination'] = 'India';
    }
    $field = $hasDestination ? 'travel_contact_no' : 'destination';
    $this->postJson('/api/v1/leaves/validate', $data)->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->postJson('/api/v1/leaves', $data)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([false, true]);

test('quick approval rechecks policy and profile without blocking cancellation', function (string $restriction) {
    $leave = LeaveRequest::query()->create([...$this->data, 'total_days' => 1]);
    if ($restriction === 'marital') {
        $this->leaveType->update(['code' => 'PARENTAL']);
        $this->profile->update(['marital_status' => 'single']);
    } else {
        $this->policy->update(['minimum_service_months' => 24]);
    }
    $this->put(route('leave-requests.update', $leave), ['status' => 'approved'])->assertSessionHasErrors('leave_type_id');
    $this->put(route('leave-requests.update', $leave), ['status' => 'cancelled'])->assertRedirect();
    expect($leave->fresh()->status)->toBe('cancelled');
})->with(['marital', 'service']);
