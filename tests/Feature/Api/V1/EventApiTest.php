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
