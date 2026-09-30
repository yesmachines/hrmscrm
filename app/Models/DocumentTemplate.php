<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_type_id', 'organisation_id', 'template_name', 'template_code', 'status'])]
class DocumentTemplate extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'document_type_id' => 'integer',
            'organisation_id' => 'integer',
        ];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @param  Builder<DocumentTemplate>  $query
     * @return Builder<DocumentTemplate>
     */
    public function scopeForOrganisation($query, ?int $organisationId)
    {
        if ($organisationId) {
            return $query->where(function ($q) use ($organisationId) {
                $q->where('organisation_id', $organisationId)
                    ->orWhereNull('organisation_id');
            });
        }

        return $query->whereNull('organisation_id');
    }
}
