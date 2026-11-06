<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'leave_type_id',
    'organisation_id',
    'annual_limit',
    'allow_once',
    'allow_balance',
    'full_pay_days',
    'half_pay_days',
    'no_pay_days',
    'requires_document_after_days',
    'requires_weekend_document',
    'requires_handover',
    'carry_forward',
    'carry_forward_weeks',
    'encashment',
    'remarks',
    'requires_attachment',
    'probation_applicable',
    'minimum_service_months',
    'exclude_rules',
])]
class LeavePolicy extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'annual_limit' => 'integer',
            'allow_once' => 'boolean',
            'allow_balance' => 'boolean',
            'requires_weekend_document' => 'boolean',
            'requires_handover' => 'boolean',
            'carry_forward' => 'boolean',
            'carry_forward_weeks' => 'integer',
            'encashment' => 'boolean',
            'requires_attachment' => 'boolean',
            'probation_applicable' => 'boolean',
            'exclude_rules' => 'array',
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
