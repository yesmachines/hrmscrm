<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['leave_request_id', 'action_type', 'remarks', 'done_by', 'action_on'])]
class LeaveHistory extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'action_on' => 'datetime',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
