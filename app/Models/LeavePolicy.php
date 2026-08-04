<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'leave_type_id',
    'organisation_id',
    'full_pay_days',
    'half_pay_days',
    'no_pay_days',
    'requires_document_after_days',
    'requires_weekend_document',
    'allocation_days',
    'carry_forward',
    'encashment',
    'remarks',
    'requires_attachment',
    'probation_applicable',
    'minimum_service_months',
])]
class LeavePolicy extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'requires_weekend_document' => 'boolean',
            'carry_forward' => 'boolean',
            'encashment' => 'boolean',
            'requires_attachment' => 'boolean',
            'probation_applicable' => 'boolean',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
