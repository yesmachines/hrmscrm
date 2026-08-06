<?php

namespace App\Http\Requests;

use App\Concerns\EmployeeValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeProfileRequest extends FormRequest
{
    use EmployeeValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareEmployeeInput();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->employeeRules(isCreate: true);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>, 3: list<string>}
     */
    public function employeePayload(): array
    {
        return $this->splitEmployeePayload($this->validated());
    }
}
