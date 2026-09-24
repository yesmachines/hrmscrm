<?php

namespace App\Models;

use App\Models\SalesCrm\Employee;
use Database\Factories\RewardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id',
    'submitted_by',
    'claim_no',
    'description',
    'amount',
    'document_file',
    'submitted_date',
    'status',
])]
class Reward extends Model
{
    /** @use HasFactory<RewardFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'submitted_by' => 'integer',
            'amount' => 'decimal:2',
            'submitted_date' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RewardCategory::class, 'category_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'submitted_by');
    }

    public function approvalStatuses(): HasMany
    {
        return $this->hasMany(RewardApprovalStatus::class, 'reward_id')->orderBy('done_on', 'asc');
    }

    /**
     * Scope a query to pending rewards.
     *
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    /**
     * Scope a query to approved rewards.
     *
     * @param  Builder<self>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', 'approved');
    }

    /**
     * Scope a query to paid rewards.
     *
     * @param  Builder<self>  $query
     */
    public function scopePaid(Builder $query): void
    {
        $query->where('status', 'paid');
    }

    /**
     * Scope a query to rejected rewards.
     *
     * @param  Builder<self>  $query
     */
    public function scopeRejected(Builder $query): void
    {
        $query->where('status', 'rejected');
    }
}
