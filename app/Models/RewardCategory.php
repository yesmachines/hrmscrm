<?php

namespace App\Models;

use Database\Factories\RewardCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reward_name',
    'short_code',
    'status',
    'details',
])]
class RewardCategory extends Model
{
    /** @use HasFactory<RewardCategoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'integer',
        ];
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class, 'category_id');
    }

    /**
     * Scope a query to only active reward categories.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 1);
    }
}
