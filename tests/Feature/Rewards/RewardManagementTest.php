<?php

use App\Models\Reward;
use App\Models\RewardCategory;
use App\Models\SalesCrm\Employee;
use Illuminate\Http\UploadedFile;

test('authenticated hr or admin users can view rewards page with summary metrics', function () {
    $user = createHrmsLoginUser('admin');
    $this->actingAs($user);

    $category = RewardCategory::factory()->create(['reward_name' => 'Spot Award', 'short_code' => 'SPOT']);

    Reward::factory()->create([
        'category_id' => $category->id,
        'amount' => 5000,
        'status' => 'pending',
    ]);

    Reward::factory()->create([
        'category_id' => $category->id,
        'amount' => 10000,
        'status' => 'paid',
        'updated_at' => now(),
    ]);

    $response = $this->get(route('rewards.index'));
    $response->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('rewards/index')
        ->has('summary')
        ->where('summary.total_claims', 2)
        ->where('summary.pending_count', 1)
        ->where('summary.paid_this_month', 10000)
    );
});

test('authenticated hr or admin users can view reward categories page', function () {
    $user = createHrmsLoginUser('hr');
    $this->actingAs($user);

    $response = $this->get(route('reward-categories.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('rewards/categories'));
});

test('admin can create a new reward category', function () {
    $user = createHrmsLoginUser('admin');
    $this->actingAs($user);

    $response = $this->post(route('reward-categories.store'), [
        'reward_name' => 'Innovation Champion',
        'short_code' => 'innov',
        'status' => 1,
        'details' => 'Awarded for breakthrough technical solutions.',
    ]);

    $response->assertRedirect(route('reward-categories.index'));

    $this->assertDatabaseHas('reward_categories', [
        'reward_name' => 'Innovation Champion',
        'short_code' => 'INNOV',
        'status' => 1,
    ]);
});

test('user can submit a reward claim and sequential claim number is generated', function () {
    $user = createHrmsLoginUser('admin');
    $this->actingAs($user);

    $category = RewardCategory::factory()->create(['reward_name' => 'Star of the Month', 'short_code' => 'STAR']);

    $year = now()->year;

    $response = $this->post(route('rewards.store'), [
        'category_id' => $category->id,
        'amount' => 7500,
        'description' => 'Successfully delivered the Q3 core migration project ahead of schedule.',
    ]);

    $response->assertRedirect(route('rewards.index'));

    $this->assertDatabaseHas('rewards', [
        'category_id' => $category->id,
        'amount' => 7500,
        'status' => 'pending',
    ]);

    $reward = Reward::latest('id')->first();
    expect($reward->claim_no)->toMatch("/^REW-{$year}-\d{4}$/");

    // Check that initial pending status was recorded in reward_approval_statuses
    $this->assertDatabaseHas('reward_approval_statuses', [
        'reward_id' => $reward->id,
        'status' => 'pending',
        'done_by' => $user->id,
    ]);
});

test('finance user can approve a pending claim and workflow status is tracked', function () {
    $finance = createHrmsLoginUser('finance');
    $this->actingAs($finance);

    $category = RewardCategory::factory()->create();
    $reward = Reward::factory()->create([
        'category_id' => $category->id,
        'status' => 'pending',
    ]);

    $response = $this->post(route('rewards.update-status', $reward), [
        'status' => 'approved',
        'comments' => 'Approved by Director. Forwarded to Accounts for disbursement.',
    ]);

    $response->assertRedirect();

    expect($reward->fresh()->status)->toBe('approved');

    $this->assertDatabaseHas('reward_approval_statuses', [
        'reward_id' => $reward->id,
        'status' => 'approved',
        'done_by' => $finance->id,
        'comments' => 'Approved by Director. Forwarded to Accounts for disbursement.',
    ]);
});

test('finance user can mark an approved claim as paid', function () {
    $finance = createHrmsLoginUser('finance');
    $this->actingAs($finance);

    $category = RewardCategory::factory()->create();
    $reward = Reward::factory()->create([
        'category_id' => $category->id,
        'status' => 'approved',
    ]);

    $response = $this->post(route('rewards.update-status', $reward), [
        'status' => 'paid',
        'comments' => 'Payment processed via NEFT reference #123456.',
    ]);

    $response->assertRedirect();
    expect($reward->fresh()->status)->toBe('paid');

    $this->assertDatabaseHas('reward_approval_statuses', [
        'reward_id' => $reward->id,
        'status' => 'paid',
        'done_by' => $finance->id,
    ]);
});

test('non-finance users cannot approve or mark claims as paid', function () {
    $admin = createHrmsLoginUser('admin');
    $this->actingAs($admin);

    $category = RewardCategory::factory()->create();
    $reward = Reward::factory()->create([
        'category_id' => $category->id,
        'status' => 'pending',
    ]);

    $response = $this->post(route('rewards.update-status', $reward), [
        'status' => 'approved',
        'comments' => 'Approved by Admin.',
    ]);

    $response->assertForbidden();
    expect($reward->fresh()->status)->toBe('pending');
});

test('rejecting a claim requires comments', function () {
    $admin = createHrmsLoginUser('admin');
    $this->actingAs($admin);

    $category = RewardCategory::factory()->create();
    $reward = Reward::factory()->create([
        'category_id' => $category->id,
        'status' => 'pending',
    ]);

    $response = $this->post(route('rewards.update-status', $reward), [
        'status' => 'rejected',
        'comments' => '',
    ]);

    $response->assertSessionHasErrors('comments');
    expect($reward->fresh()->status)->toBe('pending');
});

test('user can download or print claim form voucher', function () {
    $user = createHrmsLoginUser('admin');
    $this->actingAs($user);

    $category = RewardCategory::factory()->create(['reward_name' => 'Excellence Award', 'short_code' => 'EXC']);
    $reward = Reward::factory()->create([
        'category_id' => $category->id,
        'amount' => 15000,
        'status' => 'approved',
        'description' => 'Remarkable contribution to company revenue growth.',
    ]);

    $response = $this->get(route('rewards.download-form', $reward));
    $response->assertOk();
    $response->assertSeeText($reward->claim_no);
    $response->assertSeeText('Excellence Award');
    $response->assertSeeText('15,000.00');
});

test('rewards list can be filtered by status, category, and date', function () {
    $user = createHrmsLoginUser('admin');
    $this->actingAs($user);

    $categoryA = RewardCategory::factory()->create(['reward_name' => 'Alpha Reward', 'short_code' => 'ALP']);
    $categoryB = RewardCategory::factory()->create(['reward_name' => 'Beta Reward', 'short_code' => 'BET']);

    Reward::factory()->create([
        'category_id' => $categoryA->id,
        'status' => 'pending',
        'submitted_date' => now()->toDateString(),
    ]);

    Reward::factory()->create([
        'category_id' => $categoryB->id,
        'status' => 'paid',
        'submitted_date' => now()->subMonths(2)->toDateString(),
    ]);

    $responseStatus = $this->get(route('rewards.index', ['status' => 'pending']));
    $responseStatus->assertOk();

    $responseCat = $this->get(route('rewards.index', ['category_id' => $categoryA->id]));
    $responseCat->assertOk();

    $responseDate = $this->get(route('rewards.index', ['date_filter' => 'today']));
    $responseDate->assertOk();
});

test('hr or finance user can add an existing approved reward for an employee via web', function () {
    $hrUser = createHrmsLoginUser('hr');
    $this->actingAs($hrUser);

    $employeeUser = createHrmsLoginUser('admin');
    $employee = Employee::query()->create([
        'user_id' => $employeeUser->id,
        'emp_num' => 'EMP-'.uniqid(),
        'designation' => 'Senior Developer',
        'division' => 'Corporate',
        'status' => 1,
    ]);

    $category = RewardCategory::factory()->create(['reward_name' => 'Annual Performance', 'short_code' => 'PERF']);

    $pastDate = '2025-12-15';
    $response = $this->post(route('rewards.store'), [
        'category_id' => $category->id,
        'submitted_by' => $employee->id,
        'amount' => 12000,
        'currency' => 'AED',
        'status' => 'approved',
        'submitted_date' => $pastDate,
        'comments' => 'Annual recognition awarded at townhall.',
        'description' => 'Exemplary project delivery and mentoring across teams.',
    ]);

    $response->assertRedirect(route('rewards.index'));

    $this->assertDatabaseHas('rewards', [
        'category_id' => $category->id,
        'submitted_by' => $employee->id,
        'amount' => 12000,
        'currency' => 'AED',
        'status' => 'approved',
    ]);

    $reward = Reward::latest('id')->first();
    expect($reward->submitted_date->format('Y-m-d'))->toBe($pastDate);

    $this->assertDatabaseHas('reward_approval_statuses', [
        'reward_id' => $reward->id,
        'status' => 'approved',
        'done_by' => $hrUser->id,
        'comments' => 'Annual recognition awarded at townhall.',
    ]);
});

test('finance user can directly record an existing paid reward for an employee', function () {
    $financeUser = createHrmsLoginUser('finance');
    $this->actingAs($financeUser);

    $employeeUser = createHrmsLoginUser('admin');
    $employee = Employee::query()->create([
        'user_id' => $employeeUser->id,
        'emp_num' => 'EMP-'.uniqid(),
        'designation' => 'Sales Executive',
        'division' => 'Sales',
        'status' => 1,
    ]);

    $category = RewardCategory::factory()->create(['reward_name' => 'Sales Milestone', 'short_code' => 'SALE']);

    $response = $this->post(route('rewards.store'), [
        'category_id' => $category->id,
        'submitted_by' => $employee->id,
        'amount' => 5000,
        'currency' => 'AED',
        'status' => 'paid',
        'comments' => 'Paid via payroll bank transfer.',
        'description' => 'Achieved 150% of annual quota.',
    ]);

    $response->assertRedirect(route('rewards.index'));

    $reward = Reward::latest('id')->first();
    expect($reward->status)->toBe('paid');

    $this->assertDatabaseHas('reward_approval_statuses', [
        'reward_id' => $reward->id,
        'status' => 'paid',
        'done_by' => $financeUser->id,
        'comments' => 'Paid via payroll bank transfer.',
    ]);
});

test('hr user can download bulk import csv and excel template via web', function () {
    $hrUser = createHrmsLoginUser('hr');
    $this->actingAs($hrUser);

    // Default or explicit excel format
    $responseExcel = $this->get(route('rewards.template', ['format' => 'excel']));
    $responseExcel->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
    expect($responseExcel->getContent())->toContain('Workbook')
        ->toContain('Reward_Import_Template');

    // CSV format
    $responseCsv = $this->get(route('rewards.template', ['format' => 'csv']));
    $responseCsv->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($responseCsv->getContent())->toContain('employee_identifier,category,amount,currency,award_date,status,description,comments');
});

test('hr user can bulk import rewards via csv file upload on web', function () {
    $hrUser = createHrmsLoginUser('hr');
    $this->actingAs($hrUser);

    $employeeUser = createHrmsLoginUser('admin');
    $employee = Employee::query()->create([
        'user_id' => $employeeUser->id,
        'emp_num' => 'EMP-'.uniqid(),
        'designation' => 'Support Engineer',
        'division' => 'Operations',
        'status' => 1,
    ]);

    $category = RewardCategory::factory()->create([
        'reward_name' => 'Quarterly Hero',
        'status' => 1,
    ]);

    $csvContent = "employee_identifier,category,amount,currency,award_date,status,description,comments\n"
        ."{$employee->emp_num},Quarterly Hero,1800,AED,2026-11-01,approved,Resolved 500 tickets with 99% CSAT,Quarterly review\n";

    $file = UploadedFile::fake()->createWithContent('rewards_batch.csv', $csvContent);

    $response = $this->post(route('rewards.bulk-import'), [
        'file' => $file,
    ]);

    $response->assertRedirect(route('rewards.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('rewards', [
        'submitted_by' => $employee->id,
        'amount' => 1800,
        'status' => 'approved',
        'description' => 'Resolved 500 tickets with 99% CSAT',
    ]);
});
