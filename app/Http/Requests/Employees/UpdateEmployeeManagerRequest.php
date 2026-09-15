<?php

namespace App\Http\Requests\Employees;

use App\Models\SalesCrm\EmployeeManager;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeManagerRequest extends FormRequest
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
        /** @var EmployeeManager|null $assignment */
        $assignment = $this->route('employee_manager');

        return [
            'manager_id' => [
                'required',
                'integer',
                'exists:salescrm.employees,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($assignment) {
                    if ($assignment && (int) $value === (int) $assignment->employee_id) {
                        $fail('An employee cannot be assigned as their own reporting manager.');
                    }
                },
            ],
            'department_id' => ['nullable', 'integer', 'exists:salescrm.departments,id'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * @return array{manager_id: int, department_id: ?int, priority: int}
     */
    public function updatePayload(): array
    {
        return [
            'manager_id' => (int) $this->input('manager_id'),
            'department_id' => $this->filled('department_id') ? (int) $this->input('department_id') : null,
            'priority' => $this->filled('priority') ? (int) $this->input('priority') : 1,
        ];
    }
}
