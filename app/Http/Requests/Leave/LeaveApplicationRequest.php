<?php

namespace App\Http\Requests\Leave;

use App\Concerns\LeaveRequestValidationRules;
use App\Models\SalesCrm\Employee;
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
        if (! $isApi) {
            $rules = [
                'employee_id' => [$isUpdate ? 'nullable' : 'required', 'integer', 'exists:salescrm.employees,id'],
                ...$rules,
                ...$statusRules,
            ];
            if (! $isUpdate && ! $isPreview) {
                $rules['status'] = ['required', Rule::in(config('params.leave_request.active_statuses'))];
            }
        } elseif ($isUpdate) {
            $rules['status'] = ['sometimes', 'in:applied'];
        }

        if ($isPreview) {
            $employeeId = $isApi
                ? Employee::query()->where('user_id', $this->user()?->id)->value('id')
                : $this->input('employee_id');
            $exists = Rule::exists('leave_requests', 'id')->where('employee_id', $employeeId);
            if ($isApi) {
                $exists->where('status', 'applied');
            }
            $rules['ignore_leave_request_id'] = ['nullable', 'integer', $exists];
        }

        return $rules;
    }
}
