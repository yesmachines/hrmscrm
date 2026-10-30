<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\SalesCrm\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHrEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $documentTypeId = filter_var($this->input('document_type_id'), FILTER_VALIDATE_INT);
        $documentType = $documentTypeId !== false
            ? DocumentType::query()->find($documentTypeId)
            : null;
        $employeeId = filter_var($this->input('employee_id'), FILTER_VALIDATE_INT);
        $employee = $employeeId !== false
            ? Employee::query()->find($employeeId)
            : null;
        $fieldRequires = $documentType?->field_requires ?? [];

        $templateRules = ['nullable', 'integer'];
        if ($documentType !== null) {
            $templateRules[] = Rule::exists(DocumentTemplate::class, 'id')
                ->where('document_type_id', $documentType->id)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->where(function ($query) use ($employee) {
                    $query->whereNull('organisation_id');
                    if ($employee?->organisation_id !== null) {
                        $query->orWhere('organisation_id', $employee->organisation_id);
                    }
                });
        } else {
            $templateRules[] = 'prohibited';
        }

        return [
            'employee_id' => ['required', 'integer', Rule::exists(Employee::class, 'id')],
            'document_type_id' => [
                'required',
                'integer',
                Rule::exists(DocumentType::class, 'id')->whereNull('deleted_at'),
            ],
            'document_template_id' => $templateRules,
            'document_number' => ! empty($fieldRequires['ref_number'])
                ? ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9\/-]+\z/']
                : ['nullable', 'string', 'max:100', 'regex:/\A[A-Za-z0-9\/-]+\z/'],
            'document_title' => ['nullable', 'string', 'max:255'],
            'issue_date' => ! empty($fieldRequires['issue_date'])
                ? ['required', 'date', 'before_or_equal:today']
                : ['nullable', 'date', 'before_or_equal:today'],
            'expiry_date' => ! empty($fieldRequires['expiry_date'])
                ? ['required', 'date', 'after:today']
                : ['nullable', 'date', 'after:today'],
            'remarks' => ['nullable', 'string'],
            'file' => ! empty($fieldRequires['attachments'])
                ? ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']
                : ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'uid' => ! empty($fieldRequires['uid'])
                ? ['required', 'string', 'max:15', 'regex:/\A[0-9]{1,15}\z/']
                : ['prohibited'],
            'total_experience' => ! empty($fieldRequires['total_experience'])
                ? ['required', 'numeric', 'min:0', 'max:80']
                : ['prohibited'],
        ];
    }
}
