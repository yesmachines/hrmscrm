<?php

namespace App\Http\Requests;

use App\Models\DocumentCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('parent_id') === '') {
            $this->merge(['parent_id' => null]);
        }

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
            'category_name' => ['required', 'string', 'max:255'],
            'short_code' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'integer', Rule::in([0, 1])],
            'parent_id' => ['nullable', 'integer', Rule::exists(DocumentCategory::class, 'id')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'category_name' => $validated['category_name'],
            'short_code' => $validated['short_code'],
            'status' => (int) ($validated['status'] ?? 1),
            'parent_id' => $validated['parent_id'] ?? null,
        ];
    }
}
