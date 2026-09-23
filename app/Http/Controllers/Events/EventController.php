<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Organisation;
use App\Traits\FiltersEventsByDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    use FiltersEventsByDate;

    /**
     * Display a listing of events with calendar/list views and filters.
     */
    public function index(Request $request): Response
    {
        $query = Event::query()
            ->with([
                'eventType',
                'organisation:id,org_name,short_name',
                'employee:id,user_id,emp_num,designation',
                'employee.user:id,name',
                'creator:id,name',
            ]);

        // Search
        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function (Builder $q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('eventType', function (Builder $typeQuery) use ($search): void {
                        $typeQuery->where('event_name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by Event Type
        if ($request->filled('event_type_id')) {
            $query->where('event_type_id', $request->input('event_type_id'));
        }

        // Filter by Organisation
        if ($request->filled('organisation_id')) {
            $query->where('organisation_id', $request->input('organisation_id'));
        }

        // Filter by Status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Apply flexible date and month/year filters
        $this->applyDateAndMonthFilters($query, $request);

        $events = $query
            ->orderBy('start_datetime')
            ->paginate(30)
            ->withQueryString();

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
    public function store(Request $request): RedirectResponse
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

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('events', 'public');
            $validated['file_path'] = $path;
        }

        $validated['created_by'] = $request->user()?->id;
        $validated['show_dashboard'] = $request->boolean('show_dashboard', true);

        Event::query()->create($validated);

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
    public function update(Request $request, Event $event): RedirectResponse
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

        if ($request->hasFile('file')) {
            if ($event->file_path && Storage::disk('public')->exists($event->file_path)) {
                Storage::disk('public')->delete($event->file_path);
            }
            $validated['file_path'] = $request->file('file')->store('events', 'public');
        }

        $validated['show_dashboard'] = $request->boolean('show_dashboard', $event->show_dashboard);

        $event->update($validated);

        return redirect()->route('events.index')->with('success', 'Event updated successfully.');
    }

    /**
     * Remove the specified event (soft delete).
     */
    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('events.index')->with('success', 'Event deleted successfully.');
    }
}
