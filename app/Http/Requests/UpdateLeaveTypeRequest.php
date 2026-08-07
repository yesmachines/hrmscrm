<?php

namespace App\Http\Requests;

use App\Models\LeaveType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'is_paid',
            'requires_attachment',
            'requires_approval',
            'allow_once',
            'allow_balance',
            'requires_handover',
        ] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        foreach (['max_days', 'annual_limit', 'gender'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
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
        /** @var LeaveType $leaveType */
        $leaveType = $this->route('leave_type');

        return [
            'leave_name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('leave_types', 'code')->ignore($leaveType->id),
            ],
            'is_paid' => ['nullable', 'boolean'],
            'requires_attachment' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'max_days' => ['nullable', 'integer', 'min:0'],
            'annual_limit' => ['nullable', 'integer', 'min:0'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'all'])],
            'allow_once' => ['nullable', 'boolean'],
            'allow_balance' => ['nullable', 'boolean'],
            'status' => ['nullable', 'integer', Rule::in([0, 1])],
            'requires_handover' => ['nullable', 'boolean'],
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
            'requires_attachment' => (bool) ($validated['requires_attachment'] ?? false),
            'requires_approval' => (bool) ($validated['requires_approval'] ?? true),
            'max_days' => $validated['max_days'] ?? null,
            'annual_limit' => $validated['annual_limit'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'allow_once' => (bool) ($validated['allow_once'] ?? false),
            'allow_balance' => (bool) ($validated['allow_balance'] ?? true),
            'status' => (int) ($validated['status'] ?? 1),
            'requires_handover' => (bool) ($validated['requires_handover'] ?? false),
        ];
    }
}
