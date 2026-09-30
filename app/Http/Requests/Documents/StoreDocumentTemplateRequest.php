<?php

namespace App\Http\Requests\Documents;

use App\Models\DocumentType;
use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('status') && $this->input('status') !== null && $this->input('status') !== '') {
            $this->merge(['status' => (int) $this->input('status')]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type_id' => ['required', 'integer', Rule::exists(DocumentType::class, 'id')],
            'organisation_id' => ['nullable', 'integer', Rule::exists(Organisation::class, 'id')],
            'template_name' => ['required', 'string', 'max:255'],
            'template_code' => ['required', 'string'],
            'status' => ['nullable', 'integer', Rule::in([0, 1])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'document_type_id' => $validated['document_type_id'],
            'organisation_id' => isset($validated['organisation_id']) && $validated['organisation_id'] !== '' ? (int) $validated['organisation_id'] : null,
            'template_name' => $validated['template_name'],
            'template_code' => $validated['template_code'],
            'status' => (int) ($validated['status'] ?? 1),
        ];
    }
}
