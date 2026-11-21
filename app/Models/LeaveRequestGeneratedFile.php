<?php

namespace App\Models;

use App\Models\SalesCrm\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'leave_request_id',
    'leave_template_id',
    'version_no',
    'file_path',
    'generated_by',
    'generated_at',
    'change_notes',
])]
class LeaveRequestGeneratedFile extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'leave_request_id' => 'integer',
            'leave_template_id' => 'integer',
            'generated_by' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function leaveTemplate(): BelongsTo
    {
        return $this->belongsTo(LeaveTemplate::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
