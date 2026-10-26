<?php

namespace App\Services;

use App\Models\Event;
use App\Traits\FiltersEventsByDate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Shared methods are used by Admin and API controllers; change them carefully to preserve both workflows.
 */
class EventService
{
    use FiltersEventsByDate;

    /**
     * @param  array<int, mixed>  $relations
     */
    public function list(Request $request, array $relations, int $perPage, array $filters): LengthAwarePaginator
    {
        $query = Event::query()->with($relations);

        if (in_array('published', $filters, true)) {
            $query->where('status', 'published');
        }

        if (in_array('search', $filters, true) && $request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function (Builder $q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('eventType', function (Builder $typeQuery) use ($search): void {
                        $typeQuery->where('event_name', 'like', "%{$search}%");
                    });
            });
        }

        if (in_array('event_type_id', $filters, true) && $request->filled('event_type_id')) {
            $query->where('event_type_id', $request->input('event_type_id'));
        }

        if (in_array('organisation_id', $filters, true) && $request->filled('organisation_id')) {
            $query->where('organisation_id', $request->input('organisation_id'));
        }

        if (in_array('status', $filters, true) && $request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if (in_array('source', $filters, true) && $request->filled('source')) {
            $source = $request->input('source');
            $query->whereHas('eventType', function (Builder $q) use ($source): void {
                $q->where('event_source', $source);
            });
        }

        if (in_array('dashboard', $filters, true) && $request->has('dashboard')) {
            $query->where('show_dashboard', $request->boolean('dashboard'));
        }

        if (in_array('date', $filters, true)) {
            $this->applyDateAndMonthFilters($query, $request);
        }

        return $query->orderBy('start_datetime')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?UploadedFile $file): Event
    {
        if ($file !== null) {
            $attributes['file_path'] = $file->store('events', 'public');
        }

        return Event::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Event $event, array $attributes, ?UploadedFile $file): Event
    {
        if ($file !== null) {
            if ($event->file_path && Storage::disk('public')->exists($event->file_path)) {
                Storage::disk('public')->delete($event->file_path);
            }

            $attributes['file_path'] = $file->store('events', 'public');
        }

        $event->update($attributes);

        return $event;
    }

    public function delete(Event $event): void
    {
        $event->delete();
    }
}
