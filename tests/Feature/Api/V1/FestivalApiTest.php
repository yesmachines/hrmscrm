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

test('leaves festivals endpoint filters by numeric month query parameter', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Month Test User',
        'email' => 'month.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-MONTH-TEST',
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

    $augustHoliday = Festival::query()->create([
        'name' => 'Independence Day',
        'type' => 'holiday',
        'shortcode' => 'IND2026',
        'is_active' => true,
        'start_date' => '2026-08-15',
        'end_date' => '2026-08-15',
    ]);

    $octoberFestival = Festival::query()->create([
        'name' => 'Diwali',
        'type' => 'festival',
        'shortcode' => 'DIW2026',
        'is_active' => true,
        'start_date' => '2026-10-20',
        'end_date' => '2026-10-20',
    ]);

    DB::table('festival_nationality')->insert([
        ['festival_id' => $augustHoliday->id, 'country_id' => $countryId, 'created_at' => now(), 'updated_at' => now()],
        ['festival_id' => $octoberFestival->id, 'country_id' => $countryId, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $token = $user->createToken('test')->plainTextToken;

    // Filter by August (month=8)
    $responseAug = $this->withToken($token)->getJson('/api/v1/leaves/festivals?month=8');
    $responseAug->assertOk()
        ->assertJsonCount(1, 'data.holidays')
        ->assertJsonCount(0, 'data.festivals')
        ->assertJsonPath('data.holidays.0.name', 'Independence Day');

    // Filter by October (month=10)
    $responseOct = $this->withToken($token)->getJson('/api/v1/leaves/festivals?month=10');
    $responseOct->assertOk()
        ->assertJsonCount(0, 'data.holidays')
        ->assertJsonCount(1, 'data.festivals')
        ->assertJsonPath('data.festivals.0.name', 'Diwali');

    // Filter by December (month=12) - should be empty
    $responseDec = $this->withToken($token)->getJson('/api/v1/leaves/festivals?month=12');
    $responseDec->assertOk()
        ->assertJsonCount(0, 'data.holidays')
        ->assertJsonCount(0, 'data.festivals');
});

test('leaves festivals endpoint filters by month in YYYY-MM format and year parameter', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Date Format User',
        'email' => 'format.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-FMT-TEST',
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

    $holiday2026 = Festival::query()->create([
        'name' => 'Holiday 2026',
        'type' => 'holiday',
        'shortcode' => 'HOL2026',
        'is_active' => true,
        'start_date' => '2026-08-15',
        'end_date' => '2026-08-15',
    ]);

    DB::table('festival_nationality')->insert([
        'festival_id' => $holiday2026->id,
        'country_id' => $countryId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $token = $user->createToken('test')->plainTextToken;

    // YYYY-MM match
    $responseMatch = $this->withToken($token)->getJson('/api/v1/leaves/festivals?month=2026-08');
    $responseMatch->assertOk()
        ->assertJsonCount(1, 'data.holidays')
        ->assertJsonPath('data.holidays.0.name', 'Holiday 2026');

    // YYYY-MM mismatch year
    $responseNoMatch = $this->withToken($token)->getJson('/api/v1/leaves/festivals?month=2025-08');
    $responseNoMatch->assertOk()
        ->assertJsonCount(0, 'data.holidays');

    // Using leaves/holidays alias route
    $responseAlias = $this->withToken($token)->getJson('/api/v1/leaves/holidays?month=2026-08');
    $responseAlias->assertOk()
        ->assertJsonCount(1, 'data.holidays')
        ->assertJsonPath('data.holidays.0.name', 'Holiday 2026');
});

test('leaves index endpoint returns holidays and festivals when month is passed', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Leave Index User',
        'email' => 'leave.index@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-IDX-TEST',
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

    $holiday = Festival::query()->create([
        'name' => 'Independence Day',
        'type' => 'holiday',
        'shortcode' => 'IND2026-IDX',
        'is_active' => true,
        'start_date' => '2026-08-15',
        'end_date' => '2026-08-15',
    ]);

    DB::table('festival_nationality')->insert([
        'festival_id' => $holiday->id,
        'country_id' => $countryId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/leaves?month=8');
    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonStructure([
            'data' => [
                'leave_requests',
                'pagination',
                'holidays',
                'festivals',
            ],
        ])
        ->assertJsonPath('data.holidays.0.name', 'Independence Day');
});
