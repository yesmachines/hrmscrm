<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentType
 */
class DocumentTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DocumentType|null $type */
        $type = $this->resource;
        $templates = $type?->documentTemplates ?? collect();
        $hasTemplate = $templates->isNotEmpty();

        return [
            'id' => $type?->id,
            'category_id' => $type?->category_id,
            'category_name' => $type?->category?->category_name,
            'category_code' => $type?->category?->short_code,
            'document_name' => $type?->document_name,
            'document_code' => $type?->document_code,
            'editable_before_approval' => $type ? (bool) $type->editable_before_approval : null,
            'requires_hr_approval' => $type ? (bool) $type->requires_hr_approval : null,
            'requires_reminder' => $type ? (bool) $type->requires_reminder : null,
            'record_source' => $type?->record_source,
            'field_requires' => empty($type?->field_requires) ? null : (object) $type->field_requires,
            'has_blank_form' => $hasTemplate,
            'blank_form_url' => $hasTemplate ? route('api.v1.documents.blank-form', $type->id) : null,
            'templates' => $templates->map(fn ($template) => [
                'id' => $template->id,
                'template_name' => $template->template_name,
            ])->values(),
        ];
    }
}
