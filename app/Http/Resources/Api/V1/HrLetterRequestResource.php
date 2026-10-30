<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin EmployeeDocument
 */
class HrLetterRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $employee = $this->relationLoaded('employee') ? $this->employee : null;
        $requestDetails = $this->relationLoaded('requestDetails')
            ? $this->requestDetails
            : collect();
        $latestFile = $this->relationLoaded('files')
            ? $this->files->first()
            : null;

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $employee?->user?->name ?? "Employee #{$this->employee_id}",
            'employee_email' => $employee?->user?->email,
            'emp_num' => $employee?->emp_num,
            'document_name' => $this->documentType?->document_name,
            'document_code' => $this->documentType?->document_code,
            'purpose' => $requestDetails->firstWhere('field_key', 'purpose')?->field_value ?? 'General Request',
            'details' => $requestDetails->firstWhere('field_key', 'details')?->field_value ?? $this->remarks,
            'to_address' => $requestDetails->firstWhere('field_key', 'to_address')?->field_value,
            'destination_country' => $requestDetails->firstWhere('field_key', 'destination_country')?->field_value,
            'visa_designation' => $requestDetails->firstWhere('field_key', 'visa_designation')?->field_value,
            'salary_amount' => $requestDetails->firstWhere('field_key', 'salary_amount')?->field_value,
            'bank_iban' => $requestDetails->firstWhere('field_key', 'bank_iban')?->field_value,
            'status' => $this->status,
            'status_label' => match ($this->status) {
                'under_review', 'submitted' => 'Pending Approval',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
                default => ucfirst($this->status),
            },
            'remarks' => $this->remarks,
            'applied_date' => $this->created_at?->format('d M Y'),
            'approved_date' => $this->status === 'approved' ? $this->updated_at?->format('d M Y') : null,
            'file_url' => $latestFile ? Storage::disk('public')->url($latestFile->file_path) : null,
            'download_url' => $latestFile ? Storage::disk('public')->url($latestFile->file_path) : null,
            'has_generated_file' => $latestFile !== null,
            'request_details' => $requestDetails->pluck('field_value', 'field_key'),
            'histories' => $this->whenLoaded(
                'histories',
                fn () => $this->histories->map(fn ($history) => [
                    'id' => $history->id,
                    'action_type' => $history->action_type,
                    'remarks' => $history->remarks,
                    'action_on' => $history->action_on?->format('d M Y H:i'),
                ]),
            ),
        ];
    }
}
