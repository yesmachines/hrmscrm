<?php

namespace App\Http\Requests\Employees;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeManagerRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:salescrm.employees,id'],
            'manager_id' => ['required', 'integer', 'different:employee_id', 'exists:salescrm.employees,id'],
            'department_id' => ['nullable', 'integer', 'exists:salescrm.departments,id'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'manager_id.different' => 'An employee cannot be assigned as their own reporting manager.',
        ];
    }

    /**
     * @return array{employee_id: int, manager_id: int, department_id: ?int, priority: int}
     */
    public function assignmentPayload(): array
    {
        return [
            'employee_id' => (int) $this->input('employee_id'),
            'manager_id' => (int) $this->input('manager_id'),
            'department_id' => $this->filled('department_id') ? (int) $this->input('department_id') : null,
            'priority' => $this->filled('priority') ? (int) $this->input('priority') : 1,
        ];
    }
}
