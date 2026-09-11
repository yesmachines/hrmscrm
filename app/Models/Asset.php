<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'category_id',
    'referenceno',
    'asset_name',
    'details',
    'condition',
    'status',
    'attachment',
])]
class Asset extends Model
{
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class, 'asset_id');
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class, 'asset_id')
            ->where('status', 'Assigned')
            ->latestOfMany();
    }

    public function requests(): HasMany
    {
        return $this->hasMany(AssetRequest::class, 'asset_id');
    }
}
