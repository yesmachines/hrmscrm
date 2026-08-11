<?php

namespace App\Models;

use App\Models\SalesCrm\Employee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'employee_id',
    'leave_type_id',
    'start_date',
    'end_date',
    'total_days',
    'remarks',
    'status',
    'created_by',
])]
class LeaveRequest extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'total_days' => 'float',
        ];
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

    public function details(): HasMany
    {
        return $this->hasMany(LeaveRequestDetail::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(LeaveRequestApproval::class);
    }
}
