<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['idea_id', 'action_type', 'remarks', 'done_by', 'action_on'])]
class IdeaTrack extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'action_on' => 'datetime',
        ];
    }

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }
}
