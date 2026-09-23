<?php

use App\Models\Event;
use App\Models\EventType;
use App\Models\SalesCrm\User as SalesCrmUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::connection('salescrm')->table('personal_access_tokens')->delete();
    DB::connection('salescrm')->table('users')->where('email', 'like', 'events.api%')->delete();

    $this->apiUser = SalesCrmUser::query()->create([
        'name' => 'Events API User',
        'email' => 'events.api.'.uniqid().'@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $this->token = $this->apiUser->createToken('test')->plainTextToken;
});

test('api user can list events with pagination', function () {
    $type = EventType::factory()->create();
    Event::factory()->count(3)->create(['event_type_id' => $type->id]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/events');

    $response->assertOk()
        ->assertJsonStructure([
            'statusCode',
            'message',
            'data' => [
                'events',
                'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
            ],
        ]);
});

test('api user can fetch active event types', function () {
    EventType::factory()->create(['status' => 1, 'event_name' => 'Active Workshop']);
    EventType::factory()->create(['status' => 0, 'event_name' => 'Inactive Type']);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/events/types');

    $response->assertOk()
        ->assertJsonPath('statusCode', 200);

    $names = collect($response->json('data'))->pluck('event_name');
    expect($names)->toContain('Active Workshop')
        ->not->toContain('Inactive Type');
});

test('api user can fetch todays events for dashboard', function () {
    $type = EventType::factory()->create();

    Event::factory()->create([
        'event_type_id' => $type->id,
        'title' => 'Important Lunch',
        'start_datetime' => now()->setTime(12, 0),
        'show_dashboard' => true,
        'status' => 'published',
    ]);

    Event::factory()->create([
        'event_type_id' => $type->id,
        'title' => 'Future Meeting',
        'start_datetime' => now()->addDays(5),
        'show_dashboard' => true,
        'status' => 'published',
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/events/today');

    $response->assertOk()
        ->assertJsonPath('statusCode', 200);

    $titles = collect($response->json('data.events'))->pluck('title');
    expect($titles)->toContain('Important Lunch')
        ->not->toContain('Future Meeting');
});

test('api user can create an event via api', function () {
    $type = EventType::factory()->create();

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/events', [
            'event_type_id' => $type->id,
            'title' => 'API Created Workshop',
            'start_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
            'status' => 'published',
            'show_dashboard' => true,
            'description' => 'Test event created through REST API.',
        ]);

    $response->assertCreated()
        ->assertJsonPath('statusCode', 201)
        ->assertJsonPath('data.title', 'API Created Workshop');

    $this->assertDatabaseHas('events', [
        'title' => 'API Created Workshop',
        'event_type_id' => $type->id,
    ]);
});

test('api user can view single event details', function () {
    $event = Event::factory()->create(['title' => 'Single Event Test']);

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/events/{$event->id}");

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.title', 'Single Event Test');
});

test('api user can filter events by specific month and year via date_filter (YYYY-MM)', function () {
    $type = EventType::factory()->create();

    Event::factory()->create([
        'event_type_id' => $type->id,
        'title' => 'September Mid Event',
        'start_datetime' => '2026-09-15 10:00:00',
        'status' => 'published',
    ]);

    Event::factory()->create([
        'event_type_id' => $type->id,
        'title' => 'October Event',
        'start_datetime' => '2026-10-05 10:00:00',
        'status' => 'published',
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/events?date_filter=2026-09');

    $response->assertOk();
    $titles = collect($response->json('data.events'))->pluck('title');
    expect($titles)->toContain('September Mid Event')
        ->not->toContain('October Event');
});

test('api user can filter events by explicit month and year query parameters', function () {
    $type = EventType::factory()->create();

    Event::factory()->create([
        'event_type_id' => $type->id,
        'title' => 'September Conference',
        'start_datetime' => '2026-09-20 09:00:00',
        'end_datetime' => '2026-09-20 17:00:00',
        'status' => 'published',
    ]);

    Event::factory()->create([
        'event_type_id' => $type->id,
        'title' => 'August Retreat',
        'start_datetime' => '2026-08-10 09:00:00',
        'end_datetime' => '2026-08-10 17:00:00',
        'status' => 'published',
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/events?month=9&year=2026');

    $response->assertOk();
    $titles = collect($response->json('data.events'))->pluck('title');
    expect($titles)->toContain('September Conference')
        ->not->toContain('August Retreat');
});

test('api user can filter events by month name with year or month_year parameter', function () {
    $type = EventType::factory()->create();

    Event::factory()->create([
        'event_type_id' => $type->id,
        'title' => 'Nov Tech Summit',
        'start_datetime' => '2026-11-12 11:00:00',
        'status' => 'published',
    ]);

    Event::factory()->create([
        'event_type_id' => $type->id,
        'title' => 'Dec Year End Party',
        'start_datetime' => '2026-12-20 18:00:00',
        'status' => 'published',
    ]);

    $response1 = $this->withToken($this->token)
        ->getJson('/api/v1/events?date_filter=November 2026');

    $response1->assertOk();
    $titles1 = collect($response1->json('data.events'))->pluck('title');
    expect($titles1)->toContain('Nov Tech Summit')
        ->not->toContain('Dec Year End Party');

    $response2 = $this->withToken($this->token)
        ->getJson('/api/v1/events?month_year=2026-11');

    $response2->assertOk();
    $titles2 = collect($response2->json('data.events'))->pluck('title');
    expect($titles2)->toContain('Nov Tech Summit')
        ->not->toContain('Dec Year End Party');
});

test('api user can filter events using relative month keywords like this_month', function () {
    $type = EventType::factory()->create();

    Event::factory()->create([
        'event_type_id' => $type->id,
        'title' => 'Current Month Hackathon',
        'start_datetime' => now()->startOfMonth()->addDays(5),
        'status' => 'published',
    ]);

    Event::factory()->create([
        'event_type_id' => $type->id,
        'title' => 'Far Future Event',
        'start_datetime' => now()->addMonths(3),
        'status' => 'published',
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/events?date_filter=this_month');

    $response->assertOk();
    $titles = collect($response->json('data.events'))->pluck('title');
    expect($titles)->toContain('Current Month Hackathon')
        ->not->toContain('Far Future Event');
});
