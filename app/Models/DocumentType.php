<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'category_id',
    'document_name',
    'document_code',
    'requires_number',
    'requires_expiry',
    'editable_before_approval',
    'requires_hr_approval',
    'requires_reminder',
    'record_source',
    'requires_attachments',
])]
class DocumentType extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'requires_number' => 'boolean',
            'requires_expiry' => 'boolean',
            'editable_before_approval' => 'boolean',
            'requires_hr_approval' => 'boolean',
            'requires_reminder' => 'boolean',
            'requires_attachments' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class);
    }

    public function documentTemplates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class);
    }
}
