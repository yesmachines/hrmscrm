<?php

namespace App\Services;

use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentFile;
use App\Models\EmployeeDocumentHistory;
use App\Models\EmployeeProfile;
use App\Support\SalesCrmRoles;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Shared methods are used by Admin and API controllers; change them carefully to preserve both workflows.
 */
class EmployeeDocumentService
{
    /**
     * @var array<int, list<string>>
     */
    private array $roleNamesByUserId = [];

    public function list(Request $request, ?int $employeeId = null, int $perPage = 15, bool $applyHrOnlyFilters = false): LengthAwarePaginator
    {
        $query = EmployeeDocument::query()
            ->with([
                'documentType.category',
                'documentType.documentTemplates' => fn ($q) => $q->where('status', 1),
                'documentTemplate',
                'files' => fn ($q) => $q->orderByDesc('id'),
                'requestDetails',
            ]);

        if ($employeeId !== null) {
            $query->where('employee_id', $employeeId);
        }

        if ($applyHrOnlyFilters && $request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($applyHrOnlyFilters && $request->filled('document_type_id')) {
            $query->where('document_type_id', $request->input('document_type_id'));
        }

        if ($request->filled('category_id')) {
            $query->whereHas('documentType', function ($q) use ($request) {
                $q->where('category_id', $request->input('category_id'));
            });
        } elseif ($request->filled('category_code')) {
            $query->whereHas('documentType.category', function ($q) use ($request) {
                $q->where('short_code', $request->input('category_code'));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('organisation_id')) {
            $query->where('organisation_id', $request->input('organisation_id'));
        }

        if ($request->filled('year')) {
            $year = (int) $request->input('year');
            $query->where(function ($q) use ($year) {
                $q->whereYear('issue_date', $year)
                    ->orWhereYear('created_at', $year);
            });
        }

        if ($request->filled('month')) {
            $month = (int) $request->input('month');
            $query->where(function ($q) use ($month) {
                $q->whereMonth('issue_date', $month)
                    ->orWhereMonth('created_at', $month);
            });
        }

        if ($request->filled('date')) {
            $date = $request->input('date');
            $query->where(function ($q) use ($date) {
                $q->whereDate('issue_date', $date)
                    ->orWhereDate('created_at', $date);
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('document_title', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%")
                    ->orWhereHas('documentType', function ($sub) use ($search) {
                        $sub->where('document_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->orderByDesc('id')->paginate(max(1, min(100, $perPage)));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        DocumentType $documentType,
        array $attributes,
        ?UploadedFile $file,
        ?int $uploadedBy,
        bool $uploadedByHr,
    ): EmployeeDocument {
        return DB::transaction(function () use ($documentType, $attributes, $file, $uploadedBy, $uploadedByHr) {
            $document = EmployeeDocument::query()->create($attributes);
            $status = $document->status;

            if ($file) {
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
            }

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

    public function updateStatus(EmployeeDocument $document, string $status, string $remarks, ?int $doneBy): void
    {
        DB::transaction(function () use ($document, $status, $remarks, $doneBy) {
            $document->update([
                'status' => $status,
                'remarks' => $remarks,
            ]);

            EmployeeDocumentHistory::query()->create([
                'employee_document_id' => $document->id,
                'action_type' => $status,
                'remarks' => $remarks,
                'done_by' => $doneBy,
                'action_on' => Carbon::now(),
            ]);

            if ($status === 'approved') {
                $this->syncWithProfile($document);
            }
        });
    }

    public function canEdit(EmployeeDocument $document, int $userId, ?int $employeeId = null): bool
    {
        $document->loadMissing('documentType.category');

        if (! $document->documentType?->editable_before_approval
            || ! in_array($document->status, ['draft', 'submitted', 'under_review'], true)
            || $document->created_by === null) {
            return false;
        }

        if ($document->documentType->category?->short_code === 'letter_requests'
            && $document->created_at
            && $document->created_at->copy()->addMonths(3)->isPast()) {
            return false;
        }

        if ($employeeId !== null) {
            return (int) $document->employee_id === $employeeId
                && (int) $document->created_by === $userId
                && array_intersect(
                    SalesCrmRoles::LOGIN_ROLES,
                    $this->roleNamesForUser((int) $document->created_by),
                ) === [];
        }

        $allowedRoles = SalesCrmRoles::LOGIN_ROLES;
        $creatorRoles = array_intersect($allowedRoles, $this->roleNamesForUser((int) $document->created_by));
        $editorRoles = array_intersect($allowedRoles, $this->roleNamesForUser($userId));

        return array_intersect($creatorRoles, $editorRoles) !== [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $requestDetails
     */
    public function updateDocument(
        EmployeeDocument $document,
        array $attributes,
        array $requestDetails,
        ?UploadedFile $file,
        int $updatedBy,
        string $changeNotes,
    ): void {
        DB::transaction(function () use ($document, $attributes, $requestDetails, $file, $updatedBy, $changeNotes) {
            $document->update($attributes);

            if ($file !== null) {
                $currentVersion = (float) ($document->current_version ?? 1.0);
                $newVersion = number_format($currentVersion + 0.1, 1);

                EmployeeDocumentFile::query()->create([
                    'employee_document_id' => $document->id,
                    'version_no' => $newVersion,
                    'file_path' => $file->store('employee_documents', 'public'),
                    'uploaded_by' => $updatedBy,
                    'uploaded_date' => Carbon::now(),
                    'change_notes' => $changeNotes,
                ]);

                $document->update(['current_version' => $newVersion]);
            }

            foreach (['uid', 'total_experience'] as $fieldKey) {
                if (! array_key_exists($fieldKey, $requestDetails)) {
                    continue;
                }

                $fieldValue = $requestDetails[$fieldKey];
                if ($fieldValue === null || $fieldValue === '') {
                    $document->requestDetails()->where('field_key', $fieldKey)->delete();

                    continue;
                }

                $document->requestDetails()->updateOrCreate(
                    ['field_key' => $fieldKey],
                    [
                        'field_name' => str($fieldKey)->headline()->toString(),
                        'field_value' => (string) $fieldValue,
                    ],
                );
            }

            EmployeeDocumentHistory::query()->create([
                'employee_document_id' => $document->id,
                'action_type' => $document->status,
                'remarks' => $changeNotes,
                'done_by' => $updatedBy,
                'action_on' => Carbon::now(),
            ]);
        });
    }

    /**
     * @return list<string>
     */
    private function roleNamesForUser(int $userId): array
    {
        return $this->roleNamesByUserId[$userId] ??= SalesCrmRoles::roleNamesForUser($userId);
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
