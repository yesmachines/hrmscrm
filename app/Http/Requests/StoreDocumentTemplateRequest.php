<?php

namespace App\Http\Requests;

use App\Models\DocumentType;
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
            'template_name' => ['required', 'string', 'max:255'],
            'template_code' => ['required', 'string', 'max:255'],
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
            'template_name' => $validated['template_name'],
            'template_code' => $validated['template_code'],
            'status' => (int) ($validated['status'] ?? 1),
        ];
    }
}
