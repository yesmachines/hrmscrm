<?php

use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use App\Models\Visit;
use App\Models\VisitApproval;
use Laravel\Sanctum\Sanctum;

test('employee can submit a visit request via API', function () {
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Procurement Officer',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    Sanctum::actingAs($salesUser);

    $payload = [
        'total_visitors' => 2,
        'visitor_details' => [
            ['name' => 'John Smith', 'designation' => 'Technical Director'],
            ['name' => 'Alice Green', 'designation' => 'Project Lead'],
        ],
        'company' => 'Acme Supplies Ltd',
        'contact_no' => '+1234567890',
        'email' => 'john.smith@acmesupplies.com',
        'purpose' => 'Quarterly vendor product review and contract discussion.',
        'location' => 'Main Conference Room B',
        'expected_start_date' => '2026-09-25 10:00:00',
        'expected_end_date' => '2026-09-25 12:30:00',
        'required_approvals' => 'Department Head approval confirmed via email.',
    ];

    $response = $this->postJson(route('api.v1.visits.store'), $payload);

    $response->assertStatus(201)
        ->assertJsonPath('statusCode', 201)
        ->assertJsonPath('data.visit.company', 'Acme Supplies Ltd')
        ->assertJsonPath('data.visit.status', 'pending');

    $visit = Visit::query()->where('company', 'Acme Supplies Ltd')->first();
    expect($visit)->not->toBeNull()
        ->and($visit->total_visitors)->toBe(2)
        ->and($visit->status)->toBe('pending')
        ->and($visit->created_by)->toBe($employee->id)
        ->and(count($visit->visitor_details))->toBe(2);
});

test('employee can view their visit history via API', function () {
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Project Manager',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);

    Visit::query()->create([
        'total_visitors' => 1,
        'visitor_details' => [['name' => 'Bob White', 'designation' => 'Auditor']],
        'company' => 'Global Audit Corp',
        'contact_no' => '+9876543210',
        'email' => 'bob@auditcorp.com',
        'purpose' => 'Compliance verification visit',
        'location' => 'Board Room',
        'expected_start_date' => '2026-09-28 09:30:00',
        'created_by' => $employee->id,
        'status' => 'pending',
    ]);

    Sanctum::actingAs($salesUser);

    $response = $this->getJson(route('api.v1.visits.index'));

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonFragment(['company' => 'Global Audit Corp']);
});

test('hr can approve a visit request and provide instructions via API', function () {
    $hrUser = createHrmsLoginUser('hr');

    $salesUser = SalesCrmUser::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Client Lead',
        'division' => 'Sales',
        'status' => 1,
        'has_report' => false,
    ]);

    $visit = Visit::query()->create([
        'total_visitors' => 1,
        'visitor_details' => [['name' => 'Emma Watson', 'designation' => 'Client Partner']],
        'company' => 'Apex Partners',
        'contact_no' => '+1122334455',
        'email' => 'emma@apexpartners.com',
        'purpose' => 'New partnership kickoff',
        'location' => 'Meeting Room 3',
        'expected_start_date' => '2026-09-29 14:00:00',
        'created_by' => $employee->id,
        'status' => 'pending',
    ]);

    Sanctum::actingAs($hrUser);

    $response = $this->postJson(route('api.v1.visits.approve', $visit), [
        'instructions' => 'Please issue temporary visitor RFID card at reception desk and escort to Room 3.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.visit.status', 'approved');

    $visit->refresh();
    expect($visit->status)->toBe('approved');

    $approval = VisitApproval::query()->where('visit_id', $visit->id)->first();
    expect($approval)->not->toBeNull()
        ->and($approval->approver_id)->toBe($hrUser->id)
        ->and($approval->instructions)->toContain('RFID card');
});

test('hr can reject a visit request with reason via API', function () {
    $hrUser = createHrmsLoginUser('hr');

    $salesUser = SalesCrmUser::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Account Exec',
        'division' => 'Sales',
        'status' => 1,
        'has_report' => false,
    ]);

    $visit = Visit::query()->create([
        'total_visitors' => 1,
        'visitor_details' => [['name' => 'Suspicious Visitor', 'designation' => 'Unknown']],
        'company' => 'Unknown Corp',
        'contact_no' => '+0000000000',
        'email' => 'unknown@unknown.com',
        'purpose' => 'Unscheduled product demo',
        'location' => 'Main Lobby',
        'expected_start_date' => '2026-09-30 11:00:00',
        'created_by' => $employee->id,
        'status' => 'pending',
    ]);

    Sanctum::actingAs($hrUser);

    $response = $this->postJson(route('api.v1.visits.reject', $visit), [
        'rejected_reason' => 'Vendor registration and security clearance not completed prior to meeting request.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.visit.status', 'rejected');

    $visit->refresh();
    expect($visit->status)->toBe('rejected');

    $approval = VisitApproval::query()->where('visit_id', $visit->id)->first();
    expect($approval)->not->toBeNull()
        ->and($approval->approver_id)->toBe($hrUser->id)
        ->and($approval->rejected_reason)->toContain('security clearance');
});

test('hr can review and approve visit request via CRM web', function () {
    $hrUser = createHrmsLoginUser('hr');

    $salesUser = SalesCrmUser::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Logistics Manager',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $visit = Visit::query()->create([
        'total_visitors' => 1,
        'visitor_details' => [['name' => 'David Miller', 'designation' => 'Supplier Rep']],
        'company' => 'Fast Freight Logistics',
        'contact_no' => '+5566778899',
        'email' => 'david@fastfreight.com',
        'purpose' => 'Warehouse logistics coordination',
        'location' => 'Facility Building B',
        'expected_start_date' => '2026-10-01 10:00:00',
        'created_by' => $employee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($hrUser)
        ->withoutVite()
        ->get(route('visits.index'))
        ->assertOk();

    $this->actingAs($hrUser)
        ->withoutVite()
        ->get(route('visits.show', $visit))
        ->assertOk();

    $this->actingAs($hrUser)
        ->post(route('visits.approve', $visit), [
            'instructions' => 'Safety vest and helmet required upon entry.',
        ])
        ->assertRedirect();

    $visit->refresh();
    expect($visit->status)->toBe('approved');
});

test('employee or hr can filter visits by today, tomorrow, and day after tomorrow', function () {
    $hrUser = createHrmsLoginUser('hr');

    $salesUser = SalesCrmUser::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Operations Lead',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    // Today visit
    Visit::query()->create([
        'total_visitors' => 1,
        'visitor_details' => [['name' => 'Today Visitor', 'designation' => 'Consultant']],
        'company' => 'Today Corp',
        'contact_no' => '+1111111111',
        'email' => 'today@corp.com',
        'purpose' => 'Meeting today',
        'location' => 'Room 1',
        'expected_start_date' => now()->setTime(10, 0, 0)->toDateTimeString(),
        'created_by' => $employee->id,
        'status' => 'pending',
    ]);

    // Tomorrow visit
    Visit::query()->create([
        'total_visitors' => 1,
        'visitor_details' => [['name' => 'Tomorrow Visitor', 'designation' => 'Inspector']],
        'company' => 'Tomorrow Ltd',
        'contact_no' => '+2222222222',
        'email' => 'tomorrow@ltd.com',
        'purpose' => 'Inspection tomorrow',
        'location' => 'Room 2',
        'expected_start_date' => now()->addDay()->setTime(11, 0, 0)->toDateTimeString(),
        'created_by' => $employee->id,
        'status' => 'pending',
    ]);

    // Day after tomorrow visit
    Visit::query()->create([
        'total_visitors' => 1,
        'visitor_details' => [['name' => 'Day After Tomorrow Visitor', 'designation' => 'Auditor']],
        'company' => 'DayAfterTomorrow Co',
        'contact_no' => '+3333333333',
        'email' => 'dayafter@co.com',
        'purpose' => 'Audit day after tomorrow',
        'location' => 'Room 3',
        'expected_start_date' => now()->addDays(2)->setTime(14, 0, 0)->toDateTimeString(),
        'created_by' => $employee->id,
        'status' => 'pending',
    ]);

    Sanctum::actingAs($hrUser);

    // 1. Filter by today
    $responseToday = $this->getJson(route('api.v1.visits.index', ['all' => 'true', 'date_filter' => 'today']));
    $responseToday->assertOk()
        ->assertJsonCount(1, 'data.visits')
        ->assertJsonFragment(['company' => 'Today Corp']);

    // 2. Filter by current_day
    $responseCurrent = $this->getJson(route('api.v1.visits.index', ['all' => 'true', 'date' => 'current_day']));
    $responseCurrent->assertOk()
        ->assertJsonCount(1, 'data.visits')
        ->assertJsonFragment(['company' => 'Today Corp']);

    // 3. Filter by tomorrow
    $responseTomorrow = $this->getJson(route('api.v1.visits.index', ['all' => 'true', 'date_filter' => 'tomorrow']));
    $responseTomorrow->assertOk()
        ->assertJsonCount(1, 'data.visits')
        ->assertJsonFragment(['company' => 'Tomorrow Ltd']);

    // 4. Filter by day_after_tomorrow
    $responseDayAfter = $this->getJson(route('api.v1.visits.index', ['all' => 'true', 'filter' => 'day_after_tomorrow']));
    $responseDayAfter->assertOk()
        ->assertJsonCount(1, 'data.visits')
        ->assertJsonFragment(['company' => 'DayAfterTomorrow Co']);
});

test('employee or hr can filter visits by before_today and after_today', function () {
    $hrUser = createHrmsLoginUser('hr');

    $salesUser = SalesCrmUser::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Operations Lead',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    // Past visit (3 days ago)
    Visit::query()->create([
        'total_visitors' => 1,
        'visitor_details' => [['name' => 'Past Visitor', 'designation' => 'Vendor']],
        'company' => 'Past Corp',
        'contact_no' => '+4444444444',
        'email' => 'past@corp.com',
        'purpose' => 'Past meeting',
        'location' => 'Room Past',
        'expected_start_date' => now()->subDays(3)->setTime(10, 0, 0)->toDateTimeString(),
        'expected_end_date' => now()->subDays(3)->setTime(12, 0, 0)->toDateTimeString(),
        'created_by' => $employee->id,
        'status' => 'completed',
    ]);

    // Today visit
    Visit::query()->create([
        'total_visitors' => 1,
        'visitor_details' => [['name' => 'Today Visitor', 'designation' => 'Consultant']],
        'company' => 'Today Corp',
        'contact_no' => '+1111111111',
        'email' => 'today@corp.com',
        'purpose' => 'Meeting today',
        'location' => 'Room 1',
        'expected_start_date' => now()->setTime(10, 0, 0)->toDateTimeString(),
        'created_by' => $employee->id,
        'status' => 'pending',
    ]);

    // Future visit (starts tomorrow)
    Visit::query()->create([
        'total_visitors' => 1,
        'visitor_details' => [['name' => 'Future Visitor', 'designation' => 'Auditor']],
        'company' => 'Future Corp',
        'contact_no' => '+5555555555',
        'email' => 'future@corp.com',
        'purpose' => 'Future audit',
        'location' => 'Room Future',
        'expected_start_date' => now()->addDays(5)->setTime(14, 0, 0)->toDateTimeString(),
        'created_by' => $employee->id,
        'status' => 'pending',
    ]);

    Sanctum::actingAs($hrUser);

    // 1. Filter by before_today
    $responseBefore = $this->getJson(route('api.v1.visits.index', ['all' => 'true', 'date_filter' => 'before_today']));
    $responseBefore->assertOk()
        ->assertJsonCount(1, 'data.visits')
        ->assertJsonFragment(['company' => 'Past Corp']);

    // 2. Filter by after_today
    $responseAfter = $this->getJson(route('api.v1.visits.index', ['all' => 'true', 'date_filter' => 'after_today']));
    $responseAfter->assertOk()
        ->assertJsonCount(1, 'data.visits')
        ->assertJsonFragment(['company' => 'Future Corp']);
});
