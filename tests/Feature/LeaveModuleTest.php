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
