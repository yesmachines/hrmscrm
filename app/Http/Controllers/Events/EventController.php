<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Organisation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
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

        // Flexible date filter (today, tomorrow, upcoming, past, or exact date)
        $dateFilter = $request->query('date_filter')
            ?? $request->query('day')
            ?? $request->query('date')
            ?? $request->query('filter')
            ?? $request->input('date_filter')
            ?? $request->input('day')
            ?? $request->input('date')
            ?? $request->input('filter');

        if (! empty($dateFilter)) {
            $normalized = strtolower(trim(str_replace(['_', '-'], ' ', (string) $dateFilter)));
            $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

            if (in_array($normalized, ['before today', 'before', 'past'])) {
                $today = now()->toDateString();
                $query->whereDate('start_datetime', '<', $today);
            } elseif (in_array($normalized, ['after today', 'after', 'future', 'upcoming'])) {
                $today = now()->toDateString();
                $query->whereDate('start_datetime', '>', $today);
            } else {
                $targetDate = null;
                if (in_array($normalized, ['today', 'current day', 'current'])) {
                    $targetDate = now()->toDateString();
                } elseif (in_array($normalized, ['tomorrow', 'tomorow'])) {
                    $targetDate = now()->addDay()->toDateString();
                } elseif (in_array($normalized, ['day after tomorrow', 'day after tommarow'])) {
                    $targetDate = now()->addDays(2)->toDateString();
                } elseif ($normalized === 'yesterday') {
                    $targetDate = now()->subDay()->toDateString();
                } else {
                    try {
                        $targetDate = Carbon::parse($dateFilter)->toDateString();
                    } catch (\Throwable) {
                        $targetDate = null;
                    }
                }

                if ($targetDate) {
                    $query->where(function (Builder $q) use ($targetDate): void {
                        $q->whereDate('start_datetime', $targetDate)
                            ->orWhere(function (Builder $sub) use ($targetDate): void {
                                $sub->whereNotNull('end_datetime')
                                    ->whereDate('start_datetime', '<=', $targetDate)
                                    ->whereDate('end_datetime', '>=', $targetDate);
                            });
                    });
                }
            }
        }

        // Month / Year filter for Calendar
        if ($request->filled('month') && $request->filled('year')) {
            $month = (int) $request->input('month');
            $year = (int) $request->input('year');
            $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
            $endOfMonth = (clone $startOfMonth)->endOfMonth();

            $query->where(function (Builder $q) use ($startOfMonth, $endOfMonth): void {
                $q->whereBetween('start_datetime', [$startOfMonth, $endOfMonth])
                    ->orWhere(function (Builder $sub) use ($startOfMonth, $endOfMonth): void {
                        $sub->whereNotNull('end_datetime')
                            ->where('start_datetime', '<=', $endOfMonth)
                            ->where('end_datetime', '>=', $startOfMonth);
                    });
            });
        }

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
