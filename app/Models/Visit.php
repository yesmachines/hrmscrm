<?php

namespace App\Models;

use App\Models\SalesCrm\Employee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'total_visitors',
    'visitor_details',
    'company',
    'contact_no',
    'email',
    'purpose',
    'location',
    'expected_start_date',
    'expected_end_date',
    'created_by',
    'required_approvals',
    'status',
])]
class Visit extends Model
{
    protected function casts(): array
    {
        return [
            'total_visitors' => 'integer',
            'visitor_details' => 'array',
            'expected_start_date' => 'datetime',
            'expected_end_date' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(VisitApproval::class);
    }

    public function latestApproval(): HasOne
    {
        return $this->hasOne(VisitApproval::class)->latestOfMany();
    }
}
