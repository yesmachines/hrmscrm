<?php

use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\Organisation;

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
            'requires_attachment' => 0,
            'requires_approval' => 1,
            'max_days' => 30,
            'annual_limit' => 21,
            'gender' => 'all',
            'allow_once' => 0,
            'allow_balance' => 1,
            'status' => 1,
            'requires_handover' => 0,
        ])
        ->assertRedirect();

    $leaveType = LeaveType::query()->where('code', 'AL')->first();
    expect($leaveType)->not->toBeNull()
        ->and($leaveType->is_paid)->toBeTrue()
        ->and($leaveType->requires_approval)->toBeTrue();

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
            'full_pay_days' => 15,
            'half_pay_days' => 5,
            'no_pay_days' => 1,
            'requires_document_after_days' => 3,
            'requires_weekend_document' => 0,
            'carry_forward' => 1,
            'encashment' => 0,
            'remarks' => 'Standard annual leave policy',
            'requires_attachment' => 0,
            'probation_applicable' => 0,
            'minimum_service_months' => 3,
        ])
        ->assertRedirect();

    $policy = LeavePolicy::query()
        ->where('leave_type_id', $leaveType->id)
        ->where('organisation_id', $organisation->id)
        ->first();

    expect($policy)->not->toBeNull()
        ->and($policy->carry_forward)->toBeTrue();

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

test('annual limit is mandatory while max days is optional when creating and updating leave types', function () {
    $admin = createHrmsLoginUser('admin');

    // 1. Missing annual_limit should fail validation
    $this->actingAs($admin)
        ->from(route('leave-types.create'))
        ->post(route('leave-types.store'), [
            'leave_name' => 'Casual Leave',
            'code' => 'CL',
            'max_days' => 5,
            'annual_limit' => '',
        ])
        ->assertRedirect(route('leave-types.create'))
        ->assertSessionHasErrors(['annual_limit']);

    // 2. Providing annual_limit without max_days should succeed
    $this->actingAs($admin)
        ->post(route('leave-types.store'), [
            'leave_name' => 'Casual Leave',
            'code' => 'CL',
            'max_days' => null,
            'annual_limit' => 12,
            'is_paid' => 1,
            'status' => 1,
        ])
        ->assertRedirect(route('leave-types.index'));

    $created = LeaveType::query()->where('code', 'CL')->first();
    expect($created)->not->toBeNull()
        ->and($created->annual_limit)->toBe(12)
        ->and($created->max_days)->toBeNull();

    // 3. Updating leave type without annual_limit should fail validation
    $this->actingAs($admin)
        ->from(route('leave-types.edit', $created))
        ->put(route('leave-types.update', $created), [
            'leave_name' => 'Casual Leave Updated',
            'code' => 'CL',
            'annual_limit' => '',
            'max_days' => 10,
        ])
        ->assertRedirect(route('leave-types.edit', $created))
        ->assertSessionHasErrors(['annual_limit']);

    // 4. Updating leave type with annual_limit and nullable max_days should succeed
    $this->actingAs($admin)
        ->put(route('leave-types.update', $created), [
            'leave_name' => 'Casual Leave Updated',
            'code' => 'CL',
            'annual_limit' => 15,
            'max_days' => null,
        ])
        ->assertRedirect(route('leave-types.show', $created));

    expect($created->fresh()->annual_limit)->toBe(15)
        ->and($created->fresh()->max_days)->toBeNull();
});
