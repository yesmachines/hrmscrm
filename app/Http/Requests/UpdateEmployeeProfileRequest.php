<?php

namespace App\Http\Requests;

use App\Concerns\EmployeeProfileValidationRules;
use App\Models\EmployeeProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeProfileRequest extends FormRequest
{
    use EmployeeProfileValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareEmployeeProfileInput();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var EmployeeProfile $employeeProfile */
        $employeeProfile = $this->route('employeeProfile');

        $rules = $this->employeeProfileRules($employeeProfile->id);
        unset($rules['employee_id']);

        return $rules;
    }
}
