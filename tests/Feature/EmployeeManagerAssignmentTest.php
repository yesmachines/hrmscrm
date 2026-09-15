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

test('guests cannot access employee managers index', function () {
    $this->get(route('employee-managers.index'))->assertRedirect(route('login'));
});

test('authenticated users can view employee managers index with assignments and stats', function () {
    $user = createHrmsLoginUser('admin');

    $managerUser = SalesCrmUser::factory()->create(['name' => 'Sarah Connor']);
    $manager = Employee::query()->create([
        'user_id' => $managerUser->id,
        'emp_num' => 'MGR-001',
        'designation' => 'Engineering Lead',
        'division' => 'sd',
        'status' => 1,
        'has_report' => true,
    ]);

    $employeeUser = SalesCrmUser::factory()->create(['name' => 'John Connor']);
    $employee = Employee::query()->create([
        'user_id' => $employeeUser->id,
        'emp_num' => 'EMP-001',
        'designation' => 'Software Engineer',
        'division' => 'sd',
        'status' => 1,
        'has_report' => false,
    ]);

    $department = Department::query()->create([
        'name' => 'Engineering',
        'code' => 'ENG',
        'status' => 1,
    ]);

    EmployeeManager::query()->create([
        'employee_id' => $employee->id,
        'manager_id' => $manager->id,
        'department_id' => $department->id,
        'priority' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('employee-managers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employees/managers/index')
            ->has('assignments.data', 1)
            ->where('assignments.data.0.priority', 1)
            ->where('assignments.data.0.department.name', 'Engineering')
            ->where('stats.total_assignments', 1)
            ->where('stats.assigned_employees', 1)
            ->where('stats.active_managers', 1)
        );
});

test('authenticated users can assign an employee to a manager with priority and department', function () {
    $user = createHrmsLoginUser('admin');

    $mgrUser = SalesCrmUser::factory()->create(['name' => 'Lead Manager']);
    $manager = Employee::query()->create([
        'user_id' => $mgrUser->id,
        'emp_num' => 'M01',
        'designation' => 'Lead',
        'division' => 'sd',
        'status' => 1,
        'has_report' => true,
    ]);

    $empUser = SalesCrmUser::factory()->create(['name' => 'Junior Staff']);
    $employee = Employee::query()->create([
        'user_id' => $empUser->id,
        'emp_num' => 'E01',
        'designation' => 'Junior Dev',
        'division' => 'sd',
        'status' => 1,
        'has_report' => false,
    ]);

    $dept = Department::query()->create([
        'name' => 'Technology',
        'code' => 'TECH',
        'status' => 1,
    ]);

    $response = $this->actingAs($user)
        ->post(route('employee-managers.store'), [
            'employee_id' => $employee->id,
            'manager_id' => $manager->id,
            'department_id' => $dept->id,
            'priority' => 2,
        ]);

    $response->assertRedirect();

    $assignment = EmployeeManager::query()
        ->where('employee_id', $employee->id)
        ->where('manager_id', $manager->id)
        ->first();

    expect($assignment)->not->toBeNull()
        ->and($assignment->department_id)->toBe($dept->id)
        ->and($assignment->priority)->toBe(2);
});

test('cannot assign an employee as their own manager', function () {
    $user = createHrmsLoginUser('admin');

    $empUser = SalesCrmUser::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $empUser->id,
        'emp_num' => 'E99',
        'designation' => 'Developer',
        'division' => 'sd',
        'status' => 1,
        'has_report' => false,
    ]);

    $response = $this->actingAs($user)
        ->post(route('employee-managers.store'), [
            'employee_id' => $employee->id,
            'manager_id' => $employee->id,
            'priority' => 1,
        ]);

    $response->assertSessionHasErrors('manager_id');
});

test('authenticated users can update a manager assignment', function () {
    $user = createHrmsLoginUser('admin');

    $manager1User = SalesCrmUser::factory()->create();
    $manager1 = Employee::query()->create([
        'user_id' => $manager1User->id,
        'emp_num' => 'M1',
        'designation' => 'Manager 1',
        'division' => 'sd',
        'status' => 1,
        'has_report' => true,
    ]);

    $manager2User = SalesCrmUser::factory()->create();
    $manager2 = Employee::query()->create([
        'user_id' => $manager2User->id,
        'emp_num' => 'M2',
        'designation' => 'Manager 2',
        'division' => 'sd',
        'status' => 1,
        'has_report' => true,
    ]);

    $employeeUser = SalesCrmUser::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $employeeUser->id,
        'emp_num' => 'E1',
        'designation' => 'Dev',
        'division' => 'sd',
        'status' => 1,
        'has_report' => false,
    ]);

    $assignment = EmployeeManager::query()->create([
        'employee_id' => $employee->id,
        'manager_id' => $manager1->id,
        'priority' => 1,
    ]);

    $response = $this->actingAs($user)
        ->put(route('employee-managers.update', $assignment), [
            'manager_id' => $manager2->id,
            'priority' => 3,
        ]);

    $response->assertRedirect();

    $assignment->refresh();
    expect($assignment->manager_id)->toBe($manager2->id)
        ->and($assignment->priority)->toBe(3);
});

test('authenticated users can remove an employee manager assignment', function () {
    $user = createHrmsLoginUser('admin');

    $managerUser = SalesCrmUser::factory()->create();
    $manager = Employee::query()->create([
        'user_id' => $managerUser->id,
        'emp_num' => 'M1',
        'designation' => 'Manager',
        'division' => 'sd',
        'status' => 1,
        'has_report' => true,
    ]);

    $employeeUser = SalesCrmUser::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $employeeUser->id,
        'emp_num' => 'E1',
        'designation' => 'Employee',
        'division' => 'sd',
        'status' => 1,
        'has_report' => false,
    ]);

    $assignment = EmployeeManager::query()->create([
        'employee_id' => $employee->id,
        'manager_id' => $manager->id,
        'priority' => 1,
    ]);

    $response = $this->actingAs($user)
        ->delete(route('employee-managers.destroy', $assignment));

    $response->assertRedirect();
    expect(EmployeeManager::find($assignment->id))->toBeNull();
});
