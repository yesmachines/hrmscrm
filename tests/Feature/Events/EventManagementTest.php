<?php

use App\Models\Event;
use App\Models\EventType;

test('authenticated hr or admin users can view events page', function () {
    $user = createHrmsLoginUser('admin');
    $this->actingAs($user);

    $response = $this->get(route('events.index'));
    $response->assertOk();
});

test('authenticated hr or admin users can view event types page', function () {
    $user = createHrmsLoginUser('hr');
    $this->actingAs($user);

    $response = $this->get(route('event-types.index'));
    $response->assertOk();
});

test('admin can create a new event', function () {
    $user = createHrmsLoginUser('admin');
    $this->actingAs($user);

    $type = EventType::factory()->create(['event_name' => 'Design Sprint']);

    $payload = [
        'title' => 'Q3 Product Strategy Kickoff',
        'event_type_id' => $type->id,
        'start_datetime' => now()->addDays(2)->format('Y-m-d H:i:s'),
        'end_datetime' => now()->addDays(2)->addHours(3)->format('Y-m-d H:i:s'),
        'status' => 'published',
        'show_dashboard' => 1,
        'description' => 'Detailed walkthrough of Q3 initiatives.',
    ];

    $response = $this->post(route('events.store'), $payload);
    $response->assertRedirect(route('events.index'));

    $this->assertDatabaseHas('events', [
        'title' => 'Q3 Product Strategy Kickoff',
        'event_type_id' => $type->id,
        'status' => 'published',
    ]);
});

test('admin can update an event', function () {
    $user = createHrmsLoginUser('admin');
    $this->actingAs($user);

    $event = Event::factory()->create([
        'title' => 'Initial Title',
    ]);

    $response = $this->put(route('events.update', $event), [
        'title' => 'Updated Event Title',
        'event_type_id' => $event->event_type_id,
        'start_datetime' => $event->start_datetime->format('Y-m-d H:i:s'),
        'status' => 'published',
        'show_dashboard' => 1,
    ]);

    $response->assertRedirect(route('events.index'));
    expect($event->fresh()->title)->toBe('Updated Event Title');
});

test('admin can delete an event and it is soft deleted', function () {
    $user = createHrmsLoginUser('admin');
    $this->actingAs($user);

    $event = Event::factory()->create();

    $response = $this->delete(route('events.destroy', $event));
    $response->assertRedirect(route('events.index'));

    expect($event->fresh()->trashed())->toBeTrue();
});

test('admin can create an event type', function () {
    $user = createHrmsLoginUser('admin');
    $this->actingAs($user);

    $response = $this->post(route('event-types.store'), [
        'event_code' => 'TOWN_HALL',
        'event_name' => 'All Hands Town Hall',
        'event_source' => 'manual',
        'priority' => 15,
        'status' => 1,
    ]);

    $response->assertRedirect(route('event-types.index'));

    $this->assertDatabaseHas('event_types', [
        'event_code' => 'TOWN_HALL',
        'event_name' => 'All Hands Town Hall',
    ]);
});

test('system event types cannot be deleted', function () {
    $user = createHrmsLoginUser('admin');
    $this->actingAs($user);

    $type = EventType::factory()->create([
        'event_source' => 'system',
    ]);

    $response = $this->delete(route('event-types.destroy', $type));
    $response->assertSessionHas('error');

    $this->assertDatabaseHas('event_types', ['id' => $type->id]);
});

test('users can filter events by month and year on web events page', function () {
    $user = createHrmsLoginUser('admin');
    $this->actingAs($user);

    $type = EventType::factory()->create();

    Event::factory()->create([
        'event_type_id' => $type->id,
        'title' => 'September Gathering',
        'start_datetime' => '2026-09-10 10:00:00',
        'end_datetime' => '2026-09-10 12:00:00',
        'status' => 'published',
    ]);

    Event::factory()->create([
        'event_type_id' => $type->id,
        'title' => 'December Festive Dinner',
        'start_datetime' => '2026-12-15 19:00:00',
        'end_datetime' => '2026-12-15 22:00:00',
        'status' => 'published',
    ]);

    $response = $this->get(route('events.index', ['date_filter' => '2026-09']));
    $response->assertOk();

    $responseMonth = $this->get(route('events.index', ['month' => 9, 'year' => 2026]));
    $responseMonth->assertOk();
});
