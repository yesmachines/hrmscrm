<?php

namespace App\Http\Requests;

use App\Models\LeaveType;
use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeavePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'requires_weekend_document',
            'carry_forward',
            'encashment',
            'requires_attachment',
            'probation_applicable',
        ] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        foreach ([
            'full_pay_days',
            'half_pay_days',
            'no_pay_days',
            'requires_document_after_days',
            'allocation_days',
            'remarks',
            'minimum_service_months',
        ] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'integer', Rule::exists(LeaveType::class, 'id')],
            'organisation_id' => ['required', 'integer', Rule::exists(Organisation::class, 'id')],
            'full_pay_days' => ['nullable', 'integer', 'min:0'],
            'half_pay_days' => ['nullable', 'integer', 'min:0'],
            'no_pay_days' => ['nullable', 'integer', 'min:0'],
            'requires_document_after_days' => ['nullable', 'integer', 'min:0'],
            'requires_weekend_document' => ['nullable', 'boolean'],
            'allocation_days' => ['nullable', 'integer', 'min:0'],
            'carry_forward' => ['nullable', 'boolean'],
            'encashment' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string'],
            'requires_attachment' => ['nullable', 'boolean'],
            'probation_applicable' => ['nullable', 'boolean'],
            'minimum_service_months' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'leave_type_id' => $validated['leave_type_id'],
            'organisation_id' => $validated['organisation_id'],
            'full_pay_days' => $validated['full_pay_days'] ?? null,
            'half_pay_days' => $validated['half_pay_days'] ?? null,
            'no_pay_days' => $validated['no_pay_days'] ?? null,
            'requires_document_after_days' => $validated['requires_document_after_days'] ?? null,
            'requires_weekend_document' => (bool) ($validated['requires_weekend_document'] ?? false),
            'allocation_days' => $validated['allocation_days'] ?? null,
            'carry_forward' => (bool) ($validated['carry_forward'] ?? false),
            'encashment' => (bool) ($validated['encashment'] ?? false),
            'remarks' => $validated['remarks'] ?? null,
            'requires_attachment' => (bool) ($validated['requires_attachment'] ?? false),
            'probation_applicable' => (bool) ($validated['probation_applicable'] ?? false),
            'minimum_service_months' => $validated['minimum_service_months'] ?? null,
        ];
    }
}
