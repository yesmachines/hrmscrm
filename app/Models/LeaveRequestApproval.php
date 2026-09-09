<?php

namespace App\Models;

use App\Models\SalesCrm\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['leave_request_id', 'approval_level', 'approver_id', 'remarks', 'approved_date'])]
class LeaveRequestApproval extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'approved_date' => 'datetime',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
