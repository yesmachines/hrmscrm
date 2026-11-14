<?php

use App\Models\Reward;
use App\Models\RewardCategory;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use App\Support\SalesCrmRoles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::connection('salescrm')->table('personal_access_tokens')->delete();

    // Ensure HR role exists
    if (! DB::connection('salescrm')->table('roles')->where('name', 'hr')->exists()) {
        DB::connection('salescrm')->table('roles')->insert([
            'name' => 'hr',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    if (! DB::connection('salescrm')->table('roles')->where('name', 'finance')->exists()) {
        DB::connection('salescrm')->table('roles')->insert([
            'name' => 'finance',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Create HR User
    $this->hrUser = SalesCrmUser::query()->create([
        'name' => 'HR Manager',
        'email' => 'hr.reward.'.uniqid().'@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    SalesCrmRoles::assignRoles($this->hrUser->id, ['hr']);
    $this->hrEmployee = Employee::query()->create([
        'user_id' => $this->hrUser->id,
        'emp_num' => 'HR-'.uniqid(),
        'designation' => 'HR Specialist',
        'division' => 'Corporate',
        'status' => 1,
    ]);
    $this->hrToken = $this->hrUser->createToken('hr_test')->plainTextToken;

    // Create a regular employee user
    $this->regularUser = SalesCrmUser::query()->create([
        'name' => 'Standard Employee',
        'email' => 'emp.reward.'.uniqid().'@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $this->regularEmployee = Employee::query()->create([
        'user_id' => $this->regularUser->id,
        'emp_num' => 'EMP-'.uniqid(),
        'designation' => 'Software Engineer',
        'division' => 'Engineering',
        'status' => 1,
    ]);
    $this->regularToken = $this->regularUser->createToken('emp_test')->plainTextToken;
});

test('hr user can list all rewards across employees via hr endpoint', function () {
    $category = RewardCategory::factory()->create();

    Reward::factory()->create([
        'category_id' => $category->id,
        'submitted_by' => $this->regularEmployee->id,
        'amount' => 3000,
        'status' => 'approved',
    ]);

    Reward::factory()->create([
        'category_id' => $category->id,
        'submitted_by' => $this->hrEmployee->id,
        'amount' => 5000,
        'status' => 'pending',
    ]);

    $response = $this->withToken($this->hrToken)
        ->getJson('/api/v1/hr/rewards');

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.total', 2);
});

test('hr user can add an existing approved reward for an employee via hr endpoint', function () {
    $category = RewardCategory::factory()->create(['reward_name' => 'Spot Bonus', 'short_code' => 'SPOT']);

    $payload = [
        'employee_id' => $this->regularEmployee->id,
        'category_id' => $category->id,
        'amount' => 4500,
        'currency' => 'AED',
        'status' => 'approved',
        'submitted_date' => '2026-01-10',
        'description' => 'Spot bonus for critical client bug resolution during outage.',
        'comments' => 'Awarded by CTO and approved.',
    ];

    $response = $this->withToken($this->hrToken)
        ->postJson('/api/v1/hr/rewards', $payload);

    $response->assertCreated()
        ->assertJsonPath('statusCode', 201)
        ->assertJsonPath('data.submitted_by', $this->regularEmployee->id)
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.amount', '4500.00');

    $this->assertDatabaseHas('rewards', [
        'category_id' => $category->id,
        'submitted_by' => $this->regularEmployee->id,
        'amount' => 4500,
        'currency' => 'AED',
        'status' => 'approved',
    ]);

    $reward = Reward::latest('id')->first();
    $this->assertDatabaseHas('reward_approval_statuses', [
        'reward_id' => $reward->id,
        'status' => 'approved',
        'done_by' => $this->hrUser->id,
        'comments' => 'Awarded by CTO and approved.',
    ]);
});

test('hr user can add an existing paid reward for an employee via hr endpoint', function () {
    $category = RewardCategory::factory()->create(['reward_name' => 'Annual Award', 'short_code' => 'ANNUAL']);

    $payload = [
        'employee_id' => $this->regularEmployee->id,
        'category_id' => $category->id,
        'amount' => 15000,
        'currency' => 'AED',
        'status' => 'paid',
        'submitted_date' => '2025-11-20',
        'description' => 'Existing Annual Best Contributor Award 2025.',
        'comments' => 'Disbursed in November payroll.',
    ];

    $response = $this->withToken($this->hrToken)
        ->postJson('/api/v1/hr/rewards', $payload);

    $response->assertCreated()
        ->assertJsonPath('statusCode', 201)
        ->assertJsonPath('data.status', 'paid');

    $reward = Reward::latest('id')->first();
    expect($reward->status)->toBe('paid');
    expect($reward->submitted_date->format('Y-m-d'))->toBe('2025-11-20');

    $this->assertDatabaseHas('reward_approval_statuses', [
        'reward_id' => $reward->id,
        'status' => 'paid',
        'done_by' => $this->hrUser->id,
        'comments' => 'Disbursed in November payroll.',
    ]);
});

test('hr user can view specific reward details via hr endpoint', function () {
    $category = RewardCategory::factory()->create();

    $reward = Reward::factory()->create([
        'category_id' => $category->id,
        'submitted_by' => $this->regularEmployee->id,
        'amount' => 2500,
        'status' => 'pending',
    ]);

    $response = $this->withToken($this->hrToken)
        ->getJson("/api/v1/hr/rewards/{$reward->id}");

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.id', $reward->id)
        ->assertJsonPath('data.submitted_by', $this->regularEmployee->id);
});

test('regular employee without hr role cannot access hr rewards endpoints', function () {
    $response = $this->withToken($this->regularToken)
        ->getJson('/api/v1/hr/rewards');

    $response->assertForbidden();
});

test('regular employee can view their own rewards and apply via standard endpoint', function () {
    $category = RewardCategory::factory()->create();

    // Reward for regular employee
    Reward::factory()->create([
        'category_id' => $category->id,
        'submitted_by' => $this->regularEmployee->id,
        'status' => 'pending',
    ]);

    // Reward for another employee
    Reward::factory()->create([
        'category_id' => $category->id,
        'submitted_by' => $this->hrEmployee->id,
        'status' => 'pending',
    ]);

    $response = $this->withToken($this->regularToken)
        ->getJson('/api/v1/rewards');

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.total', 1); // Only their own!
});

test('finance user can mark reward as paid via hr status endpoint', function () {
    $financeUser = SalesCrmUser::query()->create([
        'name' => 'Finance Manager',
        'email' => 'fin.reward.'.uniqid().'@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    SalesCrmRoles::assignRoles($financeUser->id, ['finance']);
    $financeToken = $financeUser->createToken('fin_test')->plainTextToken;

    $category = RewardCategory::factory()->create();
    $reward = Reward::factory()->create([
        'category_id' => $category->id,
        'submitted_by' => $this->regularEmployee->id,
        'amount' => 8000,
        'status' => 'approved',
    ]);

    $response = $this->withToken($financeToken)
        ->postJson("/api/v1/hr/rewards/{$reward->id}/status", [
            'status' => 'paid',
            'comments' => 'Paid via direct bank deposit ref #883921.',
        ]);

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.status', 'paid');

    expect($reward->fresh()->status)->toBe('paid');
    $this->assertDatabaseHas('reward_approval_statuses', [
        'reward_id' => $reward->id,
        'status' => 'paid',
        'done_by' => $financeUser->id,
        'comments' => 'Paid via direct bank deposit ref #883921.',
    ]);
});

test('hr user can reject reward with reason via hr status endpoint', function () {
    $category = RewardCategory::factory()->create();
    $reward = Reward::factory()->create([
        'category_id' => $category->id,
        'submitted_by' => $this->regularEmployee->id,
        'status' => 'pending',
    ]);

    $response = $this->withToken($this->hrToken)
        ->postJson("/api/v1/hr/rewards/{$reward->id}/status", [
            'status' => 'rejected',
            'comments' => 'Insufficient documentation provided.',
        ]);

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.status', 'rejected');

    expect($reward->fresh()->status)->toBe('rejected');
});

test('hr user can download bulk import csv or excel template', function () {
    $responseExcel = $this->withToken($this->hrToken)
        ->get('/api/v1/hr/rewards/template?format=excel');

    $responseExcel->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
    expect($responseExcel->getContent())->toContain('Workbook')
        ->toContain('Reward_Import_Template');

    $responseCsv = $this->withToken($this->hrToken)
        ->get('/api/v1/hr/rewards/template?format=csv');

    $responseCsv->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $responseCsv->getContent();
    expect($content)->toContain('employee_identifier,category,amount,currency,award_date,status,description,comments');
});

test('hr user can bulk import rewards via csv', function () {
    $category = RewardCategory::factory()->create([
        'reward_name' => 'Monthly Star',
        'status' => 1,
    ]);

    $csvContent = "employee_identifier,category,amount,currency,award_date,status,description,comments\n"
        ."{$this->regularEmployee->emp_num},Monthly Star,1200,AED,2026-11-10,approved,Great job on delivery,Batch import test\n";

    $file = UploadedFile::fake()->createWithContent('rewards.csv', $csvContent);

    $response = $this->withToken($this->hrToken)
        ->postJson('/api/v1/hr/rewards/bulk-import', [
            'file' => $file,
        ]);

    if ($response->status() !== 200) {
        fwrite(STDERR, json_encode($response->json()).PHP_EOL);
    }

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.imported_count', 1)
        ->assertJsonPath('data.failed_count', 0);

    $this->assertDatabaseHas('rewards', [
        'submitted_by' => $this->regularEmployee->id,
        'amount' => 1200,
        'status' => 'approved',
        'description' => 'Great job on delivery',
    ]);
});

test('employee can fetch their reward summary stats for mobile dashboard', function () {
    $category = RewardCategory::factory()->create();

    // Reward this month
    Reward::factory()->create([
        'category_id' => $category->id,
        'submitted_by' => $this->regularEmployee->id,
        'amount' => 500,
        'status' => 'approved',
        'submitted_date' => now()->startOfMonth()->addDays(2),
    ]);

    // Reward earlier this year
    Reward::factory()->create([
        'category_id' => $category->id,
        'submitted_by' => $this->regularEmployee->id,
        'amount' => 1000,
        'status' => 'paid',
        'submitted_date' => now()->startOfYear()->addDays(10),
    ]);

    // Reward from previous year (should count only towards lifetime total)
    Reward::factory()->create([
        'category_id' => $category->id,
        'submitted_by' => $this->regularEmployee->id,
        'amount' => 800,
        'status' => 'paid',
        'submitted_date' => now()->subYears(2)->toDateString(),
    ]);

    // Pending nomination (should not be counted in earned amounts)
    Reward::factory()->create([
        'category_id' => $category->id,
        'submitted_by' => $this->regularEmployee->id,
        'amount' => 300,
        'status' => 'pending',
        'submitted_date' => now()->toDateString(),
    ]);

    $response = $this->withToken($this->regularToken)
        ->getJson('/api/v1/rewards/summary');

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.selected_month.amount', 500)
        ->assertJsonPath('data.selected_month.count', 1)
        ->assertJsonPath('data.selected_year.amount', 1500)
        ->assertJsonPath('data.selected_year.count', 2)
        ->assertJsonPath('data.total_rewards.amount', 2300)
        ->assertJsonPath('data.total_rewards.count', 3)
        ->assertJsonPath('data.pending_rewards_count', 1);

    // Test with specific year filter
    $responseFiltered = $this->withToken($this->regularToken)
        ->getJson('/api/v1/rewards/summary?year='.(now()->year - 2));

    $responseFiltered->assertOk()
        ->assertJsonPath('data.selected_year.amount', 800)
        ->assertJsonPath('data.selected_year.count', 1);

    // Test filtering employee personal list endpoint
    $responseList = $this->withToken($this->regularToken)
        ->getJson('/api/v1/rewards?status=approved');

    $responseList->assertOk()
        ->assertJsonPath('data.total', 1);

    // Test unified dashboard endpoint with rewards key and listing
    $responseDashboard = $this->withToken($this->regularToken)
        ->getJson('/api/v1/dashboard');

    $responseDashboard->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.employee.emp_num', $this->regularEmployee->emp_num)
        ->assertJsonPath('data.rewards.this_month.amount', 500)
        ->assertJsonPath('data.rewards.this_year.amount', 1500)
        ->assertJsonPath('data.rewards.total_rewards.amount', 2300)
        ->assertJsonPath('data.rewards.total_rewards.count', 3)
        ->assertJsonCount(4, 'data.rewards.list');
});
