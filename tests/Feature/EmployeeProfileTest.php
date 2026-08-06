<?php

use App\Models\EmployeeProfile;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use App\Support\SalesCrmRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Never mass-delete Sales CRM tables. Only touch rows created inside each test.
 */
beforeEach(function () {
    $database = (string) config('database.connections.salescrm.database');

    expect($database)->toEndWith('_testing');

    if (! Schema::connection('salescrm')->hasTable('roles')) {
        return;
    }

    $sales = DB::connection('salescrm');

    foreach (['admin', 'hr', 'salesmanager'] as $role) {
        if (! $sales->table('roles')->where('name', $role)->exists()) {
            $sales->table('roles')->insert([
                'name' => $role,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
});

afterEach(function () {
    // Clean only the emails/emp_nums this suite creates — never truncate tables.
    $sales = DB::connection('salescrm');
    $testEmails = [
        'test.employee@example.com',
        'old.employee@example.com',
        'delete.me@example.com',
    ];
    $testEmpNums = ['T-9001', 'T-9002', 'T-9003'];

    $userIds = $sales->table('users')->whereIn('email', $testEmails)->pluck('id');
    $employeeIds = $sales->table('employees')->whereIn('emp_num', $testEmpNums)->pluck('id');

    if ($employeeIds->isNotEmpty()) {
        EmployeeProfile::query()->whereIn('employee_id', $employeeIds)->delete();
        $sales->table('employees')->whereIn('id', $employeeIds)->delete();
    }

    if ($userIds->isNotEmpty()) {
        SalesCrmRoles::revokeAllRolesForUsers($userIds->all());
        $sales->table('users')->whereIn('id', $userIds)->delete();
    }
});

test('guests cannot access employees', function () {
    $this->get(route('employees.index'))->assertRedirect(route('login'));
});

test('authenticated users can view the employees index', function () {
    $user = createHrmsLoginUser('admin');

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('employees.index'))
        ->assertOk();
});

test('authenticated users can create an employee across salescrm and hrms', function () {
    $admin = createHrmsLoginUser('admin');

    $this->actingAs($admin)
        ->post(route('employees.store'), [
            'name' => 'Test Employee',
            'email' => 'test.employee@example.com',
            'password' => 'Password123!',
            'roles' => 'salesmanager',
            'emp_num' => 'T-9001',
            'designation' => 'Engineer',
            'division' => 'ops',
            'employment_status' => 'fulltime',
            'status' => 1,
            'has_report' => 1,
            'nationality' => 'Indian',
            'personal_email' => 'personal@example.com',
            'gender' => 'M',
        ])
        ->assertRedirect();

    $crmUser = SalesCrmUser::query()->where('email', 'test.employee@example.com')->first();
    expect($crmUser)->not->toBeNull();

    $employee = Employee::query()->where('emp_num', 'T-9001')->first();
    expect($employee)->not->toBeNull()
        ->and($employee->user_id)->toBe($crmUser->id)
        ->and($employee->designation)->toBe('Engineer');

    $this->assertDatabaseHas('employee_profiles', [
        'employee_id' => $employee->id,
        'nationality' => 'Indian',
        'personal_email' => 'personal@example.com',
    ]);

    $roleId = DB::connection('salescrm')->table('roles')->where('name', 'salesmanager')->value('id');
    expect(
        DB::connection('salescrm')
            ->table('model_has_roles')
            ->where('model_id', $crmUser->id)
            ->where('role_id', $roleId)
            ->exists()
    )->toBeTrue();
});

test('authenticated users can update an employee', function () {
    $admin = createHrmsLoginUser('admin');

    $crmUser = SalesCrmUser::query()->create([
        'name' => 'Old Name',
        'email' => 'old.employee@example.com',
        'password' => 'Password123!',
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $crmUser->id,
        'emp_num' => 'T-9002',
        'designation' => 'Analyst',
        'division' => 'sd',
        'status' => 1,
        'has_report' => true,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $employee->id,
        'nationality' => 'Indian',
    ]);

    $this->actingAs($admin)
        ->put(route('employees.update', $employee), [
            'name' => 'New Name',
            'email' => 'old.employee@example.com',
            'emp_num' => 'T-9002',
            'designation' => 'Senior Analyst',
            'division' => 'sd',
            'status' => 1,
            'has_report' => 1,
            'nationality' => 'Emirati',
        ])
        ->assertRedirect(route('employees.show', $employee));

    expect($employee->fresh()->designation)->toBe('Senior Analyst')
        ->and($crmUser->fresh()->name)->toBe('New Name');

    $this->assertDatabaseHas('employee_profiles', [
        'employee_id' => $employee->id,
        'nationality' => 'Emirati',
    ]);
});

test('authenticated users can delete an employee from both databases', function () {
    $admin = createHrmsLoginUser('admin');

    $crmUser = SalesCrmUser::query()->create([
        'name' => 'Delete Me',
        'email' => 'delete.me@example.com',
        'password' => 'Password123!',
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $crmUser->id,
        'emp_num' => 'T-9003',
        'designation' => 'Temp',
        'division' => 'ops',
        'status' => 1,
        'has_report' => true,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $employee->id,
        'nationality' => 'Indian',
    ]);

    $employeeId = $employee->id;
    $userId = $crmUser->id;

    $this->actingAs($admin)
        ->delete(route('employees.destroy', $employee))
        ->assertRedirect(route('employees.index'));

    expect(Employee::query()->find($employeeId))->toBeNull()
        ->and(SalesCrmUser::query()->find($userId))->toBeNull();

    $this->assertDatabaseMissing('employee_profiles', [
        'employee_id' => $employeeId,
    ]);
});
