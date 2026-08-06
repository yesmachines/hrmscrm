<?php

namespace App\Concerns;

use App\Models\OfficeLocation;
use App\Models\Organisation;
use App\Models\SalesCrm\Department;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait EmployeeValidationRules
{
    protected function prepareEmployeeInput(): void
    {
        $nullable = [
            'employee_code',
            'phone',
            'designation_id',
            'employment_status',
            'organisation_id',
            'office_location_id',
            'joining_date',
            'resignation_date',
            'image_url',
            'department_id',
            'password',
            'roles',
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

        if ($this->has('has_report')) {
            $merged['has_report'] = $this->boolean('has_report');
        }

        if ($this->has('status') && $this->input('status') !== null && $this->input('status') !== '') {
            $merged['status'] = (int) $this->input('status');
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function employeeRules(?Employee $employee = null, bool $isCreate = true): array
    {
        $emailUnique = Rule::unique(User::class, 'email');
        $empNumUnique = Rule::unique(Employee::class, 'emp_num');
        $empCodeUnique = Rule::unique(Employee::class, 'employee_code');

        if ($employee !== null) {
            $emailUnique->ignore($employee->user_id);
            $empNumUnique->ignore($employee->id);
            $empCodeUnique->ignore($employee->id);
        }

        $passwordRules = $isCreate
            ? ['required', 'string', Password::defaults()]
            : ['nullable', 'string', Password::defaults()];

        $roleExists = Rule::exists('salescrm.roles', 'name')
            ->where(fn ($query) => $query->where('id', '<>', 1));

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', $emailUnique],
            'password' => $passwordRules,
            'roles' => [$isCreate ? 'required' : 'nullable', 'string', 'max:255', $roleExists],
            'emp_num' => ['required', 'string', 'max:255', $empNumUnique],
            'employee_code' => ['nullable', 'string', 'max:255', $empCodeUnique],
            'phone' => ['nullable', 'string', 'max:255'],
            'designation' => ['required', 'string', 'max:255'],
            'designation_id' => ['nullable', 'integer'],
            'employment_status' => ['nullable', 'string', Rule::in(['fulltime', 'parttime', 'contract', 'intern'])],
            'organisation_id' => ['nullable', 'integer', Rule::exists(Organisation::class, 'id')],
            'office_location_id' => ['nullable', 'integer', Rule::exists(OfficeLocation::class, 'id')],
            'joining_date' => ['nullable', 'date'],
            'resignation_date' => ['nullable', 'date', 'after_or_equal:joining_date'],
            'division' => ['required', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'integer', Rule::in([0, 1])],
            'has_report' => ['nullable', 'boolean'],
            'department_id' => ['nullable', 'integer', Rule::exists(Department::class, 'id')],
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>, 3: list<string>}
     */
    protected function splitEmployeePayload(array $validated): array
    {
        $crmUser = array_intersect_key($validated, array_flip(['name', 'email', 'password']));

        if (! array_key_exists('password', $crmUser) || blank($crmUser['password'] ?? null)) {
            unset($crmUser['password']);
        }

        $crmEmployee = array_intersect_key($validated, array_flip([
            'organisation_id',
            'emp_num',
            'employee_code',
            'phone',
            'designation',
            'designation_id',
            'employment_status',
            'office_location_id',
            'joining_date',
            'resignation_date',
            'division',
            'image_url',
            'status',
            'has_report',
            'department_id',
        ]));

        $profile = array_intersect_key($validated, array_flip([
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
        ]));

        $roles = [];

        if (! blank($validated['roles'] ?? null)) {
            $roles = is_array($validated['roles'])
                ? array_values(array_filter(array_map('strval', $validated['roles'])))
                : [(string) $validated['roles']];
        }

        return [$crmUser, $crmEmployee, $profile, $roles];
    }
}
