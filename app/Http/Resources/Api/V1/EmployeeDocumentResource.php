<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin EmployeeDocument
 */
class EmployeeDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latestFile = $this->relationLoaded('files')
            ? $this->files->first()
            : null;

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded(
                'employee',
                fn () => $this->employee?->user?->name,
            ),
            'document_type_id' => $this->document_type_id,
            'document_title' => $this->document_title,
            'document_number' => $this->document_number,
            'issue_date' => $this->issue_date?->format('Y-m-d'),
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'remarks' => $this->remarks,
            'status' => $this->status,
            'current_version' => $this->current_version,
            'file_url' => $latestFile ? Storage::disk('public')->url($latestFile->file_path) : null,
            'file_name' => $latestFile ? basename($latestFile->file_path) : null,
            'request_details' => $this->whenLoaded(
                'requestDetails',
                fn () => $this->requestDetails->pluck('field_value', 'field_key'),
            ),
        ];
    }
}
