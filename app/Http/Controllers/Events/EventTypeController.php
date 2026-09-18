<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\EventType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventTypeController extends Controller
{
    /**
     * Display a listing of event types.
     */
    public function index(): Response
    {
        $eventTypes = EventType::query()
            ->withCount('events')
            ->orderBy('priority')
            ->orderBy('event_name')
            ->get();

        return Inertia::render('events/event-types', [
            'eventTypes' => $eventTypes,
        ]);
    }

    /**
     * Store a newly created event type.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_code' => ['required', 'string', 'max:50', 'unique:event_types,event_code'],
            'event_name' => ['required', 'string', 'max:100'],
            'event_source' => ['required', Rule::in(['system', 'manual'])],
            'priority' => ['nullable', 'integer', 'min:0'],
            'icon_path' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'integer', Rule::in([0, 1])],
        ]);

        $validated['event_code'] = strtoupper(trim($validated['event_code']));
        $validated['priority'] = $validated['priority'] ?? 0;
        $validated['status'] = $validated['status'] ?? 1;

        EventType::query()->create($validated);

        return redirect()->route('event-types.index')->with('success', 'Event type created successfully.');
    }

    /**
     * Update the specified event type.
     */
    public function update(Request $request, EventType $eventType): RedirectResponse
    {
        $validated = $request->validate([
            'event_code' => ['required', 'string', 'max:50', Rule::unique('event_types', 'event_code')->ignore($eventType->id)],
            'event_name' => ['required', 'string', 'max:100'],
            'event_source' => ['required', Rule::in(['system', 'manual'])],
            'priority' => ['nullable', 'integer', 'min:0'],
            'icon_path' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'integer', Rule::in([0, 1])],
        ]);

        $validated['event_code'] = strtoupper(trim($validated['event_code']));
        $validated['priority'] = $validated['priority'] ?? 0;
        $validated['status'] = $validated['status'] ?? 1;

        $eventType->update($validated);

        return redirect()->route('event-types.index')->with('success', 'Event type updated successfully.');
    }

    /**
     * Remove the specified event type.
     */
    public function destroy(EventType $eventType): RedirectResponse
    {
        if ($eventType->event_source === 'system') {
            return back()->with('error', 'System event types cannot be deleted.');
        }

        if ($eventType->events()->exists()) {
            return back()->with('error', 'Cannot delete an event type that has attached events.');
        }

        $eventType->delete();

        return redirect()->route('event-types.index')->with('success', 'Event type deleted successfully.');
    }
}
