<?php

use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::connection('salescrm')->table('personal_access_tokens')->delete();
    DB::connection('salescrm')->table('employee_managers')->delete();
    DB::connection('salescrm')->table('employees')->delete();
    DB::connection('salescrm')->table('departments')->delete();
    DB::connection('salescrm')->table('users')->delete();
});

test('unauthenticated request to employee details returns 401', function () {
    $response = $this->getJson('/api/v1/employees/1');

    $response->assertUnauthorized();
});

test('employee details returns top_level and low_level hierarchy using cm_employee_managers', function () {
    // 1. Create Top-level Manager (Director)
    $directorUser = SalesCrmUser::query()->create([
        'name' => 'John Smith',
        'email' => 'john.smith@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $director = Employee::query()->create([
        'user_id' => $directorUser->id,
        'emp_num' => 'EMP-001',
        'designation' => 'Managing Director',
        'division' => 'Management',
        'status' => 1,
        'has_report' => true,
    ]);

    // 2. Create Middle Manager (Operations Manager)
    $managerUser = SalesCrmUser::query()->create([
        'name' => 'Jane Doe',
        'email' => 'jane.doe@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $manager = Employee::query()->create([
        'user_id' => $managerUser->id,
        'emp_num' => 'EMP-002',
        'designation' => 'Operations Manager',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => true,
    ]);

    // 3. Create Subordinates (Team Members)
    $sub1User = SalesCrmUser::query()->create([
        'name' => 'Sarah Wilson',
        'email' => 'sarah.wilson@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $sub1 = Employee::query()->create([
        'user_id' => $sub1User->id,
        'emp_num' => 'EMP-003',
        'designation' => 'Production Lead',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $sub2User = SalesCrmUser::query()->create([
        'name' => 'David Lee',
        'email' => 'david.lee@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $sub2 = Employee::query()->create([
        'user_id' => $sub2User->id,
        'emp_num' => 'EMP-004',
        'designation' => 'Quality Lead',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    // Map relationships in cm_employee_managers
    // Manager reports to Director
    DB::connection('salescrm')->table('employee_managers')->insert([
        ['employee_id' => $manager->id, 'manager_id' => $director->id],
        // Subordinates report to Manager
        ['employee_id' => $sub1->id, 'manager_id' => $manager->id],
        ['employee_id' => $sub2->id, 'manager_id' => $manager->id],
    ]);

    $token = $managerUser->createToken('test')->plainTextToken;

    // Fetch Middle Manager details
    $response = $this->withToken($token)->getJson("/api/v1/employees/{$manager->id}");

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.employee.id', $manager->id)
        ->assertJsonPath('data.employee.name', 'Jane Doe')
        ->assertJsonPath('data.employee.designation', 'Operations Manager')
        // Top level (Director) is an array with 1 item
        ->assertJsonCount(1, 'data.employee.top_level')
        ->assertJsonPath('data.employee.top_level.0.id', $director->id)
        ->assertJsonPath('data.employee.top_level.0.name', 'John Smith')
        ->assertJsonPath('data.employee.top_level.0.designation', 'Managing Director')
        // Low level (Subordinates) is an array with 2 items
        ->assertJsonCount(2, 'data.employee.low_level')
        ->assertJsonPath('data.employee.low_level.0.id', $sub1->id)
        ->assertJsonPath('data.employee.low_level.0.name', 'Sarah Wilson')
        ->assertJsonPath('data.employee.low_level.1.id', $sub2->id)
        ->assertJsonPath('data.employee.low_level.1.name', 'David Lee');
});

test('employee with multiple managers returns array of all managers in top_level', function () {
    $mgr1User = SalesCrmUser::query()->create([
        'name' => 'Manager One',
        'email' => 'mgr1@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $mgr1 = Employee::query()->create([
        'user_id' => $mgr1User->id,
        'emp_num' => 'EMP-101',
        'designation' => 'Technical Manager',
        'division' => 'Engineering',
        'status' => 1,
        'has_report' => true,
    ]);

    $mgr2User = SalesCrmUser::query()->create([
        'name' => 'Manager Two',
        'email' => 'mgr2@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $mgr2 = Employee::query()->create([
        'user_id' => $mgr2User->id,
        'emp_num' => 'EMP-102',
        'designation' => 'Product Manager',
        'division' => 'Product',
        'status' => 1,
        'has_report' => true,
    ]);

    $empUser = SalesCrmUser::query()->create([
        'name' => 'Team Engineer',
        'email' => 'engineer@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $empUser->id,
        'emp_num' => 'EMP-103',
        'designation' => 'Software Engineer',
        'division' => 'Engineering',
        'status' => 1,
        'has_report' => false,
    ]);

    // Employee reports to both Manager 1 and Manager 2
    DB::connection('salescrm')->table('employee_managers')->insert([
        ['employee_id' => $employee->id, 'manager_id' => $mgr1->id],
        ['employee_id' => $employee->id, 'manager_id' => $mgr2->id],
    ]);

    $token = $empUser->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson("/api/v1/employees/{$employee->id}");

    $response->assertOk()
        ->assertJsonCount(2, 'data.employee.top_level')
        ->assertJsonCount(0, 'data.employee.low_level');

    $managerIds = collect($response->json('data.employee.top_level'))->pluck('id')->all();
    expect($managerIds)->toContain($mgr1->id, $mgr2->id);
});

test('top level manager with no managers returns empty top_level array', function () {
    $directorUser = SalesCrmUser::query()->create([
        'name' => 'CEO',
        'email' => 'ceo@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $director = Employee::query()->create([
        'user_id' => $directorUser->id,
        'emp_num' => 'EMP-CEO',
        'designation' => 'CEO',
        'division' => 'Board',
        'status' => 1,
        'has_report' => false,
    ]);

    $token = $directorUser->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson("/api/v1/employees/{$director->id}");

    $response->assertOk()
        ->assertJsonCount(0, 'data.employee.top_level')
        ->assertJsonCount(0, 'data.employee.low_level');
});

test('employees me endpoint returns current authenticated employee details with hierarchy', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Current User',
        'email' => 'current@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-ME',
        'designation' => 'Senior Developer',
        'division' => 'Engineering',
        'status' => 1,
        'has_report' => false,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/employees/me');

    $response->assertOk()
        ->assertJsonPath('data.employee.id', $employee->id)
        ->assertJsonPath('data.employee.name', 'Current User')
        ->assertJsonIsArray('data.employee.top_level')
        ->assertJsonIsArray('data.employee.low_level');
});

test('non existent employee id returns 404', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Test User',
        'email' => 'test404@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/employees/999999');

    $response->assertNotFound();
});

test('reporting relations return explicit department assigned against the relationship', function () {
    $salesDeptId = DB::connection('salescrm')->table('departments')->insertGetId([
        'name' => 'Sales & Business',
        'code' => 'SALES',
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $mgrUser = SalesCrmUser::query()->create([
        'name' => 'Sales Head',
        'email' => 'sales.head@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $manager = Employee::query()->create([
        'user_id' => $mgrUser->id,
        'emp_num' => 'EMP-S01',
        'designation' => 'Sales Director',
        'division' => 'sd',
        'status' => 1,
        'has_report' => true,
    ]);

    $empUser = SalesCrmUser::query()->create([
        'name' => 'Sales Executive',
        'email' => 'sales.exec@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $empUser->id,
        'emp_num' => 'EMP-S02',
        'designation' => 'Sales Executive',
        'division' => 'sd',
        'status' => 1,
        'has_report' => false,
    ]);

    // Assign reporting relation with explicit department_id
    DB::connection('salescrm')->table('employee_managers')->insert([
        'employee_id' => $employee->id,
        'manager_id' => $manager->id,
        'department_id' => $salesDeptId,
    ]);

    $token = $empUser->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson("/api/v1/employees/{$employee->id}");

    $response->assertOk()
        ->assertJsonPath('data.employee.top_level.0.id', $manager->id)
        ->assertJsonPath('data.employee.top_level.0.department_id', $salesDeptId)
        ->assertJsonPath('data.employee.top_level.0.department.id', $salesDeptId)
        ->assertJsonPath('data.employee.top_level.0.department.name', 'Sales & Business')
        ->assertJsonPath('data.employee.top_level.0.department.code', 'SALES');
});

test('employee with reporting relations in different departments returns respective departments', function () {
    $salesDeptId = DB::connection('salescrm')->table('departments')->insertGetId([
        'name' => 'Sales',
        'code' => 'SLS',
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $opsDeptId = DB::connection('salescrm')->table('departments')->insertGetId([
        'name' => 'Operations',
        'code' => 'OPS',
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $salesMgrUser = SalesCrmUser::query()->create([
        'name' => 'Sales Manager',
        'email' => 'sales.mgr@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $salesMgr = Employee::query()->create([
        'user_id' => $salesMgrUser->id,
        'emp_num' => 'EMP-SM1',
        'designation' => 'Sales Manager',
        'division' => 'sd',
        'status' => 1,
        'has_report' => true,
    ]);

    $opsMgrUser = SalesCrmUser::query()->create([
        'name' => 'Operations Manager',
        'email' => 'ops.mgr@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $opsMgr = Employee::query()->create([
        'user_id' => $opsMgrUser->id,
        'emp_num' => 'EMP-OM1',
        'designation' => 'Operations Head',
        'division' => 'ops',
        'status' => 1,
        'has_report' => true,
    ]);

    $empUser = SalesCrmUser::query()->create([
        'name' => 'Cross Functional Employee',
        'email' => 'cross@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $empUser->id,
        'emp_num' => 'EMP-CF1',
        'designation' => 'Operations & Sales Coordinator',
        'division' => 'sd',
        'status' => 1,
        'has_report' => false,
    ]);

    // Reports to Sales Manager for Sales, and Operations Manager for Operations
    DB::connection('salescrm')->table('employee_managers')->insert([
        ['employee_id' => $employee->id, 'manager_id' => $salesMgr->id, 'department_id' => $salesDeptId],
        ['employee_id' => $employee->id, 'manager_id' => $opsMgr->id, 'department_id' => $opsDeptId],
    ]);

    $token = $empUser->createToken('test')->plainTextToken;

    // 1. Fetch all managers
    $response = $this->withToken($token)->getJson("/api/v1/employees/{$employee->id}");

    $response->assertOk()
        ->assertJsonCount(2, 'data.employee.top_level');

    $topLevel = collect($response->json('data.employee.top_level'))->keyBy('id');
    expect($topLevel->get($salesMgr->id)['department']['name'])->toBe('Sales');
    expect($topLevel->get($opsMgr->id)['department']['name'])->toBe('Operations');

    // 2. Filter by sales department only
    $salesFiltered = $this->withToken($token)->getJson("/api/v1/employees/{$employee->id}?department_id={$salesDeptId}");
    $salesFiltered->assertOk()
        ->assertJsonCount(1, 'data.employee.top_level')
        ->assertJsonPath('data.employee.top_level.0.id', $salesMgr->id);

    // 3. Filter by operations department only
    $opsFiltered = $this->withToken($token)->getJson("/api/v1/employees/{$employee->id}?department_id={$opsDeptId}");
    $opsFiltered->assertOk()
        ->assertJsonCount(1, 'data.employee.top_level')
        ->assertJsonPath('data.employee.top_level.0.id', $opsMgr->id);
});
