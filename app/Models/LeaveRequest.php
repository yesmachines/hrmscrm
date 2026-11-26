<?php

namespace App\Models;

use App\Models\SalesCrm\Employee;
use App\Observers\LeaveRequestObserver;
use App\Services\WorkFromHomeService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([LeaveRequestObserver::class])]
#[Fillable([
    'employee_id',
    'leave_type_id',
    'start_date',
    'end_date',
    'total_days',
    'remarks',
    'status',
    'created_by',
    'request_type',
])]
class LeaveRequest extends Model
{
    protected $hidden = ['pending_reserved_days'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_days' => 'float',
            'pending_reserved_days' => 'float',
        ];
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function withoutWorkFromHome(Builder $query): void
    {
        $query->whereHas('leaveType', fn (Builder $type) => $type->where('code', '!=', WorkFromHomeService::LEAVE_TYPE_CODE));
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(LeaveHistory::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(LeaveRequestFile::class);
    }

    public function generatedFiles(): HasMany
    {
        return $this->hasMany(LeaveRequestGeneratedFile::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(LeaveRequestDetail::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(LeaveRequestApproval::class);
    }
}
