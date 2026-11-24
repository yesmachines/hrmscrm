<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class PreviewLeaveTemplateRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:salescrm.employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'values' => ['nullable', 'array'],
            'values.start_date' => ['nullable', 'date'],
            'values.end_date' => ['nullable', 'date'],
            'values.request_type' => ['nullable', 'string'],
            'values.total_days' => ['nullable', 'numeric'],
            'values.remarks' => ['nullable', 'string'],
            'values.local_contact_no' => ['nullable', 'string'],
            'values.traveling_outside_country' => ['nullable', 'boolean'],
            'values.destination' => ['nullable', 'string'],
            'values.travel_contact_no' => ['nullable', 'string'],
            'values.ticket_status' => ['nullable', 'string'],
            'values.handover_person_id' => ['nullable', 'integer', 'exists:salescrm.employees,id'],
            'values.handover_description' => ['nullable', 'string'],
            'values.handover_other_name' => ['nullable', 'string', 'max:255'],
            'values.handover_other_organisation' => ['nullable', 'string', 'max:255'],
            'values.handover_other_designation' => ['nullable', 'string', 'max:255'],
            'values.handover_other_contact_number' => ['nullable', 'string', 'regex:/^\+?[0-9]+$/', 'max:30'],
        ];
    }
}
