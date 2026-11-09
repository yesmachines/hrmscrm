<?php

namespace App\Http\Requests\Leave;

use App\Models\LeaveType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'is_paid',
            'requires_approval',
        ] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        foreach (['gender', 'religion'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        foreach (['gender', 'religion'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $this->merge([
                    $field => $field === 'gender'
                        ? LeaveType::normalizeGender($value)
                        : LeaveType::normalizeReligion($value),
                ]);
            }
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
            'leave_name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:leave_types,code'],
            'is_paid' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'gender' => ['nullable', 'string', Rule::in(array_keys(config('params.leave_type.gender_options', [])))],
            'religion' => ['nullable', 'string', 'max:100', Rule::in(config('params.leave_type.religion_options', []))],
            'status' => ['nullable', 'integer', Rule::in([0, 1])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'leave_name' => $validated['leave_name'],
            'code' => $validated['code'],
            'is_paid' => (bool) ($validated['is_paid'] ?? true),
            'requires_approval' => (bool) ($validated['requires_approval'] ?? true),
            'gender' => LeaveType::normalizeGender($validated['gender'] ?? null),
            'religion' => LeaveType::normalizeReligion($validated['religion'] ?? null),
            'status' => (int) ($validated['status'] ?? 1),
        ];
    }
}
