<?php

namespace App\Models;

use App\Models\SalesCrm\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'posted_by',
        'content',
        'status',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'posted_by', 'id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(SocialMedia::class, 'post_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(SocialPostReaction::class, 'post_id');
    }
}
