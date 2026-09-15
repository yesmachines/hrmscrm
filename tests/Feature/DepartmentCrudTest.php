<?php

use App\Models\SalesCrm\Department;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\EmployeeManager;
use App\Models\SalesCrm\User as SalesCrmUser;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    DB::connection('salescrm')->table('personal_access_tokens')->delete();
    DB::connection('salescrm')->table('employee_managers')->delete();
    DB::connection('salescrm')->table('departments')->delete();
    DB::connection('salescrm')->table('employees')->delete();
    DB::connection('salescrm')->table('users')->delete();
});

test('guests cannot access departments', function () {
    $this->get(route('departments.index'))->assertRedirect(route('login'));
});

test('authenticated users can view departments index', function () {
    $user = createHrmsLoginUser('admin');

    Department::query()->create([
        'name' => 'Human Resources',
        'code' => 'HR',
        'status' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('departments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('departments/index')
            ->has('departments.data', 1)
            ->where('departments.data.0.name', 'Human Resources')
        );
});

test('authenticated users can create a department in HRMS', function () {
    $user = createHrmsLoginUser('admin');

    $response = $this->actingAs($user)
        ->post(route('departments.store'), [
            'name' => 'Operations & Logistics',
            'code' => 'OPS',
            'status' => 1,
        ]);

    $dept = Department::query()->where('name', 'Operations & Logistics')->first();
    expect($dept)->not->toBeNull()
        ->and($dept->code)->toBe('OPS')
        ->and($dept->status)->toBe(1);

    $response->assertRedirect(route('departments.show', $dept));
});

test('authenticated users can update a department', function () {
    $user = createHrmsLoginUser('admin');

    $dept = Department::query()->create([
        'name' => 'Information Tech',
        'code' => 'IT',
        'status' => 1,
    ]);

    $response = $this->actingAs($user)
        ->put(route('departments.update', $dept), [
            'name' => 'Information Technology',
            'code' => 'INFOTECH',
            'status' => 1,
        ]);

    $dept->refresh();
    expect($dept->name)->toBe('Information Technology')
        ->and($dept->code)->toBe('INFOTECH');

    $response->assertRedirect(route('departments.show', $dept));
});

test('authenticated users can delete a department', function () {
    $user = createHrmsLoginUser('admin');

    $dept = Department::query()->create([
        'name' => 'Temporary Department',
        'code' => 'TMP',
        'status' => 1,
    ]);

    $response = $this->actingAs($user)
        ->delete(route('departments.destroy', $dept));

    expect(Department::find($dept->id))->toBeNull();
    $response->assertRedirect(route('departments.index'));
});

test('authenticated users can assign reporting manager with department to an employee', function () {
    $adminUser = createHrmsLoginUser('admin');

    $managerUser = SalesCrmUser::factory()->create(['name' => 'Manager User']);
    $manager = Employee::query()->create([
        'user_id' => $managerUser->id,
        'emp_num' => 'EMP-MGR',
        'designation' => 'HR Director',
        'division' => 'sd',
        'status' => 1,
        'has_report' => true,
    ]);

    $employeeUser = SalesCrmUser::factory()->create(['name' => 'Staff User']);
    $employee = Employee::query()->create([
        'user_id' => $employeeUser->id,
        'emp_num' => 'EMP-STF',
        'designation' => 'HR Officer',
        'division' => 'sd',
        'status' => 1,
        'has_report' => false,
    ]);

    $dept = Department::query()->create([
        'name' => 'Human Resources',
        'code' => 'HR',
        'status' => 1,
    ]);

    $response = $this->actingAs($adminUser)
        ->post(route('employees.managers.store', $employee), [
            'manager_id' => $manager->id,
            'department_id' => $dept->id,
        ]);

    $response->assertRedirect();

    $relation = EmployeeManager::query()
        ->where('employee_id', $employee->id)
        ->where('manager_id', $manager->id)
        ->first();

    expect($relation)->not->toBeNull()
        ->and($relation->department_id)->toBe($dept->id);
});

test('authenticated users can remove reporting manager from an employee', function () {
    $adminUser = createHrmsLoginUser('admin');

    $managerUser = SalesCrmUser::factory()->create();
    $manager = Employee::query()->create([
        'user_id' => $managerUser->id,
        'emp_num' => 'EMP-M1',
        'designation' => 'Manager',
        'division' => 'sd',
        'status' => 1,
        'has_report' => true,
    ]);

    $employeeUser = SalesCrmUser::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $employeeUser->id,
        'emp_num' => 'EMP-E1',
        'designation' => 'Employee',
        'division' => 'sd',
        'status' => 1,
        'has_report' => false,
    ]);

    EmployeeManager::query()->create([
        'employee_id' => $employee->id,
        'manager_id' => $manager->id,
        'department_id' => 1,
    ]);

    $response = $this->actingAs($adminUser)
        ->delete(route('employees.managers.destroy', [$employee, $manager->id]));

    $response->assertRedirect();

    expect(EmployeeManager::query()
        ->where('employee_id', $employee->id)
        ->where('manager_id', $manager->id)
        ->exists()
    )->toBeFalse();
});
