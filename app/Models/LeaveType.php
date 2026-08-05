<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'leave_name',
    'code',
    'is_paid',
    'requires_attachment',
    'requires_approval',
    'max_days',
    'annual_limit',
    'gender',
    'allow_once',
    'allow_balance',
    'status',
    'requires_handover',
])]
class LeaveType extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'requires_attachment' => 'boolean',
            'requires_approval' => 'boolean',
            'allow_once' => 'boolean',
            'allow_balance' => 'boolean',
            'requires_handover' => 'boolean',
        ];
    }

    public function policies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
