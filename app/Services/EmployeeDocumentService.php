<?php

namespace App\Services;

use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentFile;
use App\Models\EmployeeDocumentHistory;
use App\Models\EmployeeProfile;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Shared methods are used by Admin and API controllers; change them carefully to preserve both workflows.
 */
class EmployeeDocumentService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        DocumentType $documentType,
        array $attributes,
        UploadedFile $file,
        ?int $uploadedBy,
        bool $uploadedByHr,
    ): EmployeeDocument {
        return DB::transaction(function () use ($documentType, $attributes, $file, $uploadedBy, $uploadedByHr) {
            $document = EmployeeDocument::query()->create($attributes);
            $status = $document->status;

            $fileChangeNotes = $uploadedByHr
                ? ($status === 'submitted' ? 'Uploaded document awaiting HR approval' : 'Uploaded by HR')
                : 'Initial upload';

            EmployeeDocumentFile::query()->create([
                'employee_document_id' => $document->id,
                'version_no' => '1.0',
                'file_path' => $file->store('employee_documents', 'public'),
                'uploaded_by' => $uploadedBy,
                'uploaded_date' => Carbon::now(),
                'change_notes' => $fileChangeNotes,
            ]);

            $historyRemarks = $uploadedByHr
                ? ($status === 'submitted'
                    ? 'Document uploaded and awaiting HR approval'
                    : 'Document uploaded and approved by HR')
                : 'Document submitted by employee';

            EmployeeDocumentHistory::query()->create([
                'employee_document_id' => $document->id,
                'action_type' => $status,
                'remarks' => $historyRemarks,
                'done_by' => $uploadedBy,
                'action_on' => Carbon::now(),
            ]);

            if ($documentType->requires_reminder && $document->expiry_date) {
                $reminderAttributes = [
                    'employee_document_id' => $document->id,
                    'reminder_date' => Carbon::parse($document->expiry_date)->subDays(90),
                    'days_before' => 90,
                ];

                if ($uploadedByHr) {
                    $reminderAttributes['status'] = 'pending';
                }

                $document->reminders()->create($reminderAttributes);
            }

            if ($uploadedByHr && $status === 'approved') {
                $this->syncWithProfile($document, $documentType);
            }

            return $document;
        });
    }

    /**
     * Auto-sync approved document metadata with the employee profile.
     */
    public function syncWithProfile(EmployeeDocument $document, ?DocumentType $documentType = null): void
    {
        $profile = EmployeeProfile::query()
            ->where('employee_id', $document->employee_id)
            ->first();

        if (! $profile) {
            return;
        }

        $code = $documentType?->document_code ?? $document->documentType?->document_code;

        if ($code === 'passport' && $document->expiry_date) {
            $profile->update(['dob_passport' => $document->issue_date]);
        } elseif ($code === 'education') {
            $profile->update(['highest_education' => $document->document_title]);
        }
    }
}
