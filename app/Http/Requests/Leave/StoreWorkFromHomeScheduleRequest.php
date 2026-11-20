<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkFromHomeScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:salescrm.employees,id'],
            'working_days' => ['required', 'integer', 'min:0'],
            'requested_date' => ['required', 'date'],
            'effective_from' => ['nullable', 'date', 'required_with:effective_to'],
            'effective_to' => ['nullable', 'date', 'required_with:effective_from', 'after_or_equal:effective_from'],
        ];
    }
}
