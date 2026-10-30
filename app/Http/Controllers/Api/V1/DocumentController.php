<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreHrEmployeeDocumentRequest;
use App\Http\Resources\Api\V1\DocumentTypeResource;
use App\Http\Resources\Api\V1\EmployeeDocumentResource;
use App\Models\DocumentCategory;
use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentHistory;
use App\Models\EmployeeRequestDetail;
use App\Models\SalesCrm\Employee;
use App\Services\EmployeeDocumentService;
use App\Support\SalesCrmRoles;
use App\Traits\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

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
    #[OA\Get(
        path: '/api/v1/documents/categories',
        summary: 'Get document categories with counts',
        security: [['sanctum' => []]],
        tags: ['Documents'],
        responses: [
            new OA\Response(response: 200, description: 'Document categories retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
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
    #[OA\Get(
        path: '/api/v1/documents/types',
        summary: 'Get document types',
        security: [['sanctum' => []]],
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category_code', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Document types retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
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

        $types = DocumentTypeResource::collection($query->orderBy('id')->get());

        return $this->successResponse($types, 'Document types retrieved successfully.');
    }

    /**
     * Download or view blank form template for a document type.
     */
    public function blankForm(Request $request, $id)
    {
        $docType = DocumentType::query()->with(['documentTemplates' => fn ($q) => $q->where('status', 1)])->find($id);
        if (! $docType) {
            return $this->errorResponse('Document type not found.', 404);
        }

        $employee = $this->getEmployee($request);
        $template = $docType->resolveTemplate($employee?->organisation_id);
        if (! $template || empty($template->template_code)) {
            return $this->errorResponse('No blank form template available for this document type.', 404);
        }

        $isPureBlank = $request->boolean('pure_blank') || ! $employee;

        $replacements = [
            '{{employee_name}}' => $isPureBlank ? '&nbsp;' : ($employee?->user?->name ?? '&nbsp;'),
            '{{employee_code}}' => $isPureBlank ? '&nbsp;' : ($employee?->emp_num ?? '&nbsp;'),
            '{{department}}' => $isPureBlank ? '&nbsp;' : ($employee?->department?->name ?? '&nbsp;'),
            '{{designation}}' => $isPureBlank ? '&nbsp;' : ($employee?->designation ?? '&nbsp;'),
            '{{joining_date}}' => ($isPureBlank || ! $employee?->joining_date) ? '&nbsp;' : Carbon::parse($employee->joining_date)->format('d M Y'),
        ];

        $html = str_replace(array_keys($replacements), array_values($replacements), $template->template_code);

        // If JSON requested explicitly
        if ($request->wantsJson() && ! $request->boolean('download')) {
            return $this->successResponse([
                'document_type_id' => $docType->id,
                'document_name' => $docType->document_name,
                'document_code' => $docType->document_code,
                'template_name' => $template->template_name,
                'rendered_html' => $html,
                'download_url' => route('api.v1.documents.blank-form', ['document_type' => $docType->id, 'download' => 1]),
            ], 'Blank form template retrieved successfully.');
        }

        // Return PDF
        $pdfHtml = '<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>'.htmlspecialchars($docType->document_name).'</title>
    <style>
        @page { margin: 12mm 15mm; size: A4 portrait; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 12px; color: #111; line-height: 1.4; margin: 0; padding: 0; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        p { margin: 4px 0; }
    </style>
</head>
<body>'.$html.'</body>
</html>';

        $pdf = Pdf::loadHTML($pdfHtml)->setPaper('a4', 'portrait');
        $filename = Str::slug($docType->document_name).'-blank-form.pdf';

        if ($request->query('view') === '1' || $request->query('stream') === '1') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    /**
     * Filtered Document List (Matching Figma Category Document List).
     */
    public function index(Request $request, EmployeeDocumentService $employeeDocumentService): JsonResponse
    {
        $employee = $this->getEmployee($request);
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        return $this->formatDocumentList(
            $employeeDocumentService->list(
                $request,
                (int) $employee->id,
                (int) $request->input('per_page', 15),
            ),
            $employeeDocumentService,
            (int) $request->user()->id,
            (int) $employee->id,
        );
    }

    /**
     * List documents across all employees for HR.
     */
    #[OA\Get(
        path: '/api/v1/hr/documents',
        summary: 'List documents across employees for HR',
        security: [['sanctum' => []]],
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(name: 'employee_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'document_type_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Documents retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function hrIndex(Request $request, EmployeeDocumentService $employeeDocumentService): JsonResponse
    {
        return $this->formatDocumentList(
            $employeeDocumentService->list(
                $request,
                perPage: (int) $request->input('per_page', 15),
                applyHrOnlyFilters: true,
                includeEmployeeDetails: true,
            ),
            $employeeDocumentService,
            (int) $request->user()->id,
            includeEmployeeDetails: true,
        );
    }

    public function hrShow(Request $request, int $id, EmployeeDocumentService $employeeDocumentService): JsonResponse
    {
        $document = EmployeeDocument::query()
            ->with([
                'employee.user:id,name',
                'documentType.category',
                'documentTemplate',
                'files' => fn ($q) => $q->orderByDesc('id'),
                'histories' => fn ($q) => $q->orderByDesc('id'),
                'requestDetails',
            ])
            ->findOrFail($id);

        $latestFile = $document->files->first();

        return $this->successResponse([
            'id' => $document->id,
            'employee_id' => $document->employee_id,
            'employee_name' => $document->employee?->user?->name,
            'document_type' => DocumentTypeResource::make($document->documentType),
            'document_title' => $document->document_title,
            'document_number' => $document->document_number,
            'issue_date' => $document->issue_date?->format('d M Y'),
            'issue_date_raw' => $document->issue_date?->format('Y-m-d'),
            'expiry_date' => $document->expiry_date?->format('d M Y'),
            'expiry_date_raw' => $document->expiry_date?->format('Y-m-d'),
            'remarks' => $document->remarks,
            'status' => $document->status,
            'current_version' => $document->current_version,
            'file_url' => $latestFile ? Storage::disk('public')->url($latestFile->file_path) : null,
            'file_name' => $latestFile ? basename($latestFile->file_path) : null,
            'request_details' => $document->requestDetails->pluck('field_value', 'field_key'),
            'histories' => $document->histories->map(fn ($history) => [
                'action_type' => $history->action_type,
                'remarks' => $history->remarks,
                'action_on' => $history->action_on?->format('d M Y H:i'),
            ]),
            'can_edit' => $employeeDocumentService->canEdit($document, (int) $request->user()->id),
        ], 'Document details retrieved successfully.');
    }

    public function updateHrDocument(Request $request, int $id, EmployeeDocumentService $employeeDocumentService): JsonResponse
    {
        $document = EmployeeDocument::query()->with('documentType')->findOrFail($id);
        $userId = (int) $request->user()->id;

        if (! $employeeDocumentService->canEdit($document, $userId)) {
            return $this->errorResponse('You are not allowed to edit this document.', 403);
        }

        $fieldRequires = $document->documentType?->field_requires ?? [];
        $fileIsRequired = ! empty($fieldRequires['attachments']) && ! $document->files()->exists();
        $validator = Validator::make($request->all(), [
            'document_number' => ! empty($fieldRequires['ref_number'])
                ? ['sometimes', 'required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9\/-]+\z/']
                : ['prohibited'],
            'document_title' => ['sometimes', 'required', 'string', 'max:255'],
            'issue_date' => ! empty($fieldRequires['issue_date'])
                ? ['sometimes', 'required', 'date', 'before_or_equal:today']
                : ['prohibited'],
            'expiry_date' => ! empty($fieldRequires['expiry_date'])
                ? ['sometimes', 'required', 'date', 'after:today']
                : ['prohibited'],
            'remarks' => ['sometimes', 'nullable', 'string'],
            'file' => $fileIsRequired
                ? 'required|file|mimes:pdf,jpg,jpeg,png|max:10240'
                : 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'uid' => ! empty($fieldRequires['uid'])
                ? ['sometimes', 'required', 'string', 'max:15', 'regex:/\A[0-9]{1,15}\z/']
                : ['prohibited'],
            'total_experience' => ! empty($fieldRequires['total_experience'])
                ? ['sometimes', 'required', 'numeric', 'min:0', 'max:80']
                : ['prohibited'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $validated = $validator->validated();
        $attributes = array_intersect_key($validated, array_flip([
            'document_number',
            'document_title',
            'issue_date',
            'expiry_date',
            'remarks',
        ]));
        $attributes['status'] = $document->status === 'under_review' ? 'submitted' : $document->status;
        $requestDetails = array_intersect_key($validated, array_flip(['uid', 'total_experience']));

        $employeeDocumentService->updateDocument(
            $document,
            $attributes,
            $requestDetails,
            $request->file('file'),
            $userId,
            'Document details updated by '.(SalesCrmRoles::hrmsRoleLabelForUser($userId) ?? 'HRMS user'),
        );

        return $this->successResponse([
            'id' => $document->id,
            'status' => $document->status,
            'current_version' => $document->current_version,
        ], 'Document updated successfully.');
    }

    /**
     * Approve or reject an employee document as HR.
     */
    #[OA\Post(
        path: '/api/v1/hr/documents/{id}/status',
        summary: 'Approve or reject an employee document',
        security: [['sanctum' => []]],
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['approved', 'rejected']),
                    new OA\Property(property: 'remarks', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Document status updated successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Document not found'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function updateHrStatus(Request $request, int $id, EmployeeDocumentService $employeeDocumentService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'remarks' => $request->input('status') === 'rejected'
                ? 'required|string|max:500'
                : 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $validated = $validator->validated();
        $document = EmployeeDocument::query()->findOrFail($id);
        $document->loadMissing('documentType.category');

        if ($document->documentType?->category?->short_code === 'letter_requests') {
            return $this->errorResponse(
                'Use the letter request approval or rejection endpoint to process letter requests.',
                409,
            );
        }

        $remarks = $validated['remarks'] ?? 'Approved by HR';

        $employeeDocumentService->updateStatus(
            $document,
            $validated['status'],
            $remarks,
            $request->user()?->id,
        );

        return $this->successResponse([
            'id' => $document->id,
            'status' => $document->status,
            'remarks' => $document->remarks,
        ], 'Document status updated successfully.');
    }

    private function formatDocumentList(
        LengthAwarePaginator $documents,
        EmployeeDocumentService $employeeDocumentService,
        int $userId,
        ?int $employeeId = null,
        bool $includeEmployeeDetails = false,
    ): JsonResponse {
        $documents->setCollection($documents->getCollection()->map(function (EmployeeDocument $doc) use ($employeeDocumentService, $userId, $employeeId, $includeEmployeeDetails) {
            $latestFile = $doc->files->first();
            $fileUrl = $latestFile ? Storage::disk('public')->url($latestFile->file_path) : null;

            return [
                'id' => $doc->id,
                ...($includeEmployeeDetails ? [
                    'employee_id' => $doc->employee_id,
                    'employee_name' => $doc->employee?->user?->name,
                ] : []),
                'document_type' => DocumentTypeResource::make($doc->documentType),
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
                // 'request_details' => $doc->requestDetails->pluck('field_value', 'field_key'),
                'can_edit' => $employeeDocumentService->canEdit($doc, $userId, $employeeId),
                'created_at' => $doc->created_at?->format('d M Y'),
            ];
        }));

        return $this->successPaginatedResponse($documents, 'documents', 'Documents retrieved successfully.');
    }

    /**
     * Upload / Submit New Document.
     */
    public function store(Request $request, EmployeeDocumentService $employeeDocumentService): JsonResponse
    {
        $employee = $this->getEmployee($request);
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'document_type_id' => 'required|exists:document_types,id',
            'document_template_id' => 'nullable|exists:document_templates,id',
            'organisation_id' => 'nullable|integer|exists:organisations,id',
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

        $initialStatus = $docType->requires_hr_approval ? 'submitted' : 'approved';
        $organisationId = $request->input('organisation_id') ?? $employee->organisation_id;
        $templateId = $request->input('document_template_id')
            ?? $docType->resolveTemplate($organisationId)?->id;
        $uploaderRole = SalesCrmRoles::hrmsRoleLabelForUser($request->user()?->id);

        $document = $employeeDocumentService->create(
            $docType,
            [
                'employee_id' => $employee->id,
                'organisation_id' => $organisationId,
                'document_type_id' => $docType->id,
                'document_template_id' => $templateId,
                'document_number' => $request->input('document_number'),
                'document_title' => $request->input('document_title') ?? $docType->document_name,
                'issue_date' => $request->input('issue_date'),
                'expiry_date' => $request->input('expiry_date'),
                'remarks' => $request->input('remarks'),
                'current_version' => '1.0',
                'created_by' => $request->user()?->id,
                'status' => $initialStatus,
            ],
            $request->file('file'),
            $request->user()?->id,
            false,
        );

        if ($uploaderRole !== null) {
            $document->files()->latest('id')->firstOrFail()->update([
                'change_notes' => $document->status === 'submitted'
                    ? "Uploaded by {$uploaderRole}; awaiting HR approval"
                    : "Uploaded by {$uploaderRole}",
            ]);
        }

        return $this->successResponse([
            'id' => $document->id,
            'status' => $document->status,
            'message' => 'Document uploaded successfully.',
        ], 'Document uploaded successfully.', 201);
    }

    public function hrStore(StoreHrEmployeeDocumentRequest $request, EmployeeDocumentService $employeeDocumentService): JsonResponse
    {
        $validated = $request->validated();
        $employee = Employee::query()->findOrFail((int) $validated['employee_id']);
        $documentType = DocumentType::query()->findOrFail((int) $validated['document_type_id']);
        $organisationId = $employee->organisation_id;
        $templateId = $validated['document_template_id']
            ?? $documentType->resolveTemplate($organisationId)?->id;
        $status = $documentType->requires_hr_approval ? 'submitted' : 'approved';
        $userId = (int) $request->user()->id;
        $requestDetails = array_intersect_key($validated, array_flip(['uid', 'total_experience']));

        $document = $employeeDocumentService->create(
            $documentType,
            [
                'employee_id' => $employee->id,
                'organisation_id' => $organisationId,
                'document_type_id' => $documentType->id,
                'document_template_id' => $templateId,
                'document_number' => $validated['document_number'] ?? null,
                'document_title' => $validated['document_title'] ?? $documentType->document_name,
                'issue_date' => $validated['issue_date'] ?? null,
                'expiry_date' => $validated['expiry_date'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'current_version' => '1.0',
                'created_by' => $userId,
                'status' => $status,
            ],
            $request->file('file'),
            $userId,
            true,
            $requestDetails,
            SalesCrmRoles::hrmsRoleLabelForUser($userId),
        );

        $document->load([
            'employee.user:id,name',
            'files' => fn ($query) => $query->orderByDesc('id'),
            'requestDetails',
        ]);
        $data = EmployeeDocumentResource::make($document)->resolve($request);
        $data['message'] = 'Document uploaded successfully.';

        return $this->successResponse($data, 'Document uploaded successfully.', 201);
    }

    /**
     * Get Document Details with version history.
     */
    public function show(Request $request, int $id, EmployeeDocumentService $employeeDocumentService): JsonResponse
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
            'template_name' => $doc->documentTemplate?->template_name,
            'can_edit' => $employeeDocumentService->canEdit($doc, (int) $request->user()->id, (int) $employee->id),
            'files' => $files,
            'histories' => $histories,
            'request_details' => $doc->requestDetails->pluck('field_value', 'field_key'),
        ], 'Document details retrieved successfully.');
    }

    /**
     * Edit / Update Document (e.g. before approval or replace file).
     */
    public function update(Request $request, int $id, EmployeeDocumentService $employeeDocumentService): JsonResponse
    {
        $employee = $this->getEmployee($request);
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $document = EmployeeDocument::query()
            ->with('documentType')
            ->where('employee_id', $employee->id)
            ->findOrFail($id);

        if (! $employeeDocumentService->canEdit($document, (int) $request->user()->id, (int) $employee->id)) {
            return $this->errorResponse('You are not allowed to edit this document.', 403);
        }

        $fieldRequires = $document->documentType?->field_requires ?? [];
        $fileIsRequired = ! empty($fieldRequires['attachments']) && ! $document->files()->exists();
        $validator = Validator::make($request->all(), [
            'document_number' => ! empty($fieldRequires['ref_number'])
                ? ['sometimes', 'required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9\/-]+\z/']
                : ['sometimes', 'nullable', 'string', 'max:100', 'regex:/\A[A-Za-z0-9\/-]+\z/'],
            'document_title' => ['sometimes', 'required', 'string', 'max:255'],
            'issue_date' => ! empty($fieldRequires['issue_date'])
                ? ['sometimes', 'required', 'date', 'before_or_equal:today']
                : ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'expiry_date' => ! empty($fieldRequires['expiry_date'])
                ? ['sometimes', 'required', 'date', 'after:today']
                : ['sometimes', 'nullable', 'date', 'after:today'],
            'remarks' => ['sometimes', 'nullable', 'string'],
            'file' => $fileIsRequired
                ? 'required|file|mimes:pdf,jpg,jpeg,png|max:10240'
                : 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'uid' => ! empty($fieldRequires['uid'])
                ? ['sometimes', 'required', 'string', 'max:15', 'regex:/\A[0-9]{1,15}\z/']
                : ['prohibited'],
            'total_experience' => ! empty($fieldRequires['total_experience'])
                ? ['sometimes', 'required', 'numeric', 'min:0', 'max:80']
                : ['prohibited'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $validated = $validator->validated();
        $attributes = array_intersect_key($validated, array_flip([
            'document_number',
            'document_title',
            'issue_date',
            'expiry_date',
            'remarks',
        ]));
        $attributes['status'] = 'submitted';
        $requestDetails = array_intersect_key($validated, array_flip(['uid', 'total_experience']));

        $employeeDocumentService->updateDocument(
            $document,
            $attributes,
            $requestDetails,
            $request->file('file'),
            (int) $request->user()->id,
            'Document details updated by employee',
        );

        return $this->successResponse([
            'id' => $document->id,
            'status' => $document->status,
            'current_version' => $document->current_version,
        ], 'Document updated successfully.');
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
    #[OA\Get(
        path: '/api/v1/documents/letters',
        summary: 'Get employee letter requests list',
        security: [['sanctum' => []]],
        tags: ['Documents - Letters'],
        responses: [
            new OA\Response(response: 200, description: 'Letter requests retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
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
    #[OA\Post(
        path: '/api/v1/documents/letters',
        summary: 'Submit a new letter request',
        security: [['sanctum' => []]],
        tags: ['Documents - Letters'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['document_type_id', 'purpose'],
                properties: [
                    new OA\Property(property: 'document_type_id', type: 'integer', example: 1),
                    new OA\Property(property: 'document_template_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'organisation_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'purpose', type: 'string', example: 'Employment Visa Process'),
                    new OA\Property(property: 'details', type: 'string', nullable: true),
                    new OA\Property(property: 'to_address', type: 'string', nullable: true),
                    new OA\Property(property: 'visa_designation', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Letter request submitted successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Employee not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function requestLetter(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'document_type_id' => 'required|exists:document_types,id',
            'document_template_id' => 'nullable|exists:document_templates,id',
            'organisation_id' => 'nullable|integer|exists:organisations,id',
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
            $organisationId = $request->input('organisation_id') ?? $employee->organisation_id;
            $templateId = $request->input('document_template_id')
                ?? $docType->resolveTemplate($organisationId)?->id;

            $document = EmployeeDocument::query()->create([
                'employee_id' => $employee->id,
                'organisation_id' => $organisationId,
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
    #[OA\Get(
        path: '/api/v1/documents/letters/{id}',
        summary: 'Get letter request details',
        security: [['sanctum' => []]],
        tags: ['Documents - Letters'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Letter request details retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Employee or letter request not found'),
        ]
    )]
    public function letterDetails(Request $request, int $id): JsonResponse
    {
        $employee = $this->getEmployee($request);
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $doc = EmployeeDocument::query()
            ->with(['documentType', 'documentTemplate', 'requestDetails', 'histories', 'files' => fn ($q) => $q->orderByDesc('id')])
            ->where('employee_id', $employee->id)
            ->findOrFail($id);

        $details = $doc->requestDetails->pluck('field_value', 'field_key');
        $latestFile = $doc->files->first();
        $fileUrl = $latestFile ? Storage::disk('public')->url($latestFile->file_path) : null;

        return $this->successResponse([
            'id' => $doc->id,
            'title' => ($doc->documentType?->document_name ?? 'Document').' Request Details',
            'purpose' => $details['purpose'] ?? 'Employment Visa Process',
            'details' => $details['details'] ?? 'Requesting a No Objection Certificate for embassy submission regarding the new employment visa renewal and international mobility transfer.',
            'to_address' => $details['to_address'] ?? null,
            'visa_designation' => $details['visa_designation'] ?? null,
            'salary_amount' => $details['salary_amount'] ?? null,
            'bank_iban' => $details['bank_iban'] ?? null,
            'destination_country' => $details['destination_country'] ?? null,
            'applied_date' => $doc->created_at?->format('d M Y') ?? '12 JAN 2026',
            'approved_date' => $doc->status === 'approved' ? ($doc->updated_at?->format('d M Y') ?? null) : null,
            'status' => ucfirst($doc->status),
            'remarks' => $doc->remarks,
            'file_url' => $fileUrl,
            'download_url' => $latestFile ? url('storage/'.$latestFile->file_path) : null,
            'has_generated_file' => (bool) $latestFile,
        ], 'Letter request details retrieved successfully.');
    }
}
