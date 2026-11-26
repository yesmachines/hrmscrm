<?php

namespace App\Models;

use App\Models\SalesCrm\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'leave_balance_id',
    'employee_id',
    'leave_type_id',
    'leave_request_id',
    'transaction_type',
    'quantity',
    'balance_before',
    'balance_after',
    'remarks',
    'created_by',
    'created_at',
])]
class LeaveBalanceTransaction extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'balance_before' => 'float',
            'balance_after' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class, 'leave_balance_id');
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
