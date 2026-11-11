<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'leave_policy_id',
    'from_day',
    'to_day',
    'pay_percentage',
])]
class LeavePolicyTier extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'leave_policy_id' => 'integer',
            'from_day' => 'integer',
            'to_day' => 'integer',
            'pay_percentage' => 'decimal:2',
        ];
    }

    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id');
    }
}
