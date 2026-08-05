<?php

namespace App\Concerns;

use Illuminate\Validation\Rule;

trait EmployeeProfileValidationRules
{
    protected function prepareEmployeeProfileInput(): void
    {
        $nullable = [
            'gender',
            'dob_personal',
            'marital_status',
            'nationality',
            'religion',
            'blood_group',
            'personal_email',
            'personal_mobile',
            'address_uae',
            'emergency_contact_name',
            'emergency_relation',
            'emergency_mobile',
            'home_country',
            'address_home',
            'home_mobile',
            'home_emergency_name',
            'home_emergency_relation',
            'home_emergency_mobile',
            'visa_type',
            'visa_from',
            'dob_passport',
            'total_experience',
            'highest_education',
        ];

        $merged = [];

        foreach ($nullable as $field) {
            if ($this->input($field) === '') {
                $merged[$field] = null;
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function employeeProfileRules(?int $employeeProfileId = null): array
    {
        $employeeIdRules = [
            'required',
            'integer',
            'exists:users,id',
            Rule::unique('employee_profiles', 'employee_id')->ignore($employeeProfileId),
        ];

        return [
            'employee_id' => $employeeIdRules,
            'gender' => ['nullable', 'string', Rule::in(['M', 'F'])],
            'dob_personal' => ['nullable', 'date'],
            'marital_status' => ['nullable', 'string', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'nationality' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:255'],
            'blood_group' => ['nullable', 'string', 'max:255'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'personal_mobile' => ['nullable', 'string', 'max:255'],
            'address_uae' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_relation' => ['nullable', 'string', 'max:255'],
            'emergency_mobile' => ['nullable', 'string', 'max:255'],
            'home_country' => ['nullable', 'integer'],
            'address_home' => ['nullable', 'string', 'max:255'],
            'home_mobile' => ['nullable', 'string', 'max:255'],
            'home_emergency_name' => ['nullable', 'string', 'max:255'],
            'home_emergency_relation' => ['nullable', 'string', 'max:255'],
            'home_emergency_mobile' => ['nullable', 'string', 'max:255'],
            'visa_type' => ['nullable', 'string', Rule::in(['visa', 'workpermit'])],
            'visa_from' => ['nullable', 'string', 'max:255'],
            'dob_passport' => ['nullable', 'date'],
            'total_experience' => ['nullable', 'numeric', 'min:0', 'max:80'],
            'highest_education' => ['nullable', 'string', 'max:255'],
        ];
    }
}
