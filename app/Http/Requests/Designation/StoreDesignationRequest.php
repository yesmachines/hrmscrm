<?php

namespace App\Http\Requests\Designation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDesignationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255', Rule::unique('designations', 'title')],
            'shortcode' => ['required', 'string', 'max:50'],
            'status' => ['nullable', 'integer', Rule::in([0, 1])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function designationPayload(): array
    {
        $data = $this->validated();
        $data['status'] = isset($data['status']) ? (int) $data['status'] : 1;

        return $data;
    }
}
