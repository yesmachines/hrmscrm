<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'employee_id',
    'title',
    'description',
    'idea_files',
    'status',
    'review_comment',
])]
class Idea extends Model
{
    public function tracks(): HasMany
    {
        return $this->hasMany(IdeaTrack::class);
    }
}
