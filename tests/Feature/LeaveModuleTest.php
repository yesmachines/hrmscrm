<?php

use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\Organisation;
use Illuminate\Support\Facades\Schema;

test('leave schema includes workbook policy and balance fields', function () {
    expect(Schema::hasColumns('leave_policies', [
        'annual_limit',
        'allow_once',
        'allow_balance',
        'requires_attachment',
        'requires_handover',
        'carry_forward_weeks',
        'exclude_rules',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('leave_types', 'annual_limit'))->toBeFalse()
        ->and(Schema::hasColumn('leave_types', 'allow_once'))->toBeFalse()
        ->and(Schema::hasColumn('leave_types', 'allow_balance'))->toBeFalse()
        ->and(Schema::hasColumn('leave_types', 'max_days'))->toBeFalse()
        ->and(Schema::hasColumn('leave_types', 'requires_attachment'))->toBeFalse()
        ->and(Schema::hasColumn('leave_types', 'requires_handover'))->toBeFalse()
        ->and(Schema::hasColumn('leave_requests', 'request_type'))->toBeTrue()
        ->and(Schema::hasColumn('leave_balances', 'leave_validity'))->toBeTrue()
        ->and(Schema::hasTable('employee_wfh_schedules'))->toBeTrue()
        ->and(Schema::hasTable('leave_policy_tiers'))->toBeTrue()
        ->and(Schema::hasTable('leave_balance_transactions'))->toBeTrue();
});

test('authenticated users can manage leave types and leave policies', function () {
    $admin = createHrmsLoginUser('admin');

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('leave-types.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('leave-types.store'), [
            'leave_name' => 'Annual Leave',
            'code' => 'AL',
            'is_paid' => 1,
            'requires_approval' => 1,
            'gender' => 'all',
            'religion' => 'Muslim',
            'status' => 1,
        ])
        ->assertRedirect();

    $leaveType = LeaveType::query()->where('code', 'AL')->first();
    expect($leaveType)->not->toBeNull()
        ->and($leaveType->is_paid)->toBeTrue()
        ->and($leaveType->requires_approval)->toBeTrue()
        ->and($leaveType->gender)->toBeNull()
        ->and($leaveType->religion)->toBe('Islam');

    $organisation = Organisation::query()->create([
        'org_name' => 'Acme Corp',
        'short_name' => 'ACME',
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('leave-policies.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('leave-policies.store'), [
            'leave_type_id' => $leaveType->id,
            'organisation_id' => $organisation->id,
            'annual_limit' => 24,
            'allow_once' => 1,
            'allow_balance' => 0,
            'full_pay_days' => 15,
            'half_pay_days' => 5,
            'no_pay_days' => 1,
            'requires_document_after_days' => 3,
            'requires_weekend_document' => 0,
            'requires_handover' => 1,
            'carry_forward' => 1,
            'carry_forward_weeks' => 8,
            'encashment' => 0,
            'remarks' => 'Standard annual leave policy',
            'requires_attachment' => 1,
            'probation_applicable' => 0,
            'minimum_service_months' => 3,
            'exclude_rules' => '{"weekends":true}',
        ])
        ->assertRedirect();

    $policy = LeavePolicy::query()
        ->where('leave_type_id', $leaveType->id)
        ->where('organisation_id', $organisation->id)
        ->first();

    expect($policy)->not->toBeNull()
        ->and($policy->carry_forward)->toBeTrue()
        ->and($policy->annual_limit)->toBe(24)
        ->and($policy->allow_once)->toBeTrue()
        ->and($policy->allow_balance)->toBeFalse()
        ->and($policy->requires_attachment)->toBeTrue()
        ->and($policy->requires_handover)->toBeTrue()
        ->and($policy->carry_forward_weeks)->toBe(8)
        ->and($policy->exclude_rules)->toBe(['weekends' => true]);

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('leave-policies.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leave-policies/index')
            ->where('leavePolicies.data.0.requires_attachment', 1)
            ->where('leavePolicies.data.0.requires_handover', 1)
        );

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('leave-policies.show', $policy))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leave-policies/show')
            ->where('leavePolicy.requires_attachment', 1)
            ->where('leavePolicy.requires_handover', 1)
        );

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('leave-policies.edit', $policy))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leave-policies/edit')
            ->where('leavePolicy.annual_limit', 24)
            ->where('leavePolicy.allow_once', 1)
            ->where('leavePolicy.allow_balance', 0)
            ->where('leavePolicy.requires_attachment', 1)
            ->where('leavePolicy.requires_handover', 1)
            ->where('leavePolicy.carry_forward_weeks', 8)
            ->where('leavePolicy.exclude_rules', ['weekends' => true])
        );

    $this->actingAs($admin)
        ->put(route('leave-policies.update', $policy), [
            'leave_type_id' => $leaveType->id,
            'organisation_id' => $organisation->id,
            'annual_limit' => 30,
            'allow_once' => 0,
            'allow_balance' => 1,
            'requires_attachment' => 0,
            'requires_handover' => 0,
            'carry_forward_weeks' => 4,
            'exclude_rules' => '{"public_holidays":true}',
        ])
        ->assertRedirect(route('leave-policies.show', $policy));

    expect($policy->fresh()->annual_limit)->toBe(30)
        ->and($policy->fresh()->allow_once)->toBeFalse()
        ->and($policy->fresh()->allow_balance)->toBeTrue()
        ->and($policy->fresh()->requires_attachment)->toBeFalse()
        ->and($policy->fresh()->requires_handover)->toBeFalse()
        ->and($policy->fresh()->carry_forward_weeks)->toBe(4)
        ->and($policy->fresh()->exclude_rules)->toBe(['public_holidays' => true]);

    $this->actingAs($admin)
        ->delete(route('leave-types.destroy', $leaveType))
        ->assertRedirect();

    expect(LeaveType::query()->find($leaveType->id))->not->toBeNull();

    $this->actingAs($admin)
        ->delete(route('leave-policies.destroy', $policy))
        ->assertRedirect(route('leave-policies.index'));

    $this->actingAs($admin)
        ->delete(route('leave-types.destroy', $leaveType))
        ->assertRedirect(route('leave-types.index'));

    expect(LeaveType::query()->find($leaveType->id))->toBeNull();
});

test('leave policy validates new policy settings and exclusion JSON', function () {
    $admin = createHrmsLoginUser('admin');
    $organisation = Organisation::query()->create([
        'org_name' => 'Validation Org',
        'short_name' => 'VAL',
        'status' => 1,
    ]);
    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Validation Leave',
        'code' => 'VALIDATION',
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->from(route('leave-policies.create'))
        ->post(route('leave-policies.store'), [
            'leave_type_id' => $leaveType->id,
            'organisation_id' => $organisation->id,
            'annual_limit' => -1,
            'allow_once' => 'invalid',
            'allow_balance' => 2,
            'requires_attachment' => 'invalid',
            'requires_handover' => 'invalid',
            'carry_forward_weeks' => 0,
            'exclude_rules' => '{invalid json',
        ])
        ->assertRedirect(route('leave-policies.create'))
        ->assertSessionHasErrors([
            'annual_limit',
            'allow_once',
            'allow_balance',
            'requires_attachment',
            'requires_handover',
            'carry_forward_weeks',
            'exclude_rules',
        ]);
});

test('leave type create and edit use configured gender and religion options', function () {
    $admin = createHrmsLoginUser('admin');

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('leave-types.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leave-types/create')
            ->where('genderOptions', [
                ['value' => 'male', 'label' => 'Male'],
                ['value' => 'female', 'label' => 'Female'],
            ])
            ->where('religionOptions', [
                ['value' => 'Other', 'label' => 'Other'],
                ['value' => 'Hinduism', 'label' => 'Hinduism'],
                ['value' => 'Islam', 'label' => 'Islam'],
                ['value' => 'Christianity', 'label' => 'Christianity'],
                ['value' => 'Sikhism', 'label' => 'Sikhism'],
                ['value' => 'Buddhism', 'label' => 'Buddhism'],
                ['value' => 'Jainism', 'label' => 'Jainism'],
                ['value' => 'Judaism', 'label' => 'Judaism'],
            ])
        );

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Legacy Leave',
        'code' => 'LEGACY',
        'gender' => 'Female',
        'religion' => 'Christianity',
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('leave-types.edit', $leaveType))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leave-types/edit')
            ->where('leaveType.gender', 'female')
            ->where('leaveType.religion', 'Christianity')
            ->has('genderOptions', 2)
            ->has('religionOptions', 8)
        );
});

test('leave type gender and religion are validated against configured options and normalized', function () {
    $admin = createHrmsLoginUser('admin');

    $this->actingAs($admin)
        ->from(route('leave-types.create'))
        ->post(route('leave-types.store'), [
            'leave_name' => 'Configured Leave',
            'code' => 'CONFIGURED',
            'gender' => 'unknown',
            'religion' => 'Zoroastrianism',
        ])
        ->assertRedirect(route('leave-types.create'))
        ->assertSessionHasErrors(['gender', 'religion']);

    $this->actingAs($admin)
        ->post(route('leave-types.store'), [
            'leave_name' => 'Islam Leave',
            'code' => 'ISLAM',
            'gender' => 'FEMALE',
            'religion' => 'Islam',
        ])
        ->assertRedirect(route('leave-types.index'));

    $leaveType = LeaveType::query()->where('code', 'ISLAM')->firstOrFail();
    expect($leaveType->gender)->toBe('female')
        ->and($leaveType->religion)->toBe('Islam');

    $this->actingAs($admin)
        ->from(route('leave-types.edit', $leaveType))
        ->put(route('leave-types.update', $leaveType), [
            'leave_name' => 'Islam Leave',
            'code' => 'ISLAM',
            'gender' => 'all',
            'religion' => 'Hinduism',
        ])
        ->assertRedirect(route('leave-types.show', $leaveType));

    expect($leaveType->fresh()->gender)->toBeNull()
        ->and($leaveType->fresh()->religion)->toBe('Hinduism');
});

test('leave policy create page marks leave types as disabled only if they already have a policy', function () {
    $admin = createHrmsLoginUser('admin');

    $org = Organisation::query()->create([
        'org_name' => 'Acme Test Org',
        'short_name' => 'ATO',
        'status' => 1,
    ]);

    $typeWithPolicy = LeaveType::query()->create([
        'leave_name' => 'Type With Policy',
        'code' => 'TWP',
        'status' => 1,
    ]);

    LeavePolicy::query()->create([
        'leave_type_id' => $typeWithPolicy->id,
        'organisation_id' => $org->id,
    ]);

    $typeWithoutPolicy = LeaveType::query()->create([
        'leave_name' => 'Type Without Policy',
        'code' => 'TWOP',
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('leave-policies.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leave-policies/create')
            ->where('leaveTypes', fn ($types) => collect($types)->firstWhere('id', $typeWithPolicy->id)['disabled'] === true
                && collect($types)->firstWhere('id', $typeWithoutPolicy->id)['disabled'] === false
            )
        );
});

test('leave type CRUD excludes fields assigned to leave policies', function () {
    $admin = createHrmsLoginUser('admin');

    expect(Schema::hasColumn('leave_types', 'annual_limit'))->toBeFalse()
        ->and(Schema::hasColumn('leave_types', 'allow_once'))->toBeFalse()
        ->and(Schema::hasColumn('leave_types', 'allow_balance'))->toBeFalse()
        ->and(Schema::hasColumn('leave_types', 'max_days'))->toBeFalse()
        ->and(Schema::hasColumn('leave_types', 'requires_attachment'))->toBeFalse()
        ->and(Schema::hasColumn('leave_types', 'requires_handover'))->toBeFalse();

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('leave-types.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leave-types/create')
            ->missing('leaveType.annual_limit')
            ->missing('leaveType.allow_once')
            ->missing('leaveType.allow_balance')
        );

    $this->actingAs($admin)
        ->post(route('leave-types.store'), [
            'leave_name' => 'Casual Leave',
            'code' => 'CL',
            'is_paid' => 1,
            'status' => 1,
        ])
        ->assertRedirect(route('leave-types.index'));

    $created = LeaveType::query()->where('code', 'CL')->firstOrFail();
    expect($created)->not->toBeNull();

    $this->actingAs($admin)
        ->put(route('leave-types.update', $created), [
            'leave_name' => 'Casual Leave Updated',
            'code' => 'CL',
        ])
        ->assertRedirect(route('leave-types.show', $created));

    expect(array_key_exists('annual_limit', $created->fresh()->getAttributes()))->toBeFalse()
        ->and(array_key_exists('allow_once', $created->fresh()->getAttributes()))->toBeFalse()
        ->and(array_key_exists('allow_balance', $created->fresh()->getAttributes()))->toBeFalse()
        ->and(array_key_exists('max_days', $created->fresh()->getAttributes()))->toBeFalse()
        ->and(array_key_exists('requires_attachment', $created->fresh()->getAttributes()))->toBeFalse()
        ->and(array_key_exists('requires_handover', $created->fresh()->getAttributes()))->toBeFalse();
});
