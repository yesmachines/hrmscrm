<?php

namespace App\Concerns;

use Illuminate\Validation\Rule;

trait LeaveRequestValidationRules
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function leaveApplicationRules(bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $rules = [
            'leave_type_id' => [$presence, 'integer', 'exists:leave_types,id'],
            'start_date' => [$presence, 'date'],
            'end_date' => [$presence, 'date'],
            'request_type' => ['nullable', Rule::in(config('params.leave_request.request_types'))],
            'total_days' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'handover_person_id' => ['nullable', 'exists:salescrm.employees,id'],
            'handover_description' => ['nullable', 'string'],
            'handover_other_name' => ['nullable', 'string', 'max:255'],
            'handover_other_organisation' => ['nullable', 'string', 'max:255'],
            'handover_other_designation' => ['nullable', 'string', 'max:255'],
            'handover_other_contact_number' => ['nullable', 'string', 'regex:/^\+?[0-9]+$/', 'max:30'],
            'emergency_contact_no' => ['nullable', 'string', 'regex:/^\+?[0-9]+$/'],
            'travel_contact_no' => ['nullable', 'string', 'regex:/^\+?[0-9]+$/'],
            'traveling_outside_country' => ['nullable', 'boolean'],
            'destination' => ['nullable', 'string'],
            'certificate' => [
                'nullable', 'file',
                'mimes:'.implode(',', config('params.leave_request.certificate_mimes')),
                'max:'.config('params.leave_request.certificate_max_kb'),
            ],
            'signature' => [
                'nullable', 'image',
                'mimes:'.implode(',', config('params.leave_request.signature_mimes')),
                'max:'.config('params.leave_request.signature_max_kb'),
            ],
            'handover_giver_signature' => [
                'nullable', 'image',
                'mimes:'.implode(',', config('params.leave_request.signature_mimes')),
                'max:'.config('params.leave_request.signature_max_kb'),
            ],
            'handover_receiver_signature' => [
                'nullable', 'image',
                'mimes:'.implode(',', config('params.leave_request.signature_mimes')),
                'max:'.config('params.leave_request.signature_max_kb'),
            ],
            'declaration' => ['nullable', 'string'],
        ];

        foreach (config('params.leave_type.detail_fields') as $key => $field) {
            $rules[$key] = $field['rules'];
        }

        return $rules;
    }
}
