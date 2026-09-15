<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }

    /**
     * @return array{name: string, code: ?string, status: int}
     */
    public function departmentPayload(): array
    {
        return [
            'name' => (string) $this->input('name'),
            'code' => $this->filled('code') ? strtoupper((string) $this->input('code')) : null,
            'status' => (int) $this->input('status', 1),
        ];
    }
}
