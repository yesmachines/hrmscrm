<?php

namespace App\Models;

use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'request_no',
    'request_type',
    'asset_id',
    'category_id',
    'requested_by',
    'requested_date',
    'description',
    'priority',
    'status',
    'approved_by',
    'approved_at',
    'rejection_reason',
    'admin_notes',
])]
class AssetRequest extends Model
{
    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
