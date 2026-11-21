<?php

use App\Models\EmployeeProfile;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organisation;
use App\Models\SalesCrm\Department;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use App\Services\LeaveValidationService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

beforeEach(function () {
    $this->user = createHrmsLoginUser('hr', ['email' => Str::uuid().'@example.test']);
    $this->actingAs($this->user)->withToken($this->user->createToken('policy-rules')->plainTextToken);
    $organisation = Organisation::query()->firstOrCreate(
        ['id' => 1],
        ['org_name' => 'Policy Rules', 'short_name' => 'RULES', 'status' => 1],
    );
    $department = Department::query()->forceCreate([
        'id' => (int) Department::query()->max('id') + 1,
        'name' => 'Policy Rules',
        'code' => 'RULES-'.Str::random(8),
        'organisation_id' => $organisation->id,
        'status' => 1,
    ]);
    $this->employee = Employee::query()->create([
        'user_id' => $this->user->id,
        'emp_num' => 'RULES-'.$this->user->id,
        'organisation_id' => $organisation->id,
        'department_id' => $department->id,
        'designation' => 'Engineer',
        'division' => 'Technology',
        'joining_date' => '2025-04-05',
        'status' => 1,
        'has_report' => false,
    ]);
    EmployeeProfile::query()->create([
        'employee_id' => $this->employee->id,
        'religion' => 'Islam',
    ]);
    $this->leaveType = LeaveType::query()->create([
        'leave_name' => 'Unpaid Leave',
        'code' => 'UNPAID',
        'is_paid' => false,
        'requires_approval' => true,
        'status' => 1,
    ]);
    $this->policy = LeavePolicy::query()->create([
        'leave_type_id' => $this->leaveType->id,
        'organisation_id' => $organisation->id,
        'allow_balance' => true,
    ]);
    $this->data = [
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2027-04-05',
        'end_date' => '2027-04-05',
        'request_type' => 'fullday',
        'remarks' => 'Personal reasons',
        'status' => 'applied',
    ];
    $this->service = app(LeaveValidationService::class);
});

/** @param array<string, mixed> $data */
function submitPolicyRuleFlow(TestCase $test, string $flow, array $data): TestResponse
{
    $existing = in_array($flow, ['admin update', 'admin approve', 'api update'], true)
        ? LeaveRequest::query()->create([...$data, 'total_days' => 1])
        : null;

    return match ($flow) {
        'admin preview' => $test->postJson(route('leave-requests.validate'), $data),
        'admin create' => $test->post(route('leave-requests.store'), $data),
        'admin update' => $test->put(route('leave-requests.update', $existing), $data),
        'admin approve' => $test->put(route('leave-requests.update', $existing), ['status' => 'approved']),
        'api preview' => $test->postJson('/api/v1/leaves/validate', $data),
        'api create' => $test->postJson('/api/v1/leaves', $data),
        'api update' => $test->patchJson('/api/v1/leaves/'.$existing->id, $data),
    };
}

function assertPolicyRuleError(TestResponse $response, string $flow, string $field, string $message): void
{
    if ($flow === 'admin preview') {
        $response->assertOk()->assertJsonPath('valid', false);
        $errors = $response->json('errors');
    } elseif (str_starts_with($flow, 'admin')) {
        $response->assertSessionHasErrors($field);
        $errors = session('errors')->getBag('default')->messages();
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors($field);
        $errors = $response->json('errors');
    }

    expect(array_key_first($errors))->toBe($field)
        ->and($errors[$field][0])->toContain($message);
}

/** @param array<string, mixed> $attributes */
function policyRuleColleague(Employee $employee, array $attributes = []): Employee
{
    $user = SalesCrmUser::factory()->create(['email' => Str::uuid().'@example.test']);

    return Employee::query()->create([
        ...$employee->only(['organisation_id', 'department_id', 'designation', 'division', 'joining_date', 'status', 'has_report']),
        'user_id' => $user->id,
        'emp_num' => 'RULES-'.$user->id,
        ...$attributes,
    ]);
}

dataset('policy rule flows', [
    'admin preview', 'admin create', 'admin update', 'admin approve',
    'api preview', 'api create', 'api update',
]);

test('new business rules reject consistently in every active flow', function (string $flow, string $rule, string $field, string $message) {
    $data = $this->data;
    switch ($rule) {
        case 'reason before allowance':
            $data['remarks'] = '   ';
            LeaveRequest::query()->create([...$data, 'start_date' => '2027-01-04', 'end_date' => '2027-01-04', 'total_days' => 90]);
            break;
        case 'annual safeguard':
            LeaveRequest::query()->create([...$data, 'start_date' => '2027-01-04', 'end_date' => '2027-01-04', 'total_days' => 90]);
            break;
        case 'independent handover':
            $data['end_date'] = '2027-04-08';
            $this->policy->update(['requires_handover' => false, 'requires_document_after_days' => 10]);
            break;
        case 'missing joining date':
            $this->policy->update(['minimum_service_months' => 24, 'allow_balance' => false]);
            $this->employee->update(['joining_date' => null]);
            $this->employee->forceFill(['created_at' => '2000-01-01'])->save();
            break;
        case 'minimum service before department':
            $this->policy->update(['minimum_service_months' => 24, 'exclude_rules' => ['department_overlap' => true]]);
            $this->employee->update(['joining_date' => '2026-04-05', 'department_id' => null]);
            break;
        case 'missing department':
            $this->policy->update(['exclude_rules' => ['department_overlap' => true]]);
            $this->employee->update(['department_id' => null]);
            break;
        case 'department before handover':
            $data['end_date'] = '2027-04-08';
            $this->policy->update(['exclude_rules' => ['department_overlap' => true]]);
            $peer = policyRuleColleague($this->employee);
            LeaveRequest::query()->create([...$data, 'employee_id' => $peer->id, 'total_days' => 4]);
            break;
    }

    assertPolicyRuleError(submitPolicyRuleFlow($this, $flow, $data), $flow, $field, $message);
})->with('policy rule flows')->with([
    ['reason before allowance', 'remarks', 'reason is required'],
    ['annual safeguard', 'total_days', 'permitted allowance'],
    ['independent handover', 'handover_person_id', 'handover person is required'],
    ['missing joining date', 'leave_type_id', 'Joining date is required'],
    ['minimum service before department', 'leave_type_id', '24 months'],
    ['missing department', 'leave_type_id', 'department is required'],
    ['department before handover', 'start_date', 'same leave type'],
]);

test('unpaid requests at three days need neither balance nor handover in every flow', function (string $flow) {
    $this->policy->update(['requires_handover' => true, 'requires_document_after_days' => 0]);
    $response = submitPolicyRuleFlow($this, $flow, [...$this->data, 'end_date' => '2027-04-07']);
    if ($flow === 'admin preview') {
        $response->assertOk()->assertJsonPath('valid', true)->assertJsonPath('handover_required', false);
    } elseif (str_starts_with($flow, 'admin')) {
        $response->assertRedirect()->assertSessionHasNoErrors();
    } elseif ($flow === 'api preview') {
        $response->assertOk()->assertJsonPath('data.eligible', true)->assertJsonPath('data.handover_required', false);
    } else {
        $response->assertSuccessful();
    }
    expect(LeaveBalance::query()->where('employee_id', $this->employee->id)->exists())->toBeFalse();
})->with('policy rule flows');

test('required handover descriptions accept task lists and reject empty task lists', function (string $description, bool $valid) {
    $this->policy->update(['requires_handover' => true]);
    $handoverPerson = policyRuleColleague($this->employee);
    $data = [
        ...$this->data,
        'end_date' => '2027-04-08',
        'handover_person_id' => $handoverPerson->id,
        'handover_description' => $description,
    ];
    $validate = fn () => $this->service->validateApplication($this->employee, $data);

    if ($valid) {
        expect($validate())->toBeArray();

        return;
    }

    expect($validate)->toThrow(ValidationException::class, 'handover task');
})->with([
    'multiple tasks' => ['["Cover incoming requests", "Review approvals"]', true],
    'legacy text' => ['Cover incoming requests', true],
    'empty task list' => ['[]', false],
    'blank task list' => ['["", "   "]', false],
    'non-string task' => ['["Cover requests", 3]', false],
]);

test('admin and app previews reject empty required handover task lists', function (string $flow) {
    $this->policy->update(['requires_handover' => true]);
    $handoverPerson = policyRuleColleague($this->employee);

    assertPolicyRuleError(
        submitPolicyRuleFlow($this, $flow, [
            ...$this->data,
            'end_date' => '2027-04-08',
            'handover_person_id' => $handoverPerson->id,
            'handover_description' => '[]',
        ]),
        $flow,
        'handover_description',
        'handover task',
    );
})->with(['admin preview', 'api preview']);

test('admin and app requests persist JSON handover task lists', function (string $flow) {
    $this->policy->update(['requires_handover' => true]);
    $handoverPerson = policyRuleColleague($this->employee);
    $description = '["Cover incoming requests", "Review approvals"]';
    $response = submitPolicyRuleFlow($this, $flow, [
        ...$this->data,
        'end_date' => '2027-04-08',
        'handover_person_id' => $handoverPerson->id,
        'handover_description' => $description,
    ]);

    if (str_starts_with($flow, 'admin')) {
        $response->assertRedirect();
    } elseif ($flow === 'api create') {
        $response->assertCreated();
    } else {
        $response->assertOk();
    }

    $leaveRequest = LeaveRequest::query()->latest('id')->firstOrFail();

    expect($leaveRequest->details()->where('field_key', 'handover_description')->value('field_value'))
        ->toBe($description);
})->with(['admin create', 'admin update', 'api create', 'api update']);

test('Annual Leave fields are persisted by admin and API create flows', function (string $flow) {
    $annual = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL',
        'requires_approval' => true,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $annual->id,
        'organisation_id' => $this->employee->organisation_id,
        'allow_balance' => false,
    ]);

    $data = [
        ...$this->data,
        'leave_type_id' => $annual->id,
        'local_contact_no' => '+971501234567',
        'traveling_outside_country' => '1',
        'destination' => 'London',
        'travel_contact_no' => '+447700900123',
        'ticket_status' => 'ticket',
    ];
    $response = $flow === 'admin'
        ? $this->post(route('leave-requests.store'), $data)
        : $this->postJson('/api/v1/leaves', $data);

    if ($flow === 'admin') {
        $response->assertRedirect()->assertSessionHasNoErrors();
    } else {
        $response->assertCreated();
    }

    $leaveRequest = LeaveRequest::query()
        ->where('employee_id', $this->employee->id)
        ->where('leave_type_id', $annual->id)
        ->firstOrFail();
    expect($leaveRequest->details()->pluck('field_value', 'field_key')->all())
        ->toMatchArray([
            'local_contact_no' => '+971501234567',
            'traveling_outside_country' => 'Yes',
            'destination' => 'London',
            'travel_contact_no' => '+447700900123',
            'ticket_status' => 'ticket',
        ]);

    if ($flow === 'admin') {
        $this->get(route('leave-requests.edit', $leaveRequest))
            ->assertInertia(fn (Assert $page) => $page
                ->where('leaveRequest.local_contact_no', '+971501234567')
                ->where('leaveRequest.traveling_outside_country', '1')
                ->where('leaveRequest.destination', 'London')
                ->where('leaveRequest.travel_contact_no', '+447700900123')
                ->where('leaveRequest.ticket_status', 'ticket'));
    }
})->with(['admin', 'api']);

test('Annual Leave requires the local contact number', function () {
    $annual = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL',
        'requires_approval' => true,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $annual->id,
        'organisation_id' => $this->employee->organisation_id,
        'allow_balance' => false,
    ]);

    $this->postJson('/api/v1/leaves/validate', [
        ...$this->data,
        'leave_type_id' => $annual->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('local_contact_no');
});

test('API can mark Annual Leave as not travelling and removes destination details', function () {
    $annual = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL',
        'requires_approval' => true,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $annual->id,
        'organisation_id' => $this->employee->organisation_id,
        'allow_balance' => false,
    ]);

    $response = $this->postJson('/api/v1/leaves', [
        ...$this->data,
        'leave_type_id' => $annual->id,
        'local_contact_no' => '+971501234567',
        'traveling_outside_country' => true,
        'destination' => 'London',
        'travel_contact_no' => '+447700900123',
        'ticket_status' => 'ticket',
    ])->assertCreated();
    $leaveRequestId = $response->json('data.id');

    $this->patchJson('/api/v1/leaves/'.$leaveRequestId, [
        'traveling_outside_country' => false,
    ])->assertOk();

    expect(LeaveRequest::query()->findOrFail($leaveRequestId)->details()->pluck('field_value', 'field_key')->all())
        ->toMatchArray([
            'local_contact_no' => '+971501234567',
            'traveling_outside_country' => 'No',
            'ticket_status' => 'ticket',
        ])
        ->not->toHaveKeys(['destination', 'travel_contact_no']);
});

test('handover uses a strict configurable threshold independent of documents', function (float $days, bool $required) {
    $this->policy->update(['requires_handover' => false, 'requires_document_after_days' => 10, 'requires_attachment' => true]);
    $this->getJson('/api/v1/leaves/meta')->assertOk()
        ->assertJsonPath('data.leave_types.0.policy.requires_attachment_after_days', 10)
        ->assertJsonPath('data.leave_types.0.policy.requires_handover_after_days', 3)
        ->assertJsonPath('data.leave_types.0.policy.requires_handover', true)
        ->assertJsonPath('data.leave_types.0.policy.remarks_required', true);
    expect($this->service->requiresHandover($this->leaveType, $this->policy, $days))->toBe($required)
        ->and($this->policy->requiresAttachmentForDuration($days))->toBeFalse();
})->with([[0.5, false], [3.0, false], [3.5, true], [4.0, true]]);

test('unpaid constants can be changed without touching the service or frontend', function () {
    config([
        'params.leave_type.special_rules.UNPAID.internal_annual_limit' => 2,
        'params.leave_type.special_rules.UNPAID.handover_after_days' => 1,
        'params.leave_type.special_rules.UNPAID.remarks_required' => false,
    ]);
    $this->getJson('/api/v1/leaves/meta')->assertOk()
        ->assertJsonPath('data.leave_types.0.policy.requires_handover_after_days', 1)
        ->assertJsonPath('data.leave_types.0.policy.remarks_required', false);
    expect($this->service->requiresHandover($this->leaveType, null, 1))->toBeFalse()
        ->and($this->service->requiresHandover($this->leaveType, null, 1.5))->toBeTrue();
    $this->service->validateApplication($this->employee, [...$this->data, 'remarks' => null]);
    expect(fn () => $this->service->validatePolicyEligibility($this->employee, $this->leaveType, null, Carbon::parse('2027-04-05'), 2.5))
        ->toThrow(ValidationException::class, 'permitted allowance');
});

test('unpaid safeguard counts only active same-year usage and allows the exact ceiling', function (float $used, float $requested, string $status, string $date, bool $valid) {
    LeaveRequest::query()->create([
        ...$this->data, 'start_date' => $date, 'end_date' => $date, 'total_days' => $used, 'status' => $status,
    ]);
    $this->policy->update(['annual_limit' => 1]);
    $validate = fn () => $this->service->validatePolicyEligibility(
        $this->employee, $this->leaveType, $this->policy, Carbon::parse('2027-04-05'), $requested,
    );
    if ($valid) {
        $validate();
        expect(true)->toBeTrue();
    } else {
        try {
            $validate();
            $this->fail('Expected internal allowance rejection.');
        } catch (ValidationException $exception) {
            expect($exception->errors()['total_days'][0])->not->toContain('90', 'remaining', (string) $used);
        }
    }
})->with([
    [89.0, 1.0, 'applied', '2027-01-04', true],
    [89.5, 0.5, 'approved', '2027-01-04', true],
    [89.5, 1.0, 'applied', '2027-01-04', false],
    [90.0, 1.0, 'approved', '2027-01-04', false],
    [90.0, 1.0, 'rejected', '2027-01-04', true],
    [90.0, 1.0, 'cancelled', '2027-01-04', true],
    [90.0, 1.0, 'applied', '2026-01-05', true],
]);

test('unpaid rules also apply without an organisation policy', function () {
    $this->policy->delete();
    $this->getJson('/api/v1/leaves/meta')->assertOk()
        ->assertJsonPath('data.leave_types.0.policy.allow_balance', false)
        ->assertJsonPath('data.leave_types.0.policy.requires_attachment', false)
        ->assertJsonPath('data.leave_types.0.policy.requires_handover_after_days', 3)
        ->assertJsonPath('data.leave_types.0.policy.requires_handover', true)
        ->assertJsonPath('data.leave_types.0.policy.remarks_required', true)
        ->assertJsonMissingPath('data.leave_types.0.application_requirements');
    $this->service->validateApplication($this->employee, $this->data);
    expect(fn () => $this->service->validateApplication($this->employee, [...$this->data, 'remarks' => null]))
        ->toThrow(ValidationException::class, 'reason is required');
    expect(fn () => $this->service->validatePolicyEligibility($this->employee, $this->leaveType, null, Carbon::parse('2027-04-05'), 90.5))
        ->toThrow(ValidationException::class, 'permitted allowance');
});

test('minimum service uses actual joining date with an inclusive no-overflow anniversary', function (string $joining, string $start, int $months, bool $valid) {
    $this->employee->update(['joining_date' => $joining]);
    $this->employee->forceFill(['created_at' => '2000-01-01'])->save();
    $this->policy->update(['minimum_service_months' => $months]);
    $validate = fn () => $this->service->validatePolicyEligibility($this->employee, $this->leaveType, $this->policy, Carbon::parse($start), 1);
    if ($valid) {
        $validate();
        expect(true)->toBeTrue();
    } else {
        expect($validate)->toThrow(ValidationException::class, 'months of service');
    }
})->with([
    ['2025-04-05', '2027-04-04', 24, false],
    ['2025-04-05', '2027-04-05', 24, true],
    ['2025-01-31', '2025-02-27', 1, false],
    ['2025-01-31', '2025-02-28', 1, true],
    ['2024-02-29', '2026-02-28', 24, true],
]);

test('department overlap is opt-in date-inclusive organisation and leave-type scoped', function (string $scenario, bool $blocked) {
    $this->policy->update(['exclude_rules' => ['department_overlap' => true]]);
    $peer = policyRuleColleague($this->employee);
    $data = [...$this->data, 'employee_id' => $peer->id, 'total_days' => 1];
    switch ($scenario) {
        case 'approved':
            $data['status'] = 'approved';
            break;
        case 'cancelled':
        case 'rejected':
            $data['status'] = $scenario;
            break;
        case 'other organisation':
            $other = Organisation::query()->create(['org_name' => 'Other', 'short_name' => 'OTHER', 'status' => 1]);
            $peer->update(['organisation_id' => $other->id]);
            break;
        case 'other department':
            $peer->update(['department_id' => null]);
            break;
        case 'other leave type':
            $other = LeaveType::query()->create(['leave_name' => 'Other', 'code' => 'OTHER', 'status' => 1]);
            $data['leave_type_id'] = $other->id;
            break;
        case 'ends before':
            $data['start_date'] = $data['end_date'] = '2027-04-04';
            break;
        case 'starts after':
            $data['start_date'] = $data['end_date'] = '2027-04-06';
            break;
        case 'ends on start':
            $data['start_date'] = '2027-04-01';
            break;
        case 'starts on end':
            $data['end_date'] = '2027-04-09';
            break;
        case 'false':
            $this->policy->update(['exclude_rules' => ['department_overlap' => false]]);
            break;
        case 'missing key':
            $this->policy->update(['exclude_rules' => ['other_rule' => true]]);
            break;
        case 'no rules':
            $this->policy->update(['exclude_rules' => null]);
            break;
    }
    LeaveRequest::query()->create($data);
    $validate = fn () => $this->service->validateApplication($this->employee, $this->data);
    if ($blocked) {
        expect($validate)->toThrow(ValidationException::class, 'same leave type');
    } else {
        $validate();
        expect(true)->toBeTrue();
    }
})->with([
    ['applied', true], ['approved', true], ['ends on start', true], ['starts on end', true],
    ['cancelled', false], ['rejected', false], ['other organisation', false], ['other department', false],
    ['other leave type', false], ['ends before', false], ['starts after', false],
    ['false', false], ['missing key', false], ['no rules', false],
]);

test('pilgrimage keeps policy religion once-per-service and annual checks', function (string $scenario, string $message) {
    $this->leaveType->update(['code' => 'PILGRIMAGE', 'religion' => 'Islam']);
    $this->policy->update([
        'annual_limit' => 5, 'allow_balance' => false, 'minimum_service_months' => 24,
        'exclude_rules' => ['department_overlap' => true],
    ]);
    if ($scenario === 'religion') {
        EmployeeProfile::query()->where('employee_id', $this->employee->id)->update(['religion' => 'Hindu']);
    } else {
        LeaveRequest::query()->create([
            ...$this->data, 'start_date' => '2027-01-04', 'end_date' => '2027-01-08', 'total_days' => 5,
        ]);
        if ($scenario === 'once') {
            $this->policy->update(['allow_once' => true]);
        }
    }
    expect(fn () => $this->service->validateApplication($this->employee, $this->data))
        ->toThrow(ValidationException::class, $message);
})->with([['religion', 'only applicable for Islam'], ['once', 'only be taken once'], ['annual', 'annual limit of 5']]);

test('unpaid handover details are saved by API creation and updates independently of documents', function () {
    $peer = policyRuleColleague($this->employee);
    $data = [
        ...$this->data, 'end_date' => '2027-04-08',
        'handover_person_id' => $peer->id, 'handover_description' => 'Cover customer queries',
    ];
    $this->policy->update(['requires_handover' => false, 'requires_document_after_days' => 10]);
    $this->postJson('/api/v1/leaves', $data)->assertCreated();
    $leave = LeaveRequest::query()->where('employee_id', $this->employee->id)->sole();
    expect($leave->details()->where('field_key', 'handover_person_id')->value('field_value'))->toBe((string) $peer->id);
    $this->patchJson('/api/v1/leaves/'.$leave->id, ['handover_description' => 'Updated handover'])->assertOk();
    expect($leave->details()->where('field_key', 'handover_description')->value('field_value'))->toBe('Updated handover');
});

test('unpaid never mutates an existing balance on create edit approval or cancellation', function (string $flow) {
    $balance = LeaveBalance::query()->create([
        'employee_id' => $this->employee->id, 'leave_type_id' => $this->leaveType->id,
        'year' => 2027, 'allocated' => 20, 'used' => 2, 'balance' => 18, 'pending' => 3,
    ]);
    if ($flow === 'admin cancellation') {
        $leave = LeaveRequest::query()->create([...$this->data, 'total_days' => 1, 'status' => 'approved']);
        $this->post(route('leave-requests.cancel', $leave))->assertRedirect()->assertSessionHasNoErrors();
    } else {
        $data = $this->data;
        if ($flow === 'admin create') {
            $data['status'] = 'approved';
        }
        $response = submitPolicyRuleFlow($this, $flow, $data);
        if (str_starts_with($flow, 'admin')) {
            $response->assertRedirect()->assertSessionHasNoErrors();
        } else {
            $response->assertSuccessful();
        }
    }
    expect($balance->fresh()->only(['allocated', 'used', 'balance', 'pending']))->toBe($balance->only(['allocated', 'used', 'balance', 'pending']));
})->with(['admin create', 'admin update', 'admin approve', 'api create', 'api update', 'admin cancellation']);

test('unpaid ceiling stays out of API metadata and both preview responses', function () {
    $this->policy->update(['annual_limit' => 90]);
    $response = $this->getJson('/api/v1/leaves/meta')->assertOk()
        ->assertJsonMissingPath('data.types')
        ->assertJsonMissingPath('data.leave_types.0.annual_limit')
        ->assertJsonMissingPath('data.leave_types.0.allow_once')
        ->assertJsonMissingPath('data.leave_types.0.allow_balance')
        ->assertJsonPath('data.leave_types.0.policy.annual_limit', null)
        ->assertJsonPath('data.leave_types.0.policy.allow_balance', false)
        ->assertJsonPath('data.leave_types.0.policy.requires_handover_after_days', 3)
        ->assertJsonPath('data.leave_types.0.policy.remarks_required', true)
        ->assertJsonMissingPath('data.leave_types.0.application_requirements');
    expect($response->json('data.leave_types.0'))->not->toHaveKey('internal_annual_limit');
    $this->postJson('/api/v1/leaves/validate', $this->data)->assertOk()
        ->assertJsonPath('data.policy.annual_limit', null)
        ->assertJsonPath('data.policy.remarks_required', true)
        ->assertJsonPath('data.policy.requires_handover_after_days', 3)
        ->assertJsonMissingPath('data.application_requirements')
        ->assertJsonMissingPath('data.annual_limit');
    $this->postJson(route('leave-requests.validate'), $this->data)->assertOk()->assertJsonPath('annual_limit', null);
});

test('unpaid ceiling stays out of admin request and policy page props', function () {
    $this->policy->update(['annual_limit' => 90]);
    $leave = LeaveRequest::query()->create([...$this->data, 'total_days' => 1]);
    $this->withoutVite()->get(route('leave-requests.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('leaveTypes.0.policies.0.annual_limit', null));
    $this->get(route('leave-requests.edit', $leave))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('leaveTypes.0.policies.0.annual_limit', null)
            ->where('leaveRequest.leave_type.policy.annual_limit', null));
    foreach (['show', 'edit'] as $action) {
        $this->get(route('leave-policies.'.$action, $this->policy))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('leavePolicy.annual_limit', null));
    }
    $this->get(route('leave-policies.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('leavePolicies.data.0.annual_limit', null));
    expect($this->policy->fresh()->annual_limit)->toBe(90);
});

test('department rule accepts booleans and preserves unknown JSON rules on policy writes', function (string $operation, mixed $flag, bool $valid) {
    $data = [
        'leave_type_id' => $this->leaveType->id, 'organisation_id' => $this->employee->organisation_id,
        'exclude_rules' => json_encode([
            'department_overlap' => $flag,
            'holiday' => 1,
            'weekend' => '1',
            'other_rule' => ['nested' => true],
        ]),
    ];
    if ($operation === 'store') {
        $this->policy->forceDelete();
        $response = $this->post(route('leave-policies.store'), $data);
    } else {
        $response = $this->put(route('leave-policies.update', $this->policy), $data);
    }
    if (! $valid) {
        $response->assertSessionHasErrors('exclude_rules.department_overlap');

        return;
    }
    $response->assertRedirect()->assertSessionHasNoErrors();
    $policy = LeavePolicy::query()->where('leave_type_id', $this->leaveType->id)->sole();
    expect($policy->exclude_rules)->toHaveCount(4)
        ->and($policy->exclude_rules['department_overlap'])->toBe((bool) $flag)
        ->and($policy->exclude_rules['holiday'])->toBeTrue()
        ->and($policy->exclude_rules['weekend'])->toBeTrue()
        ->and($policy->exclude_rules['other_rule'])->toBe(['nested' => true])
        ->and($policy->preventsDepartmentOverlap())->toBe((bool) $flag);
})->with(['store', 'update'])->with([[true, true], [false, true], [1, true], [0, true], ['yes', false], [null, false], [[], false]]);

test('policy forms receive exclusion rule options', function () {
    $this->withoutVite()
        ->get(route('leave-policies.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leave-policies/create')
            ->where('exclusionRuleOptions', config('params.leave_policy.exclusion_rules')));

    $this->get(route('leave-policies.edit', $this->policy))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leave-policies/edit')
            ->where('exclusionRuleOptions', config('params.leave_policy.exclusion_rules')));

    $this->get(route('leave-policies.show', $this->policy))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leave-policies/show')
            ->where('exclusionRuleOptions', config('params.leave_policy.exclusion_rules')));
});

test('policy checkbox updates clear unchecked known rules and preserve unknown rules', function () {
    $this->policy->update([
        'exclude_rules' => [
            'department_overlap' => true,
            'holiday' => true,
            'custom_rule' => ['preserve' => true],
        ],
    ]);

    $this->put(route('leave-policies.update', $this->policy), [
        'leave_type_id' => $this->leaveType->id,
        'organisation_id' => $this->employee->organisation_id,
        'exclude_rules_present' => '1',
        'exclude_rules' => ['weekend' => '1'],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($this->policy->fresh()->exclude_rules)->toEqual([
        'custom_rule' => ['preserve' => true],
        'weekend' => true,
    ]);
});

test('department overlap is enforced by admin and app validation flows', function (string $flow) {
    $this->policy->update(['exclude_rules' => ['department_overlap' => true]]);
    $peer = policyRuleColleague($this->employee);
    LeaveRequest::query()->create([
        ...$this->data,
        'employee_id' => $peer->id,
        'total_days' => 1,
    ]);

    assertPolicyRuleError(
        submitPolicyRuleFlow($this, $flow, $this->data),
        $flow,
        'start_date',
        'same leave type',
    );
})->with(['admin preview', 'api preview']);

test('inactive admin edits and cancellation are not blocked by active business rules', function () {
    $this->policy->update(['minimum_service_months' => 24, 'exclude_rules' => ['department_overlap' => true]]);
    $this->employee->update(['joining_date' => null, 'department_id' => null]);
    $leave = LeaveRequest::query()->create([...$this->data, 'remarks' => null, 'total_days' => 1]);
    $this->put(route('leave-requests.update', $leave), [...$this->data, 'remarks' => null, 'status' => 'cancelled'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($leave->fresh()->status)->toBe('cancelled');
});

test('editing excludes the current request from allowance and department checks', function (bool $changeEmployee) {
    $this->policy->update(['exclude_rules' => ['department_overlap' => true]]);
    $owner = $changeEmployee ? policyRuleColleague($this->employee) : $this->employee;
    $leave = LeaveRequest::query()->create([
        ...$this->data, 'employee_id' => $owner->id, 'total_days' => 90,
    ]);
    $this->service->validateApplication($this->employee, $this->data, false, (int) $leave->id);
    expect($leave->fresh()->total_days)->toEqual(90);
})->with([false, true]);

test('policy selection does not use another organisations department or service requirements', function () {
    $other = Organisation::query()->create(['org_name' => 'Other', 'short_name' => 'OTHER', 'status' => 1]);
    $this->policy->update(['organisation_id' => $other->id, 'minimum_service_months' => 24, 'exclude_rules' => ['department_overlap' => true]]);
    $this->employee->update(['joining_date' => null, 'department_id' => null]);
    $this->service->validateApplication($this->employee, $this->data);
    expect(true)->toBeTrue();
});

test('approved admin edits cannot change the leave type or mutate balances on rejection', function () {
    $annual = LeaveType::query()->create([
        'leave_name' => 'Annual', 'code' => 'ANNUAL', 'requires_approval' => true, 'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $annual->id, 'organisation_id' => $this->employee->organisation_id, 'allow_balance' => true,
    ]);
    $balance = LeaveBalance::query()->create([
        'employee_id' => $this->employee->id, 'leave_type_id' => $annual->id,
        'year' => 2027, 'allocated' => 10, 'used' => 0, 'balance' => 10, 'pending' => 0,
    ]);
    $leave = LeaveRequest::query()->create([...$this->data, 'total_days' => 1, 'status' => 'approved']);
    $this->put(route('leave-requests.update', $leave), [...$this->data, 'leave_type_id' => $annual->id, 'status' => 'approved'])
        ->assertRedirect()->assertSessionHasErrors('leave_type_id');
    expect((float) $balance->fresh()->balance)->toBe(10.0)
        ->and((float) $balance->fresh()->used)->toBe(0.0)
        ->and($leave->fresh()->leave_type_id)->toBe($this->leaveType->id);
});
