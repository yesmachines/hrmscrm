<?php

namespace App\Models;

use App\Models\SalesCrm\Employee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'leave_type_id',
    'year',
    'allocated',
    'carried_forward',
    'used',
    'balance',
    'encashed',
    'earned',
    'last_calculated_at',
    'pending',
])]
class LeaveBalance extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'allocated' => 'float',
            'carried_forward' => 'float',
            'used' => 'float',
            'balance' => 'float',
            'encashed' => 'float',
            'earned' => 'float',
            'pending' => 'float',
            'last_calculated_at' => 'datetime',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
