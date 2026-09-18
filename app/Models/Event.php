<?php

namespace App\Models;

use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User;
use Carbon\CarbonInterface;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'event_type_id',
    'organisation_id',
    'title',
    'description',
    'start_datetime',
    'end_datetime',
    'external_link',
    'status',
    'created_by',
    'employee_id',
    'file_path',
    'show_dashboard',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type_id' => 'integer',
            'organisation_id' => 'integer',
            'created_by' => 'integer',
            'employee_id' => 'integer',
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'show_dashboard' => 'boolean',
        ];
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only published events.
     *
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    /**
     * Scope a query to events marked for dashboard display.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForDashboard(Builder $query): void
    {
        $query->where('show_dashboard', true);
    }

    /**
     * Scope a query to events occurring today.
     *
     * @param  Builder<self>  $query
     */
    public function scopeToday(Builder $query, ?CarbonInterface $date = null): void
    {
        $targetDate = ($date ?? now())->toDateString();

        $query->where(function (Builder $q) use ($targetDate): void {
            $q->whereDate('start_datetime', $targetDate)
                ->orWhere(function (Builder $sub) use ($targetDate): void {
                    $sub->whereNotNull('end_datetime')
                        ->whereDate('start_datetime', '<=', $targetDate)
                        ->whereDate('end_datetime', '>=', $targetDate);
                });
        });
    }

    /**
     * Scope a query to upcoming events from tomorrow onwards.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUpcoming(Builder $query, ?CarbonInterface $date = null): void
    {
        $targetDate = ($date ?? now())->toDateString();
        $query->whereDate('start_datetime', '>', $targetDate);
    }
}
