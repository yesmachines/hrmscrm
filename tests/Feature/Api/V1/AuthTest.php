<?php

use App\Models\EmployeeProfile;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\PersonalAccessToken;
use App\Models\SalesCrm\User as SalesCrmUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::connection('salescrm')->table('personal_access_tokens')->delete();
    DB::connection('salescrm')->table('employees')->delete();
    DB::connection('salescrm')->table('users')->delete();
});

test('temporary admin setup endpoint is no longer available', function () {
    $this->get('/dev/admin')->assertNotFound();
});

test('api login returns a sanctum token for valid credentials', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Mobile User',
        'email' => 'mobile.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'mobile.user@example.com',
        'password' => 'Password123!',
        'device_name' => 'pixel-8',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'statusCode',
            'message',
            'data' => [
                'token',
                'token_type',
                'user' => ['id', 'name', 'email'],
            ],
        ])
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'mobile.user@example.com');

    expect(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(1);
});

test('api login rejects invalid credentials', function () {
    SalesCrmUser::query()->create([
        'name' => 'Mobile User',
        'email' => 'mobile.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $this->postJson('/api/v1/login', [
        'email' => 'mobile.user@example.com',
        'password' => 'wrong-password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('authenticated api user can fetch their profile', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Mobile User',
        'email' => 'mobile.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-ME-001',
        'employee_code' => 'CODE-001',
        'phone' => '+971501234567',
        'designation' => 'Senior Developer',
        'employment_status' => 'fulltime',
        'joining_date' => '2024-01-15',
        'division' => 'Engineering',
        'status' => 1,
        'has_report' => true,
    ]);
    EmployeeProfile::query()->create([
        'employee_id' => $employee->id,
        'gender' => 'F',
        'dob_personal' => '1990-04-12',
        'marital_status' => 'married',
        'nationality' => 'Canadian',
        'religion' => 'None',
        'blood_group' => 'O+',
        'personal_email' => 'personal@example.com',
        'personal_mobile' => '+971509876543',
        'address_uae' => 'Dubai',
        'emergency_contact_name' => 'Emergency Contact',
        'emergency_relation' => 'Spouse',
        'emergency_mobile' => '+971509876544',
        'home_country' => 1,
        'address_home' => 'Toronto',
        'home_mobile' => '+14165550100',
        'home_emergency_name' => 'Home Contact',
        'home_emergency_relation' => 'Parent',
        'home_emergency_mobile' => '+14165550101',
        'visa_type' => 'visa',
        'visa_from' => 'Employer',
        'dob_passport' => '2030-10-25',
        'total_experience' => 8.5,
        'highest_education' => 'Bachelors Degree',
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'mobile.user@example.com')
        ->assertJsonPath('data.name', 'Mobile User')
        ->assertJsonPath('data.employee.id', $employee->id)
        ->assertJsonPath('data.employee.emp_num', 'EMP-ME-001')
        ->assertJsonPath('data.employee.employee_code', 'CODE-001')
        ->assertJsonPath('data.employee.phone', '+971501234567')
        ->assertJsonPath('data.employee.designation', 'Senior Developer')
        ->assertJsonPath('data.employee.employment_status', 'fulltime')
        ->assertJsonPath('data.employee.joining_date', '2024-01-15')
        ->assertJsonPath('data.employee.division', 'Engineering')
        ->assertJsonPath('data.employee.profile.gender', 'F')
        ->assertJsonPath('data.employee.profile.dob_personal', '1990-04-12')
        ->assertJsonPath('data.employee.profile.marital_status', 'married')
        ->assertJsonPath('data.employee.profile.nationality', 'Canadian')
        ->assertJsonPath('data.employee.profile.religion', 'None')
        ->assertJsonPath('data.employee.profile.blood_group', 'O+')
        ->assertJsonPath('data.employee.profile.personal_email', 'personal@example.com')
        ->assertJsonPath('data.employee.profile.personal_mobile', '+971509876543')
        ->assertJsonPath('data.employee.profile.address_uae', 'Dubai')
        ->assertJsonPath('data.employee.profile.emergency_contact_name', 'Emergency Contact')
        ->assertJsonPath('data.employee.profile.emergency_relation', 'Spouse')
        ->assertJsonPath('data.employee.profile.emergency_mobile', '+971509876544')
        ->assertJsonPath('data.employee.profile.home_country', 1)
        ->assertJsonPath('data.employee.profile.address_home', 'Toronto')
        ->assertJsonPath('data.employee.profile.home_mobile', '+14165550100')
        ->assertJsonPath('data.employee.profile.home_emergency_name', 'Home Contact')
        ->assertJsonPath('data.employee.profile.home_emergency_relation', 'Parent')
        ->assertJsonPath('data.employee.profile.home_emergency_mobile', '+14165550101')
        ->assertJsonPath('data.employee.profile.visa_type', 'visa')
        ->assertJsonPath('data.employee.profile.visa_from', 'Employer')
        ->assertJsonPath('data.employee.profile.dob_passport', '2030-10-25')
        ->assertJsonPath('data.employee.profile.total_experience', 8.5)
        ->assertJsonPath('data.employee.profile.highest_education', 'Bachelors Degree')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.employee.password');
});

test('guests cannot access protected api routes', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

test('authenticated api user can logout and revoke the current token', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Mobile User',
        'email' => 'mobile.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logged out successfully.');

    expect(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(0);

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});
