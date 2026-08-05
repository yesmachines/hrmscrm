<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['leave_request_id', 'file_path', 'uploaded_by', 'uploaded_date'])]
class LeaveRequestFile extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'uploaded_date' => 'datetime',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
