<?php

use App\Concerns\LeaveBusinessRules;
use App\Http\Controllers\Api\V1\LeaveController;
use App\Http\Controllers\Leave\LeaveRequestController;
use App\Models\EmployeeProfile;
use App\Models\Festival;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organisation;
use App\Models\SalesCrm\Employee;
use App\Services\LeaveTypeRules;
use App\Services\LeaveValidationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = createHrmsLoginUser('hr');
    $this->actingAs($this->user);
    $this->withToken($this->user->createToken('parity')->plainTextToken);
    $organisation = Organisation::query()->firstOrCreate(
        ['id' => 1],
        ['org_name' => 'Parity Organisation', 'short_name' => 'PARITY', 'status' => 1],
    );
    $this->employee = Employee::query()->create([
        'user_id' => $this->user->id,
        'emp_num' => 'PARITY-'.$this->user->id,
        'organisation_id' => $organisation->id,
        'designation' => 'Engineer',
        'division' => 'Technology',
        'joining_date' => '2026-01-05',
        'status' => 1,
        'has_report' => false,
    ]);
    $this->profile = EmployeeProfile::query()->create([
        'employee_id' => $this->employee->id,
        'gender' => 'F',
        'marital_status' => 'married',
    ]);
    $this->leaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL',
        'is_paid' => true,
        'requires_approval' => true,
        'status' => 1,
    ]);
    $this->policy = LeavePolicy::query()->create([
        'leave_type_id' => $this->leaveType->id,
        'organisation_id' => $organisation->id,
        'allow_balance' => true,
    ]);
    $this->balance = LeaveBalance::query()->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'year' => 2027,
        'allocated' => 20,
        'used' => 0,
        'balance' => 20,
        'pending' => 0,
    ]);
    $this->data = [
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2027-04-05',
        'end_date' => '2027-04-05',
        'request_type' => 'fullday',
        'status' => 'applied',
    ];
});

test('leave business rules are composed by the shared service rather than controllers', function (string $method) {
    expect(class_uses_recursive(LeaveValidationService::class))->toContain(LeaveBusinessRules::class)
        ->and(class_uses_recursive(LeaveController::class))->not->toContain(LeaveBusinessRules::class)
        ->and(class_uses_recursive(LeaveRequestController::class))->not->toContain(LeaveBusinessRules::class)
        ->and((new ReflectionMethod(LeaveValidationService::class, $method))->getFileName())
        ->toBe((new ReflectionClass(LeaveBusinessRules::class))->getFileName());
})->with([
    'validateMaternityLeave',
    'validateParentalLeave',
    'validateCompassionateLeave',
    'requiresHolidayAttachment',
]);

test('active leave flows enforce the same validation order', function (string $flow, string $stage, string $field, string $message) {
    $data = $this->data;
    switch ($stage) {
        case 'type before dates':
            $this->leaveType->update(['status' => 0]);
            $data['end_date'] = '2027-04-02';
            break;
        case 'dates before profile':
            $data['end_date'] = '2027-04-02';
            $this->leaveType->update(['gender' => 'male']);
            break;
        case 'overlap before profile':
            LeaveRequest::query()->create([...$data, 'total_days' => 1]);
            $this->leaveType->update(['gender' => 'male']);
            break;
        case 'profile before special details':
            $this->leaveType->update(['code' => 'MATERNITY']);
            $this->profile->update(['marital_status' => 'single']);
            break;
        case 'special details before policy':
            $this->leaveType->update(['code' => 'MATERNITY']);
            $this->policy->update(['annual_limit' => 0]);
            break;
        case 'once before annual':
            LeaveRequest::query()->create([...$data, 'start_date' => '2027-02-01', 'end_date' => '2027-02-01', 'total_days' => 1]);
            $this->policy->update(['allow_once' => true, 'annual_limit' => 0]);
            break;
        case 'annual before service':
            $this->policy->update(['annual_limit' => 0, 'minimum_service_months' => 24]);
            break;
        case 'service before balance':
            $this->policy->update(['minimum_service_months' => 24]);
            $this->balance->update(['balance' => 0]);
            break;
        case 'balance before handover':
            $this->balance->update(['balance' => 0]);
            $this->policy->update(['requires_handover' => true]);
            break;
        case 'handover before travel':
            $this->policy->update(['requires_handover' => true]);
            $data['traveling_outside_country'] = true;
            break;
        case 'travel before documents':
            $data['traveling_outside_country'] = true;
            $this->policy->update(['requires_attachment' => true]);
            break;
        case 'documents before festival':
            $this->leaveType->update(['code' => 'FESTIVAL']);
            $this->policy->update(['requires_attachment' => true]);
            break;
        case 'festival last':
            $this->leaveType->update(['code' => 'FESTIVAL']);
            break;
    }

    $existing = in_array($flow, ['admin update', 'admin approve', 'api update'], true)
        ? LeaveRequest::query()->create([...$data, 'total_days' => 1])
        : null;
    if ($existing !== null && ! empty($data['traveling_outside_country'])) {
        $existing->details()->create([
            'field_key' => 'traveling_outside_country',
            'field_name' => 'Traveling Outside Country',
            'field_value' => 'Yes',
        ]);
    }
    $response = match ($flow) {
        'admin preview' => $this->postJson(route('leave-requests.validate'), $data),
        'admin create' => $this->post(route('leave-requests.store'), $data),
        'admin update' => $this->put(route('leave-requests.update', $existing), $data),
        'admin approve' => $this->put(route('leave-requests.update', $existing), ['status' => 'approved']),
        'api preview' => $this->postJson('/api/v1/leaves/validate', $data),
        'api create' => $this->postJson('/api/v1/leaves', $data),
        'api update' => $this->patchJson('/api/v1/leaves/'.$existing->id, $data),
    };

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
    if ($existing !== null) {
        expect($existing->fresh()->status)->toBe('applied');
    }
})->with([
    'admin preview', 'admin create', 'admin update', 'admin approve',
    'api preview', 'api create', 'api update',
])->with([
    ['type before dates', 'leave_type_id', 'inactive'],
    ['dates before profile', 'end_date', 'greater than or equal'],
    ['overlap before profile', 'start_date', 'overlapping'],
    ['profile before special details', 'leave_type_id', 'married'],
    ['special details before policy', 'due_date', 'due date'],
    ['once before annual', 'leave_type_id', 'only be taken once'],
    ['annual before service', 'total_days', 'annual limit'],
    ['service before balance', 'leave_type_id', '24 months'],
    ['balance before handover', 'total_days', 'Insufficient leave balance'],
    ['handover before travel', 'handover_person_id', 'handover person is required'],
    ['travel before documents', 'destination', 'Travel destination'],
    ['documents before festival', 'certificate', 'supporting document'],
    ['festival last', 'festival_id', 'Festival selection'],
]);

test('common input rules reject invalid special fields and uploads across entry points', function (string $flow, string $field) {
    $data = [...$this->data, $field => match ($field) {
        'due_date', 'child_birth_date' => 'not-a-date',
        'certificate' => UploadedFile::fake()->create('invalid.txt', 10, 'text/plain'),
        'travel_contact_no' => 'invalid-phone',
    }];
    $existing = str_ends_with($flow, 'update')
        ? LeaveRequest::query()->create([...$this->data, 'total_days' => 1])
        : null;
    $response = match ($flow) {
        'admin preview' => $this->postJson(route('leave-requests.validate'), $data),
        'admin create' => $this->postJson(route('leave-requests.store'), $data),
        'admin update' => $this->putJson(route('leave-requests.update', $existing), $data),
        'api preview' => $this->postJson('/api/v1/leaves/validate', $data),
        'api create' => $this->postJson('/api/v1/leaves', $data),
        'api update' => $this->patchJson('/api/v1/leaves/'.$existing->id, $data),
    };
    $response->assertUnprocessable()->assertJsonValidationErrors($field);
})->with(['admin preview', 'admin create', 'admin update', 'api preview', 'api create', 'api update'])
    ->with(['due_date', 'child_birth_date', 'certificate', 'travel_contact_no']);

test('special rule configuration drives both validation and all field metadata', function () {
    $this->leaveType->update(['code' => 'PARENTAL']);
    config([
        'params.leave_type.parental_birth_window_months' => 2,
        'params.leave_type.detail_fields.child_birth_date.label' => 'Baby Birth Date',
    ]);
    $data = [...$this->data, 'child_birth_date' => '2027-01-05'];
    $this->postJson('/api/v1/leaves/validate', $data)->assertUnprocessable()->assertJsonValidationErrors('child_birth_date');
    $this->postJson(route('leave-requests.validate'), $data)->assertOk()->assertJsonPath('valid', false)
        ->assertJsonPath('errors.child_birth_date.0', "Parental Leave must start within 2 months of the child's birth.");
    config(['params.leave_type.parental_birth_window_months' => 4]);
    $this->postJson('/api/v1/leaves/validate', $data)->assertOk();
    $this->postJson(route('leave-requests.validate'), $data)->assertOk()->assertJsonPath('valid', true);

    $expected = app(LeaveTypeRules::class)->fieldDefinitions($this->leaveType);
    expect($expected[0]['label'])->toBe('Baby Birth Date')
        ->and($expected[0]['help'])->toContain('4 months');
    $meta = collect($this->getJson('/api/v1/leaves/meta')->assertOk()->json('data.leave_types'))->firstWhere('id', $this->leaveType->id);
    expect($meta['detail_fields'])->toBe(['child_birth_date'])
        ->and($meta['field_definitions'])->toBe($expected);
    $existing = LeaveRequest::query()->create([...$this->data, 'total_days' => 1]);
    foreach ([route('leave-requests.create'), route('leave-requests.edit', $existing)] as $url) {
        $this->withoutVite()->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('leaveTypes', fn ($types) => collect($types)->firstWhere('id', $this->leaveType->id)['field_definitions'] === $expected));
    }
    $this->withoutVite()->get(route('leave-requests.show', $existing))
        ->assertInertia(fn (Assert $page) => $page->where('fieldDefinitions', $expected));
});

test('only supported special leave types have configured rules', function () {
    expect(array_keys(config('params.leave_type.special_rules')))
        ->toBe(['SICK', 'MATERNITY', 'PARENTAL', 'COMPASSIONATE', 'FESTIVAL']);
});

test('configured marital restrictions apply consistently to admin and API previews', function () {
    config(['params.leave_type.special_rules.PARENTAL.marital_status' => 'single']);
    $this->leaveType->update(['code' => 'PARENTAL']);
    $data = [...$this->data, 'child_birth_date' => '2027-04-01'];
    $this->postJson('/api/v1/leaves/validate', $data)->assertUnprocessable()->assertJsonValidationErrors('leave_type_id');
    $this->profile->update(['marital_status' => 'single']);
    $this->postJson('/api/v1/leaves/validate', $data)->assertOk();
    $this->postJson(route('leave-requests.validate'), $data)->assertOk()->assertJsonPath('valid', true);
});

test('festival annual request limit is configurable without replacing the festival service', function () {
    $this->leaveType->update(['code' => 'FESTIVAL']);
    $festival = Festival::query()->create([
        'name' => 'Parity Festival', 'type' => 'festival', 'shortcode' => 'PARITY',
        'start_date' => '2027-04-05', 'end_date' => '2027-04-05', 'is_active' => true,
    ]);
    LeaveRequest::query()->create([...$this->data, 'start_date' => '2027-02-01', 'end_date' => '2027-02-01', 'total_days' => 1]);
    $data = [...$this->data, 'festival_id' => $festival->id];
    $this->postJson('/api/v1/leaves/validate', $data)->assertUnprocessable()->assertJsonValidationErrors('festival_id');
    config(['params.leave_type.special_rules.FESTIVAL.annual_request_limit' => 2]);
    $this->postJson('/api/v1/leaves/validate', $data)->assertOk();
    $this->postJson(route('leave-requests.validate'), $data)->assertOk()->assertJsonPath('valid', true);
});

test('approved edits reuse only their own deducted balance in the same employee type and year', function () {
    $leave = LeaveRequest::query()->create([...$this->data, 'status' => 'approved', 'total_days' => 1]);
    $this->balance->update(['balance' => 0, 'used' => 1]);
    $this->postJson(route('leave-requests.validate'), [...$this->data, 'ignore_leave_request_id' => $leave->id])
        ->assertOk()->assertJsonPath('valid', true);
    $this->put(route('leave-requests.update', $leave), [...$this->data, 'status' => 'approved'])->assertSessionHasNoErrors();
    $this->postJson(route('leave-requests.validate'), [
        ...$this->data, 'end_date' => '2027-04-06', 'ignore_leave_request_id' => $leave->id,
    ])->assertOk()->assertJsonPath('valid', false)->assertJsonStructure(['errors' => ['total_days']]);
    $this->postJson(route('leave-requests.validate'), [
        ...$this->data, 'start_date' => '2028-04-03', 'end_date' => '2028-04-03', 'ignore_leave_request_id' => $leave->id,
    ])->assertOk()->assertJsonPath('valid', false)->assertJsonStructure(['errors' => ['total_days']]);
});

test('admin and API edits preserve omitted special details but reject explicit clearing', function (string $flow) {
    $this->leaveType->update(['code' => 'MATERNITY']);
    $leave = LeaveRequest::query()->create([...$this->data, 'total_days' => 1]);
    $leave->details()->create(['field_key' => 'due_date', 'field_name' => 'Expected Due Date', 'field_value' => '2027-05-01']);
    if ($flow === 'admin') {
        $this->put(route('leave-requests.update', $leave), $this->data)->assertSessionHasNoErrors();
        $this->put(route('leave-requests.update', $leave), [...$this->data, 'due_date' => null])->assertSessionHasErrors('due_date');
    } else {
        $this->patchJson('/api/v1/leaves/'.$leave->id, ['remarks' => 'Updated'])->assertOk();
        $this->patchJson('/api/v1/leaves/'.$leave->id, ['due_date' => null])->assertUnprocessable()->assertJsonValidationErrors('due_date');
    }
    expect($leave->details()->where('field_key', 'due_date')->value('field_value'))->toBe('2027-05-01')
        ->and($leave->details()->where('field_key', 'due_date')->count())->toBe(1);
})->with(['admin', 'api']);

test('rejection and cancellation stay possible when active eligibility no longer holds', function (string $status) {
    $leave = LeaveRequest::query()->create([...$this->data, 'total_days' => 1]);
    $this->leaveType->update(['status' => 0, 'code' => 'MATERNITY']);
    $this->balance->update(['balance' => 0]);
    $this->policy->update(['requires_attachment' => true, 'requires_handover' => true]);
    $this->postJson(route('leave-requests.validate'), [
        ...$this->data, 'status' => $status, 'ignore_leave_request_id' => $leave->id,
    ])->assertOk()->assertJsonPath('valid', true);
    $this->put(route('leave-requests.update', $leave), [...$this->data, 'status' => $status])->assertSessionHasNoErrors();
    expect($leave->fresh()->status)->toBe($status);
})->with(['rejected', 'cancelled']);

test('validation never mutates balances or applies pay tiers', function () {
    $this->policy->tiers()->create(['from_day' => 1, 'to_day' => 5, 'pay_percentage' => 50]);
    $before = $this->balance->fresh()->getAttributes();
    app(LeaveValidationService::class)->validateApplication($this->employee, $this->data);
    expect($this->balance->fresh()->getAttributes())->toBe($before);
});

test('configured detail requirements are respected in every active flow', function (string $flow, string $code, string $field) {
    $this->leaveType->update(['code' => $code]);
    $existing = in_array($flow, ['admin update', 'admin approve', 'api update'], true)
        ? LeaveRequest::query()->create([...$this->data, 'total_days' => 1])
        : null;
    config(['params.leave_type.detail_fields.'.$field.'.required' => false]);
    $response = match ($flow) {
        'admin preview' => $this->postJson(route('leave-requests.validate'), $this->data),
        'admin create' => $this->post(route('leave-requests.store'), $this->data),
        'admin update' => $this->put(route('leave-requests.update', $existing), $this->data),
        'admin approve' => $this->put(route('leave-requests.update', $existing), ['status' => 'approved']),
        'api preview' => $this->postJson('/api/v1/leaves/validate', $this->data),
        'api create' => $this->postJson('/api/v1/leaves', $this->data),
        'api update' => $this->patchJson('/api/v1/leaves/'.$existing->id, $this->data),
    };
    if ($flow === 'admin preview') {
        $response->assertOk()->assertJsonPath('valid', true);
    } elseif (str_starts_with($flow, 'admin')) {
        $response->assertRedirect()->assertSessionHasNoErrors();
    } else {
        $response->assertSuccessful();
    }
    expect(app(LeaveTypeRules::class)->fieldDefinitions($this->leaveType)[0]['required'])->toBeFalse();
})->with([
    'admin preview', 'admin create', 'admin update', 'admin approve',
    'api preview', 'api create', 'api update',
])->with([
    ['MATERNITY', 'due_date'], ['PARENTAL', 'child_birth_date'],
    ['COMPASSIONATE', 'family_relationship'], ['FESTIVAL', 'festival_id'],
]);

test('unknown configured validators fail explicitly instead of silently bypassing special rules', function () {
    config(['params.leave_type.special_rules.ANNUAL.validator' => 'invalid-validator']);
    expect(fn () => app(LeaveValidationService::class)->validateApplication($this->employee, $this->data))
        ->toThrow(LogicException::class, 'Unknown leave validator');
});

test('sick holiday certificates are enforced across admin and API flows', function (string $flow, string $holidayDate, bool $hasCertificate) {
    Storage::fake('public');
    $this->leaveType->update(['code' => 'SICK']);
    Festival::query()->create([
        'name' => 'Sick Leave Holiday', 'type' => 'holiday', 'shortcode' => 'SLH',
        'start_date' => $holidayDate, 'end_date' => $holidayDate, 'is_active' => true,
        'religion' => 'Hinduism',
    ]);
    $data = $this->data;
    $existing = in_array($flow, ['admin update', 'admin approve', 'api update'], true)
        ? LeaveRequest::query()->create([...$data, 'total_days' => 1])
        : null;
    if ($hasCertificate) {
        if ($existing !== null) {
            $existing->files()->create(['file_path' => 'leave-certificates/existing.pdf', 'uploaded_by' => $this->user->id]);
        } else {
            $data['certificate'] = UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf');
        }
    }

    $response = match ($flow) {
        'admin preview' => $this->postJson(route('leave-requests.validate'), $data),
        'admin create' => $this->post(route('leave-requests.store'), $data),
        'admin update' => $this->put(route('leave-requests.update', $existing), $data),
        'admin approve' => $this->put(route('leave-requests.update', $existing), ['status' => 'approved']),
        'api preview' => $this->postJson('/api/v1/leaves/validate', $data),
        'api create' => $this->postJson('/api/v1/leaves', $data),
        'api update' => $this->patchJson('/api/v1/leaves/'.$existing->id, $data),
    };

    if (! $hasCertificate) {
        if ($flow === 'admin preview') {
            $response->assertOk()->assertJsonPath('valid', false)
                ->assertJsonPath('errors.certificate.0', 'A supporting document is required for leave including or immediately adjacent to a holiday.');
        } elseif (str_starts_with($flow, 'admin')) {
            $response->assertSessionHasErrors('certificate');
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('certificate');
        }
        expect(LeaveRequest::query()->count())->toBe($existing === null ? 0 : 1);
        if ($existing !== null) {
            expect($existing->fresh()->status)->toBe('applied');
        }

        return;
    }

    if ($flow === 'admin preview') {
        $response->assertOk()->assertJsonPath('valid', true)->assertJsonPath('attachment_required', true);
    } elseif (str_starts_with($flow, 'admin')) {
        $response->assertRedirect()->assertSessionHasNoErrors();
    } elseif ($flow === 'api preview') {
        $response->assertOk()->assertJsonPath('data.attachment_required', true);
    } else {
        $response->assertSuccessful();
    }
})->with([
    'admin preview', 'admin create', 'admin update', 'admin approve',
    'api preview', 'api create', 'api update',
])->with(['2027-04-04', '2027-04-05', '2027-04-06'])->with([false, true]);

test('sick holiday checking respects calendar boundaries and configuration', function (string $scenario, bool $required) {
    $this->leaveType->update(['code' => 'SICK']);
    $attributes = [
        'name' => 'Calendar Boundary Holiday', 'type' => 'holiday', 'shortcode' => 'CBH',
        'start_date' => '2027-04-04', 'end_date' => '2027-04-04', 'is_active' => true,
    ];
    switch ($scenario) {
        case 'weekend gap':
            $attributes['start_date'] = $attributes['end_date'] = '2027-04-02';
            break;
        case 'after adjacent day':
            $attributes['start_date'] = $attributes['end_date'] = '2027-04-07';
            break;
        case 'inactive':
            $attributes['is_active'] = false;
            break;
        case 'festival':
            $attributes['type'] = 'festival';
            break;
        case 'different leave':
            $this->leaveType->update(['code' => 'ANNUAL']);
            break;
        case 'disabled':
            config(['params.leave_type.special_rules.SICK.requires_holiday_document' => false]);
            break;
        case 'multi day holiday':
            $attributes['start_date'] = '2027-03-30';
            $attributes['end_date'] = '2027-04-07';
            break;
        case 'null end date':
            $attributes['end_date'] = null;
            break;
        case 'old null end date':
            $attributes['start_date'] = '2027-04-01';
            $attributes['end_date'] = null;
            break;
        case 'year boundary':
            $this->data['start_date'] = $this->data['end_date'] = '2027-12-31';
            $attributes['start_date'] = $attributes['end_date'] = '2028-01-01';
            break;
        case 'month boundary':
            $this->data['start_date'] = $this->data['end_date'] = '2027-04-30';
            $attributes['start_date'] = $attributes['end_date'] = '2027-05-01';
            break;
    }
    $holiday = Festival::query()->create($attributes);
    if ($scenario === 'deleted') {
        $holiday->delete();
    }
    if ($scenario === 'no holidays') {
        $holiday->forceDelete();
    }
    $validation = app(LeaveValidationService::class)->validateApplication($this->employee, $this->data, true);
    expect($validation['attachment_required'])->toBe($required)
        ->and($validation['total_days'])->toBe(1.0);
})->with([
    ['weekend gap', false], ['after adjacent day', false], ['inactive', false], ['deleted', false],
    ['festival', false], ['different leave', false], ['disabled', false], ['no holidays', false],
    ['multi day holiday', true], ['null end date', true], ['old null end date', false],
    ['year boundary', true], ['month boundary', true],
]);

test('sick weekend document checks remain policy controlled without holidays', function (bool $enabled) {
    $this->leaveType->update(['code' => 'SICK']);
    $this->policy->update(['requires_weekend_document' => $enabled]);
    $data = [...$this->data, 'start_date' => '2027-04-02'];
    $validation = app(LeaveValidationService::class)->validateApplication($this->employee, $data, true);
    expect($validation['attachment_required'])->toBe($enabled)
        ->and($validation['total_days'])->toBe(2.0);
})->with([false, true]);

test('sick holiday certificate requirements do not block rejection or cancellation', function (string $status) {
    $this->leaveType->update(['code' => 'SICK']);
    Festival::query()->create([
        'name' => 'Cancellation Holiday', 'type' => 'holiday', 'shortcode' => 'CCH',
        'start_date' => '2027-04-06', 'end_date' => '2027-04-06', 'is_active' => true,
    ]);
    $leave = LeaveRequest::query()->create([...$this->data, 'total_days' => 1]);
    $this->put(route('leave-requests.update', $leave), ['status' => $status])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($leave->fresh()->status)->toBe($status);
})->with(['rejected', 'cancelled']);
