<?php

namespace App\Models;

use App\Models\SalesCrm\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'visit_id',
    'approver_id',
    'instructions',
    'rejected_reason',
])]
class VisitApproval extends Model
{
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
