<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Organisation;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * Display a listing of events with calendar/list views and filters.
     */
    public function index(Request $request, EventService $eventService): Response
    {
        $events = $eventService->list($request, [
            'eventType',
            'organisation:id,org_name,short_name',
            'employee:id,user_id,emp_num,designation',
            'employee.user:id,name',
            'creator:id,name',
        ], 30, ['search', 'event_type_id', 'organisation_id', 'status', 'date']);
        $events->withQueryString();

        $eventTypes = EventType::query()
            ->active()
            ->orderBy('priority')
            ->orderBy('event_name')
            ->get();

        $organisations = Organisation::query()->select('id', 'org_name', 'short_name')->get();

        return Inertia::render('events/index', [
            'events' => $events,
            'eventTypes' => $eventTypes,
            'organisations' => $organisations,
            'filters' => $request->only([
                'search',
                'event_type_id',
                'organisation_id',
                'status',
                'date_filter',
                'month',
                'year',
                'view',
            ]),
        ]);
    }

    /**
     * Store a newly created event.
     */
    public function store(Request $request, EventService $eventService): RedirectResponse
    {
        $validated = $request->validate([
            'event_type_id' => ['required', 'exists:event_types,id'],
            'organisation_id' => ['nullable', 'exists:organisations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'external_link' => ['nullable', 'url', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published', 'cancelled'])],
            'employee_id' => ['nullable', 'exists:salescrm.employees,id'],
            'show_dashboard' => ['nullable', 'boolean'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $validated['created_by'] = $request->user()?->id;
        $validated['show_dashboard'] = $request->boolean('show_dashboard', true);

        $eventService->create($validated, $request->file('file'));

        return redirect()->route('events.index')->with('success', 'Event created successfully.');
    }

    /**
     * Display the specified event.
     */
    public function show(Event $event): Response
    {
        $event->load([
            'eventType',
            'organisation',
            'employee.user',
            'creator',
        ]);

        return Inertia::render('events/show', [
            'event' => $event,
        ]);
    }

    /**
     * Update the specified event.
     */
    public function update(Request $request, Event $event, EventService $eventService): RedirectResponse
    {
        $validated = $request->validate([
            'event_type_id' => ['required', 'exists:event_types,id'],
            'organisation_id' => ['nullable', 'exists:organisations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'external_link' => ['nullable', 'url', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published', 'cancelled'])],
            'employee_id' => ['nullable', 'exists:salescrm.employees,id'],
            'show_dashboard' => ['nullable', 'boolean'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $validated['show_dashboard'] = $request->boolean('show_dashboard', $event->show_dashboard);

        $eventService->update($event, $validated, $request->file('file'));

        return redirect()->route('events.index')->with('success', 'Event updated successfully.');
    }

    /**
     * Remove the specified event (soft delete).
     */
    public function destroy(Event $event, EventService $eventService): RedirectResponse
    {
        $eventService->delete($event);

        return redirect()->route('events.index')->with('success', 'Event deleted successfully.');
    }
}
