<?php

namespace App\Http\Requests\Leave;

use App\Models\LeaveTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLeaveTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'organisation_id' => ['nullable', 'integer', 'exists:organisations,id'],
            'template_name' => ['required', 'string', 'max:255'],
            'template_code' => ['required', 'string', 'max:1000000'],
            'status' => ['required', 'integer', Rule::in([0, 1])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || (int) $this->input('status') !== 1) {
                return;
            }

            $query = LeaveTemplate::query()
                ->where('leave_type_id', $this->integer('leave_type_id'))
                ->where('status', 1);
            $leaveTemplate = $this->route('leave_template');
            if ($leaveTemplate instanceof LeaveTemplate) {
                $query->where('id', '<>', $leaveTemplate->id);
            }

            if ($this->filled('organisation_id')) {
                $query->where('organisation_id', $this->integer('organisation_id'));
            } else {
                $query->whereNull('organisation_id');
            }

            if ($query->exists()) {
                $validator->errors()->add(
                    'leave_type_id',
                    'An active template already exists for this leave type and organisation.',
                );
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->validated();
    }
}
