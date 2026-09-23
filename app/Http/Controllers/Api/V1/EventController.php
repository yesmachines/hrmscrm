<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventType;
use App\Traits\ApiResponse;
use App\Traits\FiltersEventsByDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    use ApiResponse;
    use FiltersEventsByDate;

    /**
     * List events with date and type filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::query()
            ->with([
                'eventType:id,event_code,event_name,event_source,priority,icon_path',
                'organisation:id,org_name,short_name',
                'employee:id,user_id,emp_num,designation',
                'employee.user:id,name',
            ])
            ->where('status', 'published');

        // Filter by Event Type
        if ($request->filled('event_type_id')) {
            $query->where('event_type_id', $request->input('event_type_id'));
        }

        // Filter by event source (system or manual)
        if ($request->filled('source')) {
            $source = $request->input('source');
            $query->whereHas('eventType', function (Builder $q) use ($source): void {
                $q->where('event_source', $source);
            });
        }

        // Filter by dashboard flag
        if ($request->has('dashboard')) {
            $query->where('show_dashboard', $request->boolean('dashboard'));
        }

        // Apply flexible date and month/year filters
        $this->applyDateAndMonthFilters($query, $request);

        $perPage = (int) $request->input('per_page', 20);
        $events = $query->orderBy('start_datetime')->paginate($perPage);

        return $this->successPaginatedResponse($events, 'events', 'Events retrieved successfully.');
    }

    /**
     * Today's events specifically tailored for dashboard display.
     */
    public function today(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 3);

        $events = Event::query()
            ->with([
                'eventType:id,event_code,event_name,event_source,priority,icon_path',
                'organisation:id,org_name,short_name',
            ])
            ->where('status', 'published')
            ->forDashboard()
            ->today()
            ->orderBy('start_datetime')
            ->limit($limit)
            ->get();

        return $this->successResponse([
            'events' => $events,
            'count' => $events->count(),
        ], "Today's events retrieved successfully.");
    }

    /**
     * List all active event types.
     */
    public function types(): JsonResponse
    {
        $types = EventType::query()
            ->active()
            ->orderBy('priority')
            ->orderBy('event_name')
            ->get();

        return $this->successResponse($types, 'Event types retrieved successfully.');
    }

    /**
     * Get a single event.
     */
    public function show(int $id): JsonResponse
    {
        $event = Event::query()
            ->with([
                'eventType',
                'organisation:id,org_name,short_name',
                'employee:id,user_id,emp_num,designation',
                'employee.user:id,name,email',
                'creator:id,name',
            ])
            ->find($id);

        if (! $event) {
            return $this->errorResponse('Event not found.', 404);
        }

        return $this->successResponse($event, 'Event retrieved successfully.');
    }

    /**
     * Create an event via API.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type_id' => ['required', 'exists:event_types,id'],
            'organisation_id' => ['nullable', 'exists:organisations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'external_link' => ['nullable', 'url', 'max:255'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'cancelled'])],
            'employee_id' => ['nullable', 'exists:salescrm.employees,id'],
            'show_dashboard' => ['nullable', 'boolean'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        if ($request->hasFile('file')) {
            $validated['file_path'] = $request->file('file')->store('events', 'public');
        }

        $validated['created_by'] = $request->user()?->id;
        $validated['status'] = $validated['status'] ?? 'published';
        $validated['show_dashboard'] = $request->boolean('show_dashboard', true);

        $event = Event::query()->create($validated);

        return $this->successResponse(
            $event->fresh(['eventType', 'organisation']),
            'Event created successfully.',
            201
        );
    }
}
