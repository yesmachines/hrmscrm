<?php

namespace App\Http\Requests\Leave;

use App\Concerns\LeaveRequestValidationRules;
use App\Models\SalesCrm\Employee;
use App\Services\WorkFromHomeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveApplicationRequest extends FormRequest
{
    use LeaveRequestValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $isApi = $this->routeIs('api.*');
        $isHrApply = $this->routeIs('api.v1.hr.leaves.apply');
        $isHrWfhApply = $this->routeIs('api.v1.hr.wfh.apply');
        $isHrPreview = $this->routeIs('api.v1.hr.leaves.validate');
        $isUpdate = $this->route()?->getActionMethod() === 'update';
        $isPreview = $this->route()?->getActionMethod() === 'validateForm';
        $statusRules = [
            'status' => [
                $isPreview ? 'nullable' : 'required',
                Rule::in(config('params.leave_request.statuses')),
            ],
            'remarks' => ['nullable', 'string'],
        ];

        if (! $isApi && $isUpdate && ! $this->has('start_date')) {
            return $statusRules;
        }

        $rules = $this->leaveApplicationRules($isApi && $isUpdate);
        if (! $isApi || $isHrApply || $isHrWfhApply) {
            $rules = [
                'employee_id' => [$isUpdate ? 'nullable' : 'required', 'integer', 'exists:salescrm.employees,id'],
                ...$rules,
                ...$statusRules,
            ];
            if (! $isUpdate && ! $isPreview) {
                $rules['status'] = [
                    ...($isHrWfhApply ? ['sometimes'] : []),
                    'required',
                    Rule::in(config('params.leave_request.active_statuses')),
                ];
            }
        } elseif ($isUpdate) {
            $rules['status'] = ['sometimes', 'in:applied'];
        }

        if ($isHrPreview) {
            $rules['employee_id'] = ['required', 'integer', 'exists:salescrm.employees,id'];
        }

        if ($this->routeIs('api.v1.hr.leaves.*')) {
            $rules['leave_type_id'] = [
                $isUpdate ? 'sometimes' : 'required',
                'integer',
                Rule::exists('leave_types', 'id')->whereNot('code', WorkFromHomeService::LEAVE_TYPE_CODE),
            ];
        }

        if ($isHrWfhApply) {
            $rules['leave_type_id'] = [
                'sometimes', 'required', 'integer',
                Rule::exists('leave_types', 'id')->where('code', WorkFromHomeService::LEAVE_TYPE_CODE),
            ];
        }

        if ($isPreview) {
            $employeeId = $isApi && ! $isHrPreview
                ? Employee::query()->where('user_id', $this->user()?->id)->value('id')
                : $this->integer('employee_id');
            $exists = Rule::exists('leave_requests', 'id')->where('employee_id', $employeeId);
            if ($isApi) {
                $exists->where('status', 'applied');
            }
            $rules['ignore_leave_request_id'] = ['nullable', 'integer', $exists];
        }

        return $rules;
    }
}
