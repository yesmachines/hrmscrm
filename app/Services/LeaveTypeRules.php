<?php

namespace App\Services;

use App\Models\LeaveType;

class LeaveTypeRules
{
    /**
     * @return array{validator?: string, fields?: list<string>, marital_status?: string, annual_request_limit?: int, requires_holiday_document?: bool}
     */
    public function definition(LeaveType $leaveType): array
    {
        return config('params.leave_type.special_rules.'.$leaveType->code, []);
    }

    public function isFestivalLeave(LeaveType $leaveType): bool
    {
        return ($this->definition($leaveType)['validator'] ?? null) === 'festival'
            || stripos($leaveType->leave_name, config('params.leave_type.festival_name_match')) !== false;
    }

    /**
     * @return array<string, array{label: string, storage_label?: string, type: string, required: bool, rules: list<string>, required_message?: string, help?: string, options_config?: string}>
     */
    public function fields(LeaveType $leaveType): array
    {
        $keys = $this->definition($leaveType)['fields'] ?? [];
        if ($this->isFestivalLeave($leaveType)) {
            $keys = array_unique([...$keys, ...config('params.leave_type.special_rules.FESTIVAL.fields')]);
        }

        return array_intersect_key(config('params.leave_type.detail_fields'), array_flip($keys));
    }

    /**
     * @return list<array{key: string, label: string, type: string, required: bool, help: string|null, options: array<string, array{label: string, group: string, max_days: int}>}>
     */
    public function fieldDefinitions(LeaveType $leaveType): array
    {
        $definitions = [];
        foreach ($this->fields($leaveType) as $key => $field) {
            $definitions[] = [
                'key' => $key,
                'label' => $field['label'],
                'type' => $field['type'],
                'required' => $field['required'],
                'help' => isset($field['help'])
                    ? str_replace(':months', (string) config('params.leave_type.parental_birth_window_months'), $field['help'])
                    : null,
                'options' => isset($field['options_config']) ? config($field['options_config']) : [],
            ];
        }

        return $definitions;
    }
}
