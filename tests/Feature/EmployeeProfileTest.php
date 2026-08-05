<?php

use App\Models\EmployeeProfile;
use App\Models\User;

test('guests cannot access employees', function () {
    $this->get(route('employees.index'))->assertRedirect(route('login'));
});

test('authenticated users can view the employees index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('employees.index'))
        ->assertOk();
});

test('authenticated users can view the create employee page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('employees.create'))
        ->assertOk();
});

test('authenticated users can create an employee profile', function () {
    $admin = User::factory()->create();
    $employee = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('employees.store'), [
            'employee_id' => $employee->id,
            'gender' => 'M',
            'nationality' => 'Indian',
            'personal_email' => 'personal@example.com',
            'personal_mobile' => '0501234567',
            'visa_type' => 'visa',
            'marital_status' => 'single',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('employee_profiles', [
        'employee_id' => $employee->id,
        'nationality' => 'Indian',
        'personal_email' => 'personal@example.com',
    ]);
});

test('employee profiles require a unique employee user', function () {
    $admin = User::factory()->create();
    $profile = EmployeeProfile::factory()->create();

    $this->actingAs($admin)
        ->post(route('employees.store'), [
            'employee_id' => $profile->employee_id,
            'nationality' => 'Emirati',
        ])
        ->assertSessionHasErrors('employee_id');
});

test('authenticated users can update an employee profile', function () {
    $admin = User::factory()->create();
    $profile = EmployeeProfile::factory()->create([
        'nationality' => 'Indian',
    ]);

    $this->actingAs($admin)
        ->put(route('employees.update', $profile), [
            'nationality' => 'Emirati',
            'personal_mobile' => '0509998877',
            'gender' => 'F',
        ])
        ->assertRedirect(route('employees.show', $profile));

    $this->assertDatabaseHas('employee_profiles', [
        'id' => $profile->id,
        'nationality' => 'Emirati',
        'personal_mobile' => '0509998877',
    ]);
});

test('authenticated users can delete an employee profile', function () {
    $admin = User::factory()->create();
    $profile = EmployeeProfile::factory()->create();

    $this->actingAs($admin)
        ->delete(route('employees.destroy', $profile))
        ->assertRedirect(route('employees.index'));

    $this->assertDatabaseMissing('employee_profiles', [
        'id' => $profile->id,
    ]);
});
