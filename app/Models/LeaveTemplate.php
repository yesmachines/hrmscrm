<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['leave_type_id', 'organisation_id', 'template_name', 'template_code', 'status'])]
class LeaveTemplate extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'leave_type_id' => 'integer',
            'organisation_id' => 'integer',
            'status' => 'integer',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @param  Builder<LeaveTemplate>  $query
     * @return Builder<LeaveTemplate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
