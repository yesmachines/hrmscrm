<?php

use App\Models\SalesCrm\Department;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::connection('salescrm')->table('personal_access_tokens')->delete();
    DB::connection('salescrm')->table('employees')->delete();
    DB::connection('salescrm')->table('departments')->delete();
    DB::connection('salescrm')->table('users')->delete();
});

test('unauthenticated request to departments list returns 401', function () {
    $response = $this->getJson('/api/v1/departments');

    $response->assertUnauthorized();
});

test('authenticated user can list departments with pagination', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $token = $user->createToken('test')->plainTextToken;

    Department::query()->create([
        'name' => 'Human Resources',
        'code' => 'HR',
        'status' => 1,
    ]);

    Department::query()->create([
        'name' => 'Finance & Accounts',
        'code' => 'FIN',
        'status' => 1,
    ]);

    $response = $this->withToken($token)->getJson('/api/v1/departments');

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('message', 'Departments retrieved successfully.')
        ->assertJsonPath('data.pagination.total', 2)
        ->assertJsonCount(2, 'data.departments');
});

test('departments listing supports search by name and code', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Test User',
        'email' => 'test2@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $token = $user->createToken('test')->plainTextToken;

    Department::query()->create([
        'name' => 'Information Technology',
        'code' => 'IT',
        'status' => 1,
    ]);

    Department::query()->create([
        'name' => 'Marketing',
        'code' => 'MKT',
        'status' => 1,
    ]);

    // Search by code
    $response = $this->withToken($token)->getJson('/api/v1/departments?search=IT');
    $response->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.departments.0.name', 'Information Technology');

    // Search by name
    $response2 = $this->withToken($token)->getJson('/api/v1/departments?search=Marketing');
    $response2->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.departments.0.code', 'MKT');
});

test('departments listing supports all=true for unpaginated dropdown data', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Test User',
        'email' => 'test3@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $token = $user->createToken('test')->plainTextToken;

    Department::query()->create([
        'name' => 'Operations',
        'code' => 'OPS',
        'status' => 1,
    ]);

    $response = $this->withToken($token)->getJson('/api/v1/departments?all=true');

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.departments.0.name', 'Operations');
});

test('can retrieve single department details with employee count', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Test User',
        'email' => 'test4@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $dept = Department::query()->create([
        'name' => 'Logistics',
        'code' => 'LOG',
        'status' => 1,
    ]);

    Employee::query()->create([
        'user_id' => $user->id,
        'department_id' => $dept->id,
        'emp_num' => 'EMP-LOG-01',
        'designation' => 'Logistics Specialist',
        'division' => 'Operations',
        'status' => 1,
    ]);

    $response = $this->withToken($token)->getJson('/api/v1/departments/'.$dept->id);

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.department.id', $dept->id)
        ->assertJsonPath('data.department.name', 'Logistics')
        ->assertJsonPath('data.department.employees_count', 1);
});

test('request for non-existent department returns 404', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Test User',
        'email' => 'test5@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/departments/99999');

    $response->assertNotFound()
        ->assertJsonPath('statusCode', 404)
        ->assertJsonPath('message', 'Department not found.');
});
