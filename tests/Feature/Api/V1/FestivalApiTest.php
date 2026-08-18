<?php

use App\Models\EmployeeProfile;
use App\Models\Festival;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::connection('salescrm')->table('personal_access_tokens')->delete();
    DB::connection('salescrm')->table('employees')->delete();
    DB::connection('salescrm')->table('users')->delete();
    DB::table('festival_nationality')->delete();
    DB::table('festivals')->delete();
});

test('leaves festivals endpoint returns holidays matching employee nationality', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Test Employee',
        'email' => 'employee.festival@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-TEST-FEST',
        'designation' => 'Dev',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $countryId = DB::connection('salescrm')->table('countries')->where('name', 'like', 'India%')->value('id') ?? 24;

    EmployeeProfile::query()->create([
        'employee_id' => $employee->id,
        'nationality' => 'India',
        'home_country' => null,
    ]);

    $festival = Festival::query()->create([
        'name' => 'Onam',
        'type' => 'holiday',
        'shortcode' => 'ONA2026',
        'is_active' => true,
        'start_date' => '2026-08-25',
        'end_date' => '2026-08-25',
    ]);

    DB::table('festival_nationality')->insert([
        'festival_id' => $festival->id,
        'country_id' => $countryId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/leaves/festivals');

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.holidays.0.name', 'Onam');
});
