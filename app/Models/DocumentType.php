<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id',
    'document_name',
    'document_code',
    'editable_before_approval',
    'requires_hr_approval',
    'requires_reminder',
    'record_source',
    'validity_days',
    'field_requires',
])]
class DocumentType extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'editable_before_approval' => 'boolean',
            'requires_hr_approval' => 'boolean',
            'requires_reminder' => 'boolean',
            'validity_days' => 'integer',
            'field_requires' => 'array',
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

    public function resolveTemplate(?int $organisationId = null): ?DocumentTemplate
    {
        $query = $this->documentTemplates()->where('status', 1);

        if ($organisationId) {
            $orgTemplate = (clone $query)->where('organisation_id', $organisationId)->first();
            if ($orgTemplate) {
                return $orgTemplate;
            }
        }

        return (clone $query)->whereNull('organisation_id')->first()
            ?? $query->first();
    }
}
