<?php

use App\Models\EmployeeProfile;
use App\Models\Event;
use App\Models\EventType;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use App\Services\SystemEventSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::connection('salescrm')->table('personal_access_tokens')->delete();
    DB::connection('salescrm')->table('employees')->delete();
    DB::connection('salescrm')->table('users')->where('email', 'like', 'sync.test%')->delete();
    Event::query()->delete();
    EmployeeProfile::query()->delete();
    LeaveRequest::query()->delete();
});

function createTestEmployee(array $attributes = []): Employee
{
    $user = SalesCrmUser::query()->create([
        'name' => $attributes['name'] ?? 'Test Employee '.uniqid(),
        'email' => 'sync.test.'.uniqid().'@example.com',
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
    ]);

    return Employee::query()->create(array_merge([
        'user_id' => $user->id,
        'emp_num' => 'EMP'.rand(1000, 9999),
        'designation' => 'Software Engineer',
        'division' => 'Technology',
        'status' => 1,
        'joining_date' => '2024-05-15',
    ], array_diff_key($attributes, ['name' => true])));
}

test('employee completed 2 years gets 2nd work anniversary event', function () {
    $employee = createTestEmployee([
        'name' => 'John Doe',
        'joining_date' => '2024-05-15',
    ]);

    $service = app(SystemEventSyncService::class);
    $service->syncWorkAnniversaries(2026);

    $anniversaryType = EventType::query()->where('event_code', 'WORK_ANNIVERSARY')->first();

    $this->assertDatabaseHas('events', [
        'employee_id' => $employee->id,
        'event_type_id' => $anniversaryType->id,
        'title' => "John Doe's 2nd Work Anniversary",
        'start_datetime' => '2026-05-15 09:00:00',
    ]);
});

test('employee joined less than 1 year or joining next week does NOT get work anniversary', function () {
    // Joined 3 months ago in 2026
    $recentEmployee = createTestEmployee([
        'name' => 'Recent Hire',
        'joining_date' => '2026-06-01',
    ]);

    // Joining in the future
    $futureEmployee = createTestEmployee([
        'name' => 'Future Joiner',
        'joining_date' => now()->addWeek()->format('Y-m-d'),
    ]);

    $service = app(SystemEventSyncService::class);
    $service->syncWorkAnniversaries(2026);

    $anniversaryType = EventType::query()->where('event_code', 'WORK_ANNIVERSARY')->first();

    $this->assertDatabaseMissing('events', [
        'employee_id' => $recentEmployee->id,
        'event_type_id' => $anniversaryType->id,
    ]);

    $this->assertDatabaseMissing('events', [
        'employee_id' => $futureEmployee->id,
        'event_type_id' => $anniversaryType->id,
    ]);
});

test('employee profile date of birth generates birthday event', function () {
    $employee = createTestEmployee(['name' => 'Alice Birthday']);

    EmployeeProfile::query()->create([
        'employee_id' => $employee->id,
        'dob_personal' => '1995-09-28',
    ]);

    $service = app(SystemEventSyncService::class);
    $service->syncBirthdays(2026);

    $birthdayType = EventType::query()->where('event_code', 'BIRTHDAY')->first();

    $this->assertDatabaseHas('events', [
        'employee_id' => $employee->id,
        'event_type_id' => $birthdayType->id,
        'title' => "Alice Birthday's Birthday",
        'start_datetime' => '2026-09-28 09:00:00',
    ]);
});

test('new joiner in target year generates new joiner event on start date', function () {
    $newHire = createTestEmployee([
        'name' => 'Bob Starter',
        'designation' => 'DevOps Architect',
        'joining_date' => '2026-10-01',
    ]);

    $service = app(SystemEventSyncService::class);
    $service->syncNewJoiners(2026);

    $newJoinerType = EventType::query()->where('event_code', 'NEW_JOINER')->first();

    $this->assertDatabaseHas('events', [
        'employee_id' => $newHire->id,
        'event_type_id' => $newJoinerType->id,
        'title' => 'New Joiner: Bob Starter (DevOps Architect)',
        'start_datetime' => '2026-10-01 09:00:00',
    ]);
});

test('approved leave request generates employee on leave event spanning dates', function () {
    $employee = createTestEmployee(['name' => 'Sarah Vacation']);

    $leaveType = LeaveType::query()->firstOrCreate(
        ['leave_name' => 'Annual Leave'],
        ['code' => 'AL', 'status' => 1]
    );

    LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-11-10 00:00:00',
        'end_date' => '2026-11-14 00:00:00',
        'total_days' => 5,
        'status' => 'approved',
        'remarks' => 'Family vacation',
    ]);

    // Pending leave should be ignored
    LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-12-01 00:00:00',
        'end_date' => '2026-12-02 00:00:00',
        'total_days' => 2,
        'status' => 'applied',
    ]);

    $service = app(SystemEventSyncService::class);
    $service->syncApprovedLeaves(2026);

    $leaveEventType = EventType::query()->where('event_code', 'EMPLOYEE_ON_LEAVE')->first();

    $this->assertDatabaseHas('events', [
        'employee_id' => $employee->id,
        'event_type_id' => $leaveEventType->id,
        'title' => 'Sarah Vacation on Leave (Annual Leave)',
        'start_datetime' => '2026-11-10 09:00:00',
        'end_datetime' => '2026-11-14 18:00:00',
    ]);

    // Verify only 1 event was created (pending leave excluded)
    expect(Event::query()->where('event_type_id', $leaveEventType->id)->count())->toBe(1);
});

test('syncing is idempotent and does not create duplicate events', function () {
    $employee = createTestEmployee([
        'name' => 'Idempotent User',
        'joining_date' => '2022-04-10',
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $employee->id,
        'dob_personal' => '1990-07-20',
    ]);

    $service = app(SystemEventSyncService::class);

    // Run sync twice
    $service->syncAll(2026);
    $service->syncAll(2026);

    $anniversaryType = EventType::query()->where('event_code', 'WORK_ANNIVERSARY')->first();
    $birthdayType = EventType::query()->where('event_code', 'BIRTHDAY')->first();

    expect(Event::query()->where('event_type_id', $anniversaryType->id)->where('employee_id', $employee->id)->count())->toBe(1);
    expect(Event::query()->where('event_type_id', $birthdayType->id)->where('employee_id', $employee->id)->count())->toBe(1);
});

test('artisan command events:sync-system executes successfully', function () {
    $this->artisan('events:sync-system --year=2026')
        ->expectsOutputToContain('Starting system events synchronization for year: 2026')
        ->expectsOutputToContain('System events synchronization complete')
        ->assertExitCode(0);
});

test('leave request observer automatically creates and removes calendar event on approval and cancellation', function () {
    $employee = createTestEmployee(['name' => 'Realtime Leave User']);

    $leaveType = LeaveType::query()->firstOrCreate(
        ['leave_name' => 'Casual Leave'],
        ['code' => 'CL', 'status' => 1]
    );

    $leaveEventType = EventType::query()->firstOrCreate(
        ['event_code' => 'EMPLOYEE_ON_LEAVE'],
        ['event_name' => 'Employees on Leave', 'event_source' => 'system', 'priority' => 40, 'status' => 1]
    );

    // 1. Creating as 'applied' should NOT create an event
    $leave = LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-10-15 00:00:00',
        'end_date' => '2026-10-16 00:00:00',
        'total_days' => 2,
        'status' => 'applied',
    ]);

    expect(Event::query()->where('event_type_id', $leaveEventType->id)->where('employee_id', $employee->id)->count())->toBe(0);

    // 2. Updating status to 'approved' should INSTANTLY create event
    $leave->update(['status' => 'approved']);

    $this->assertDatabaseHas('events', [
        'employee_id' => $employee->id,
        'event_type_id' => $leaveEventType->id,
        'title' => 'Realtime Leave User on Leave (Casual Leave)',
        'start_datetime' => '2026-10-15 09:00:00',
        'end_datetime' => '2026-10-16 18:00:00',
    ]);

    // 3. Updating status to 'cancelled' should INSTANTLY remove event
    $leave->update(['status' => 'cancelled']);

    expect(Event::query()->where('event_type_id', $leaveEventType->id)->where('employee_id', $employee->id)->count())->toBe(0);
});

test('pruning clears past years system events while preserving manual company events', function () {
    $employee = createTestEmployee(['name' => 'Past Year User']);

    $systemType = EventType::query()->firstOrCreate(
        ['event_code' => 'BIRTHDAY'],
        ['event_name' => 'Birthday', 'event_source' => 'system', 'status' => 1]
    );

    $manualType = EventType::query()->firstOrCreate(
        ['event_code' => 'MEETING'],
        ['event_name' => 'Meetings', 'event_source' => 'manual', 'status' => 1]
    );

    // Past year system event (2024) - should be pruned
    $pastSystemEvent = Event::query()->create([
        'employee_id' => $employee->id,
        'event_type_id' => $systemType->id,
        'title' => 'Past Year 2024 Birthday',
        'start_datetime' => '2024-05-15 09:00:00',
        'status' => 'published',
    ]);

    // Past year manual event (2024) - should be PRESERVED
    $pastManualEvent = Event::query()->create([
        'event_type_id' => $manualType->id,
        'title' => 'Important Annual Meeting 2024',
        'start_datetime' => '2024-06-20 10:00:00',
        'status' => 'published',
    ]);

    // Current year system event (2026) - should be PRESERVED
    $currentSystemEvent = Event::query()->create([
        'employee_id' => $employee->id,
        'event_type_id' => $systemType->id,
        'title' => 'Current Year 2026 Birthday',
        'start_datetime' => '2026-05-15 09:00:00',
        'status' => 'published',
    ]);

    $service = app(SystemEventSyncService::class);
    $prunedCount = $service->prunePastSystemEvents(2026);

    expect($prunedCount)->toBe(1);

    // Assert past system event is gone
    $this->assertDatabaseMissing('events', ['id' => $pastSystemEvent->id]);

    // Assert past manual event is still there!
    $this->assertDatabaseHas('events', ['id' => $pastManualEvent->id]);

    // Assert current year event is still there!
    $this->assertDatabaseHas('events', ['id' => $currentSystemEvent->id]);
});
