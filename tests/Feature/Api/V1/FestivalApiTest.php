<?php

use App\Models\EmployeeProfile;
use App\Models\Festival;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::connection('salescrm')->table('personal_access_tokens')->delete();
    DB::connection('salescrm')->table('employees')->delete();
    DB::connection('salescrm')->table('users')->delete();
    DB::table('leave_requests')->delete();
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

test('leaves holidays endpoint with month parameter returns holidays and employee applied leaves', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Calendar User',
        'email' => 'calendar.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-CAL-01',
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
        'shortcode' => 'IND2026-AUG',
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

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL',
        'is_paid' => true,
        'status' => 1,
    ]);

    // August leave (should be included)
    $augustLeave = LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-12',
        'total_days' => 3,
        'status' => 'approved',
        'remarks' => 'Summer vacation',
    ]);

    // October leave (should be excluded)
    LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-06',
        'total_days' => 2,
        'status' => 'applied',
        'remarks' => 'Autumn break',
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/leaves/holidays?month=8&year=2026');

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.month', 8)
        ->assertJsonPath('data.year', 2026)
        ->assertJsonCount(1, 'data.holidays')
        ->assertJsonPath('data.holidays.0.name', 'Independence Day')
        ->assertJsonCount(1, 'data.leaves')
        ->assertJsonPath('data.leaves.0.id', $augustLeave->id)
        ->assertJsonPath('data.leaves.0.start_date', '2026-08-10')
        ->assertJsonPath('data.leaves.0.end_date', '2026-08-12')
        ->assertJsonPath('data.leaves.0.status', 'approved')
        ->assertJsonPath('data.leaves.0.leave_type_name', 'Annual Leave')
        ->assertJsonPath('data.leaves.0.total_days', 3);

    // Also test with only ?month=8 (without year)
    $responseOnlyMonth = $this->withToken($token)->getJson('/api/v1/leaves/holidays?month=8');
    $responseOnlyMonth->assertOk()
        ->assertJsonCount(1, 'data.leaves')
        ->assertJsonPath('data.leaves.0.id', $augustLeave->id);
});
