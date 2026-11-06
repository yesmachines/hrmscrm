<?php

namespace App\Http\Requests\Leave;

use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeavePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'full_pay_days',
            'half_pay_days',
            'no_pay_days',
            'annual_limit',
            'carry_forward_weeks',
            'requires_document_after_days',
            'remarks',
            'minimum_service_months',
        ] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        $excludeRules = $this->input('exclude_rules');
        if (is_string($excludeRules)) {
            $excludeRules = trim($excludeRules);

            if ($excludeRules === '') {
                $this->merge(['exclude_rules' => null]);
            } else {
                $decodedRules = json_decode($excludeRules, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedRules)) {
                    $this->merge(['exclude_rules' => $decodedRules]);
                }
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $policyId = $this->route('leave_policy') instanceof LeavePolicy
            ? $this->route('leave_policy')->id
            : $this->route('leave_policy');

        return [
            'leave_type_id' => [
                'required',
                'integer',
                Rule::exists(LeaveType::class, 'id'),
                Rule::unique(LeavePolicy::class, 'leave_type_id')->ignore($policyId),
            ],
            'organisation_id' => ['required', 'integer', Rule::exists(Organisation::class, 'id')],
            'annual_limit' => ['nullable', 'integer', 'min:0'],
            'allow_once' => ['nullable', 'boolean'],
            'allow_balance' => ['nullable', 'boolean'],
            'full_pay_days' => ['nullable', 'integer', 'min:0'],
            'half_pay_days' => ['nullable', 'integer', 'min:0'],
            'no_pay_days' => ['nullable', 'integer', 'min:0'],
            'requires_document_after_days' => ['nullable', 'integer', 'min:0'],
            'requires_weekend_document' => ['nullable', 'boolean'],
            'requires_handover' => ['nullable', 'boolean'],
            'carry_forward' => ['nullable', 'boolean'],
            'carry_forward_weeks' => ['nullable', 'integer', 'min:1'],
            'encashment' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string'],
            'requires_attachment' => ['nullable', 'boolean'],
            'probation_applicable' => ['nullable', 'boolean'],
            'minimum_service_months' => ['nullable', 'integer', 'min:0'],
            'exclude_rules' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exclude_rules.array' => 'Exclusion rules must be a valid JSON object or array.',
            'carry_forward_weeks.min' => 'Carry-forward weeks must be at least 1.',
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
            'annual_limit' => $validated['annual_limit'] ?? null,
            'allow_once' => (bool) ($validated['allow_once'] ?? false),
            'allow_balance' => (bool) ($validated['allow_balance'] ?? true),
            'full_pay_days' => $validated['full_pay_days'] ?? null,
            'half_pay_days' => $validated['half_pay_days'] ?? null,
            'no_pay_days' => $validated['no_pay_days'] ?? null,
            'requires_document_after_days' => $validated['requires_document_after_days'] ?? null,
            'requires_weekend_document' => (bool) ($validated['requires_weekend_document'] ?? false),
            'requires_handover' => (bool) ($validated['requires_handover'] ?? false),
            'carry_forward' => (bool) ($validated['carry_forward'] ?? false),
            'carry_forward_weeks' => $validated['carry_forward_weeks'] ?? null,
            'encashment' => (bool) ($validated['encashment'] ?? false),
            'remarks' => $validated['remarks'] ?? null,
            'requires_attachment' => (bool) ($validated['requires_attachment'] ?? false),
            'probation_applicable' => (bool) ($validated['probation_applicable'] ?? false),
            'minimum_service_months' => $validated['minimum_service_months'] ?? null,
            'exclude_rules' => $validated['exclude_rules'] ?? null,
        ];
    }
}
