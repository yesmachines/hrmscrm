<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentCategory;
use App\Models\DocumentReminder;
use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentFile;
use App\Models\EmployeeDocumentHistory;
use App\Models\EmployeeRequestDetail;
use App\Models\SalesCrm\Employee;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    use ApiResponse;

    /**
     * Resolve employee from current authenticated user.
     */
    protected function getEmployee(Request $request): ?Employee
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return Employee::query()->where('user_id', $user->id)->first();
    }

    /**
     * Categories Hub (Matching Figma Main Documents Hub).
     */
    public function categories(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $categories = DocumentCategory::query()
            ->with(['documentTypes' => function ($query) {
                $query->orderBy('id');
            }])
            ->where('status', 1)
            ->get();

        $data = $categories->map(function (DocumentCategory $cat) use ($employee) {
            $typeIds = $cat->documentTypes->pluck('id');

            $docCount = EmployeeDocument::query()
                ->where('employee_id', $employee->id)
                ->whereIn('document_type_id', $typeIds)
                ->count();

            $subcategories = $cat->documentTypes->pluck('document_name')->values();

            return [
                'id' => $cat->id,
                'category_name' => $cat->category_name,
                'short_code' => $cat->short_code,
                'document_count' => $docCount,
                'badge_text' => "{$docCount} ".($docCount === 1 ? 'DOCUMENT' : 'DOCUMENTS'),
                'subcategories' => $subcategories,
            ];
        });

        return $this->successResponse($data, 'Categories retrieved successfully.');
    }

    /**
     * Document Types options for Upload Bottom Sheet (3x3 grid).
     */
    public function types(Request $request): JsonResponse
    {
        $query = DocumentType::query()->with([
            'category:id,category_name,short_code',
            'documentTemplates' => fn ($q) => $q->where('status', 1),
        ]);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        } elseif ($request->filled('category_code')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('short_code', $request->input('category_code'));
            });
        }

        $types = $query->orderBy('id')->get()->map(function (DocumentType $type) {
            return [
                'id' => $type->id,
                'category_id' => $type->category_id,
                'category_name' => $type->category?->category_name,
                'category_code' => $type->category?->short_code,
                'document_name' => $type->document_name,
                'document_code' => $type->document_code,
                'requires_number' => (bool) $type->requires_number,
                'requires_expiry' => (bool) $type->requires_expiry,
                'editable_before_approval' => (bool) $type->editable_before_approval,
                'requires_hr_approval' => (bool) $type->requires_hr_approval,
                'requires_reminder' => (bool) $type->requires_reminder,
                'record_source' => $type->record_source,
                'requires_attachments' => (bool) $type->requires_attachments,
                'templates' => $type->documentTemplates->map(fn ($t) => [
                    'id' => $t->id,
                    'template_name' => $t->template_name,
                    'template_code' => $t->template_code,
                ])->values(),
            ];
        });

        return $this->successResponse($types, 'Document types retrieved successfully.');
    }

    /**
     * Filtered Document List (Matching Figma Category Document List).
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $query = EmployeeDocument::query()
            ->with([
                'documentType.category',
                'documentTemplate',
                'files' => fn ($q) => $q->orderByDesc('id'),
                'requestDetails',
            ])
            ->where('employee_id', $employee->id);

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

        $documents = $query->orderByDesc('id')->get()->map(function (EmployeeDocument $doc) {
            $latestFile = $doc->files->first();
            $fileUrl = $latestFile ? Storage::disk('public')->url($latestFile->file_path) : null;

            return [
                'id' => $doc->id,
                'document_type_id' => $doc->document_type_id,
                'document_name' => $doc->documentType?->document_name,
                'document_code' => $doc->documentType?->document_code,
                'category_name' => $doc->documentType?->category?->category_name,
                'category_code' => $doc->documentType?->category?->short_code,
                'document_title' => $doc->document_title,
                'document_number' => $doc->document_number,
                'issue_date' => $doc->issue_date?->format('d M Y'),
                'issue_date_raw' => $doc->issue_date?->format('Y-m-d'),
                'expiry_date' => $doc->expiry_date?->format('d M Y'),
                'expiry_date_raw' => $doc->expiry_date?->format('Y-m-d'),
                'status' => $doc->status,
                'status_label' => match ($doc->status) {
                    'approved' => 'Approved',
                    'under_review' => 'Pending Approval',
                    'submitted' => 'Pending Approval',
                    'rejected' => 'Rejected',
                    'draft' => 'Draft',
                    default => ucfirst($doc->status),
                },
                'status_color' => match ($doc->status) {
                    'approved' => 'green',
                    'under_review', 'submitted' => 'orange',
                    'rejected' => 'red',
                    default => 'gray',
                },
                'remarks' => $doc->remarks,
                'current_version' => $doc->current_version ?? '1.0',
                'file_url' => $fileUrl,
                'can_edit' => in_array($doc->status, ['draft', 'submitted']) || (bool) $doc->documentType?->editable_before_approval,
                'created_at' => $doc->created_at?->format('d M Y'),
            ];
        });

        return $this->successResponse($documents, 'Documents retrieved successfully.');
    }

    /**
     * Upload / Submit New Document.
     */
    public function store(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'document_type_id' => 'required|exists:document_types,id',
            'document_number' => 'nullable|string|max:100',
            'document_title' => 'nullable|string|max:255',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'remarks' => 'nullable|string',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $docType = DocumentType::query()->findOrFail($request->input('document_type_id'));

        return DB::transaction(function () use ($request, $employee, $docType) {
            $initialStatus = $docType->requires_hr_approval ? 'submitted' : 'approved';

            $document = EmployeeDocument::query()->create([
                'employee_id' => $employee->id,
                'document_type_id' => $docType->id,
                'document_number' => $request->input('document_number'),
                'document_title' => $request->input('document_title') ?? $docType->document_name,
                'issue_date' => $request->input('issue_date'),
                'expiry_date' => $request->input('expiry_date'),
                'remarks' => $request->input('remarks'),
                'current_version' => '1.0',
                'created_by' => $request->user()?->id,
                'status' => $initialStatus,
            ]);

            // Save File
            if ($request->hasFile('file')) {
                $filePath = $request->file('file')->store('employee_documents', 'public');
                EmployeeDocumentFile::query()->create([
                    'employee_document_id' => $document->id,
                    'version_no' => '1.0',
                    'file_path' => $filePath,
                    'uploaded_by' => $request->user()?->id,
                    'uploaded_date' => Carbon::now(),
                    'change_notes' => 'Initial upload',
                ]);
            }

            // Record History
            EmployeeDocumentHistory::query()->create([
                'employee_document_id' => $document->id,
                'action_type' => $initialStatus,
                'remarks' => 'Document submitted by employee',
                'done_by' => $request->user()?->id,
                'action_on' => Carbon::now(),
            ]);

            // Set 3-month reminder if applicable
            if ($docType->requires_reminder && $request->filled('expiry_date')) {
                $expiryDate = Carbon::parse($request->input('expiry_date'));
                $reminderDate = (clone $expiryDate)->subDays(90);

                DocumentReminder::query()->create([
                    'employee_document_id' => $document->id,
                    'reminder_date' => $reminderDate,
                    'days_before' => 90,
                ]);
            }

            return $this->successResponse([
                'id' => $document->id,
                'status' => $document->status,
                'message' => 'Document uploaded successfully.',
            ], 'Document uploaded successfully.', 201);
        });
    }

    /**
     * Get Document Details with version history.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $employee = $this->getEmployee($request);
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $doc = EmployeeDocument::query()
            ->with([
                'documentType.category',
                'documentTemplate',
                'files' => fn ($q) => $q->orderByDesc('id'),
                'histories' => fn ($q) => $q->orderByDesc('id'),
                'requestDetails',
            ])
            ->where('employee_id', $employee->id)
            ->findOrFail($id);

        $files = $doc->files->map(fn ($f) => [
            'id' => $f->id,
            'version_no' => $f->version_no,
            'file_path' => $f->file_path,
            'file_url' => Storage::disk('public')->url($f->file_path),
            'uploaded_date' => $f->uploaded_date?->format('d M Y H:i'),
            'change_notes' => $f->change_notes,
        ]);

        $histories = $doc->histories->map(fn ($h) => [
            'id' => $h->id,
            'action_type' => $h->action_type,
            'remarks' => $h->remarks,
            'action_on' => $h->action_on?->format('d M Y H:i'),
        ]);

        return $this->successResponse([
            'id' => $doc->id,
            'document_name' => $doc->documentType?->document_name,
            'category_name' => $doc->documentType?->category?->category_name,
            'document_title' => $doc->document_title,
            'document_number' => $doc->document_number,
            'issue_date' => $doc->issue_date?->format('d M Y'),
            'expiry_date' => $doc->expiry_date?->format('d M Y'),
            'remarks' => $doc->remarks,
            'status' => $doc->status,
            'current_version' => $doc->current_version,
            'template' => $doc->documentTemplate ? [
                'id' => $doc->documentTemplate->id,
                'template_name' => $doc->documentTemplate->template_name,
                'template_code' => $doc->documentTemplate->template_code,
            ] : null,
            'files' => $files,
            'histories' => $histories,
            'request_details' => $doc->requestDetails->pluck('field_value', 'field_key'),
        ], 'Document details retrieved successfully.');
    }

    /**
     * Edit / Update Document (e.g. before approval or replace file).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $employee = $this->getEmployee($request);
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $document = EmployeeDocument::query()
            ->with('documentType')
            ->where('employee_id', $employee->id)
            ->findOrFail($id);

        if (! in_array($document->status, ['draft', 'submitted', 'rejected'])) {
            return $this->errorResponse('Approved documents cannot be edited directly. Please submit a new version.', 403);
        }

        $validator = Validator::make($request->all(), [
            'document_number' => 'nullable|string|max:100',
            'document_title' => 'nullable|string|max:255',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'remarks' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        return DB::transaction(function () use ($request, $document) {
            $document->update([
                'document_number' => $request->input('document_number', $document->document_number),
                'document_title' => $request->input('document_title', $document->document_title),
                'issue_date' => $request->input('issue_date', $document->issue_date),
                'expiry_date' => $request->input('expiry_date', $document->expiry_date),
                'remarks' => $request->input('remarks', $document->remarks),
                'status' => 'submitted',
            ]);

            if ($request->hasFile('file')) {
                $currentVer = (float) ($document->current_version ?? 1.0);
                $newVer = number_format($currentVer + 0.1, 1);

                $filePath = $request->file('file')->store('employee_documents', 'public');
                EmployeeDocumentFile::query()->create([
                    'employee_document_id' => $document->id,
                    'version_no' => $newVer,
                    'file_path' => $filePath,
                    'uploaded_by' => $request->user()?->id,
                    'uploaded_date' => Carbon::now(),
                    'change_notes' => 'Updated document file',
                ]);

                $document->update(['current_version' => $newVer]);
            }

            EmployeeDocumentHistory::query()->create([
                'employee_document_id' => $document->id,
                'action_type' => 'submitted',
                'remarks' => 'Document details updated by employee',
                'done_by' => $request->user()?->id,
                'action_on' => Carbon::now(),
            ]);

            return $this->successResponse([
                'id' => $document->id,
                'status' => $document->status,
                'current_version' => $document->current_version,
            ], 'Document updated successfully.');
        });
    }

    /**
     * HR Policy Documents Screen (Matching iPhone 16 Plus - 11).
     */
    public function policies(Request $request): JsonResponse
    {
        $policies = EmployeeDocument::query()
            ->with(['documentType', 'files' => fn ($q) => $q->orderByDesc('id')])
            ->whereHas('documentType.category', function ($q) {
                $q->where('short_code', 'hr_docs');
            })
            ->orderBy('id')
            ->get()
            ->map(function (EmployeeDocument $doc) {
                $latestFile = $doc->files->first();
                $fileUrl = $latestFile ? Storage::disk('public')->url($latestFile->file_path) : null;

                return [
                    'id' => $doc->id,
                    'policy_name' => $doc->document_title ?: $doc->documentType?->document_name,
                    'document_code' => $doc->documentType?->document_code,
                    'version' => 'Version '.($doc->current_version ?? '1.0'),
                    'updated_at' => $doc->updated_at ? $doc->updated_at->format('d M Y') : '15 Jan 2026',
                    'file_url' => $fileUrl,
                ];
            });

        // If no policies in DB yet, supply standard defaults
        if ($policies->isEmpty()) {
            $policies = collect([
                [
                    'id' => 1,
                    'policy_name' => 'HR Policy 2026',
                    'document_code' => 'hr_policy',
                    'version' => 'Version 1.0',
                    'updated_at' => '15 Jan 2026',
                    'file_url' => null,
                ],
                [
                    'id' => 2,
                    'policy_name' => 'Leave Policy 2026',
                    'document_code' => 'leave_policy',
                    'version' => 'Version 1.0',
                    'updated_at' => '27 Jan 2026',
                    'file_url' => null,
                ],
                [
                    'id' => 3,
                    'policy_name' => 'WFH Policy 2026',
                    'document_code' => 'wfh_policy',
                    'version' => 'Version 1.0',
                    'updated_at' => '31 Jan 2026',
                    'file_url' => null,
                ],
            ]);
        }

        return $this->successResponse($policies, 'HR Policies retrieved successfully.');
    }

    /**
     * Letter Requests List (Matching iPhone 16 Plus - 12).
     */
    public function letters(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $letters = EmployeeDocument::query()
            ->with([
                'documentType',
                'documentTemplate',
                'requestDetails',
                'files' => fn ($q) => $q->orderByDesc('id'),
            ])
            ->where('employee_id', $employee->id)
            ->whereHas('documentType.category', function ($q) {
                $q->where('short_code', 'letter_requests');
            })
            ->orderByDesc('id')
            ->get()
            ->map(function (EmployeeDocument $doc) {
                $purpose = $doc->requestDetails->firstWhere('field_key', 'purpose')?->field_value ?? 'Official Purpose';
                $latestFile = $doc->files->first();

                return [
                    'id' => $doc->id,
                    'document_name' => $doc->documentType?->document_name,
                    'document_code' => $doc->documentType?->document_code,
                    'purpose' => $purpose,
                    'apply_date' => $doc->created_at?->format('d M Y') ?? '16 JAN 2026',
                    'approved_date' => $doc->status === 'approved' ? ($doc->updated_at?->format('d M Y') ?? '18 JAN 2026') : null,
                    'status' => $doc->status,
                    'status_label' => ucfirst($doc->status),
                    'file_url' => $latestFile ? Storage::disk('public')->url($latestFile->file_path) : null,
                ];
            });

        return $this->successResponse($letters, 'Letter requests retrieved successfully.');
    }

    /**
     * Submit Letter Request (NOC, Salary Certificate, Salary Transfer Letter, Pay Slip).
     */
    public function requestLetter(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'document_type_id' => 'required|exists:document_types,id',
            'document_template_id' => 'nullable|exists:document_templates,id',
            'purpose' => 'required|string|max:255',
            'details' => 'nullable|string',
            'to_address' => 'nullable|string|max:255',
            'visa_designation' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $docType = DocumentType::query()->findOrFail($request->input('document_type_id'));

        return DB::transaction(function () use ($request, $employee, $docType) {
            $templateId = $request->input('document_template_id')
                ?? $docType->documentTemplates()->where('status', 1)->value('id');

            $document = EmployeeDocument::query()->create([
                'employee_id' => $employee->id,
                'document_type_id' => $docType->id,
                'document_template_id' => $templateId,
                'document_title' => $docType->document_name.' Request',
                'status' => 'submitted',
                'created_by' => $request->user()?->id,
            ]);

            $fields = [
                'purpose' => $request->input('purpose'),
                'details' => $request->input('details'),
                'to_address' => $request->input('to_address'),
                'visa_designation' => $request->input('visa_designation'),
            ];

            foreach ($fields as $key => $value) {
                if ($value !== null) {
                    EmployeeRequestDetail::query()->create([
                        'employee_document_id' => $document->id,
                        'field_name' => ucfirst(str_replace('_', ' ', $key)),
                        'field_key' => $key,
                        'field_value' => $value,
                    ]);
                }
            }

            EmployeeDocumentHistory::query()->create([
                'employee_document_id' => $document->id,
                'action_type' => 'submitted',
                'remarks' => 'Letter request submitted by employee',
                'done_by' => $request->user()?->id,
                'action_on' => Carbon::now(),
            ]);

            return $this->successResponse([
                'id' => $document->id,
                'status' => $document->status,
                'message' => 'Letter request submitted successfully.',
            ], 'Letter request submitted successfully.', 201);
        });
    }

    /**
     * Letter Request Details Bottom Sheet (Matching iPhone 16 Plus - 13).
     */
    public function letterDetails(Request $request, int $id): JsonResponse
    {
        $employee = $this->getEmployee($request);
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $doc = EmployeeDocument::query()
            ->with(['documentType', 'documentTemplate', 'requestDetails', 'histories'])
            ->where('employee_id', $employee->id)
            ->findOrFail($id);

        $details = $doc->requestDetails->pluck('field_value', 'field_key');

        return $this->successResponse([
            'id' => $doc->id,
            'title' => ($doc->documentType?->document_name ?? 'Document').' Request Details',
            'purpose' => $details['purpose'] ?? 'Employment Visa Process',
            'details' => $details['details'] ?? 'Requesting a No Objection Certificate for embassy submission regarding the new employment visa renewal and international mobility transfer.',
            'to_address' => $details['to_address'] ?? null,
            'visa_designation' => $details['visa_designation'] ?? null,
            'applied_date' => $doc->created_at?->format('d M Y') ?? '12 JAN 2026',
            'status' => ucfirst($doc->status),
            'remarks' => $doc->remarks,
            'template' => $doc->documentTemplate ? [
                'id' => $doc->documentTemplate->id,
                'template_name' => $doc->documentTemplate->template_name,
                'template_code' => $doc->documentTemplate->template_code,
            ] : null,
        ], 'Letter request details retrieved successfully.');
    }
}
