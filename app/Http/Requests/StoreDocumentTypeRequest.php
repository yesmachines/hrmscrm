<?php

namespace App\Http\Requests;

use App\Models\DocumentCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'requires_number',
            'requires_expiry',
            'editable_before_approval',
            'requires_hr_approval',
            'requires_reminder',
            'requires_attachments',
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
            'requires_number' => ['nullable', 'boolean'],
            'requires_expiry' => ['nullable', 'boolean'],
            'editable_before_approval' => ['nullable', 'boolean'],
            'requires_hr_approval' => ['nullable', 'boolean'],
            'requires_reminder' => ['nullable', 'boolean'],
            'record_source' => ['nullable', 'string', Rule::in(['uploaded', 'generated'])],
            'requires_attachments' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'category_id' => $validated['category_id'],
            'document_name' => $validated['document_name'],
            'document_code' => $validated['document_code'],
            'requires_number' => (bool) ($validated['requires_number'] ?? false),
            'requires_expiry' => (bool) ($validated['requires_expiry'] ?? false),
            'editable_before_approval' => (bool) ($validated['editable_before_approval'] ?? false),
            'requires_hr_approval' => (bool) ($validated['requires_hr_approval'] ?? false),
            'requires_reminder' => (bool) ($validated['requires_reminder'] ?? false),
            'record_source' => $validated['record_source'] ?? null,
            'requires_attachments' => (bool) ($validated['requires_attachments'] ?? false),
        ];
    }
}
