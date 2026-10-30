<?php

namespace App\Http\Requests\Documents;

use App\Models\DocumentCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'editable_before_approval',
            'requires_hr_approval',
            'requires_reminder',
        ] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        if ($this->input('record_source') === '') {
            $this->merge(['record_source' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists(DocumentCategory::class, 'id')],
            'document_name' => ['required', 'string', 'max:255'],
            'document_code' => ['required', 'string', 'max:255'],
            'editable_before_approval' => ['nullable', 'boolean'],
            'requires_hr_approval' => ['nullable', 'boolean'],
            'requires_reminder' => ['nullable', 'boolean'],
            'requires_number' => ['required', 'boolean'],
            'requires_expiry' => ['required', 'boolean'],
            'requires_issue_date' => ['required', 'boolean'],
            'requires_attachments' => ['required', 'boolean'],
            'requires_uid' => ['required', 'boolean'],
            'requires_total_experience' => ['required', 'boolean'],
            'record_source' => ['nullable', 'string', Rule::in(['uploaded', 'generated'])],
            'validity_days' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();
        $fieldRequires = [
            'ref_number' => (bool) $validated['requires_number'],
            'expiry_date' => (bool) $validated['requires_expiry'],
            'issue_date' => (bool) $validated['requires_issue_date'],
            'attachments' => (bool) $validated['requires_attachments'],
            'uid' => (bool) $validated['requires_uid'],
            'total_experience' => (bool) $validated['requires_total_experience'],
        ];

        return [
            'category_id' => $validated['category_id'],
            'document_name' => $validated['document_name'],
            'document_code' => $validated['document_code'],
            'editable_before_approval' => (bool) ($validated['editable_before_approval'] ?? false),
            'requires_hr_approval' => (bool) ($validated['requires_hr_approval'] ?? false),
            'requires_reminder' => (bool) ($validated['requires_reminder'] ?? false),
            'record_source' => $validated['record_source'] ?? null,
            'validity_days' => isset($validated['validity_days']) && $validated['validity_days'] !== '' ? (int) $validated['validity_days'] : null,
            'field_requires' => (object) $fieldRequires,
        ];
    }
}
