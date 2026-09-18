<?php

namespace App\Models;

use Database\Factories\EventTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'event_code',
    'event_name',
    'event_source',
    'priority',
    'icon_path',
    'status',
])]
class EventType extends Model
{
    /** @use HasFactory<EventTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'status' => 'integer',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Scope a query to only active event types.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 1);
    }

    /**
     * Scope a query to manual event types.
     *
     * @param  Builder<self>  $query
     */
    public function scopeManual(Builder $query): void
    {
        $query->where('event_source', 'manual');
    }

    /**
     * Scope a query to system event types.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSystem(Builder $query): void
    {
        $query->where('event_source', 'system');
    }
}
