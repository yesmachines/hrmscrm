<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\DocumentCategory;
use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\SalesCrm\Employee;
use App\Services\EmployeeDocumentService;
use App\Support\SalesCrmRoles;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeDocumentController extends Controller
{
    public function index(Request $request, EmployeeDocumentService $employeeDocumentService): Response
    {
        $query = EmployeeDocument::query()
            ->with([
                'documentType.category',
                'documentTemplate',
                'files' => fn ($q) => $q->orderByDesc('id'),
            ]);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('category_id')) {
            $query->whereHas('documentType', function ($q) use ($request) {
                $q->where('category_id', $request->input('category_id'));
            });
        }

        if ($request->filled('document_type_id')) {
            $query->where('document_type_id', $request->input('document_type_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
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

        $documents = $query->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (EmployeeDocument $doc) => $this->payload(
                $doc,
                $employeeDocumentService->canEdit($doc, (int) $request->user()->id),
            ));

        $downloadableForms = DocumentType::query()
            ->whereHas('documentTemplates', fn ($q) => $q->where('status', 1))
            ->get(['id', 'document_name', 'document_code'])
            ->map(fn (DocumentType $t) => [
                'id' => $t->id,
                'name' => $t->document_name,
                'code' => $t->document_code,
                'download_url' => route('document-types.blank-form', ['document_type' => $t->id, 'download' => 1]),
            ]);

        $categories = DocumentCategory::query()->where('status', 1)->get(['id', 'category_name', 'short_code']);
        $documentTypes = DocumentType::query()->get(['id', 'category_id', 'document_name', 'document_code']);
        $employees = Employee::query()->with('user:id,name,email')->get(['id', 'user_id', 'emp_num', 'designation']);

        return Inertia::render('employee-documents/index', [
            'documents' => $documents,
            'categories' => $categories,
            'documentTypes' => $documentTypes,
            'employees' => $employees,
            'downloadableForms' => $downloadableForms,
            'filters' => $request->only(['employee_id', 'category_id', 'document_type_id', 'status', 'search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('employee-documents/create', $this->documentFormOptions());
    }

    public function edit(
        Request $request,
        EmployeeDocument $employeeDocument,
        EmployeeDocumentService $employeeDocumentService,
    ): Response {
        if (! $employeeDocumentService->canEdit($employeeDocument, (int) $request->user()->id)) {
            abort(403);
        }

        $employeeDocument->load([
            'documentType',
            'files' => fn ($q) => $q->orderByDesc('id'),
            'requestDetails',
        ]);
        $latestFile = $employeeDocument->files->first();
        $formOptions = $this->documentFormOptions($employeeDocument->documentType?->category_id);
        $formOptions['document'] = [
            'id' => $employeeDocument->id,
            'employee_id' => $employeeDocument->employee_id,
            'category_id' => $employeeDocument->documentType?->category_id,
            'document_type_id' => $employeeDocument->document_type_id,
            'document_title' => $employeeDocument->document_title,
            'document_number' => $employeeDocument->document_number,
            'issue_date' => $employeeDocument->issue_date?->format('Y-m-d'),
            'expiry_date' => $employeeDocument->expiry_date?->format('Y-m-d'),
            'remarks' => $employeeDocument->remarks,
            'status' => $employeeDocument->status,
            'file_url' => $latestFile ? route('employee-documents.file', $employeeDocument->id) : null,
            'file_name' => $latestFile ? basename($latestFile->file_path) : null,
            'request_details' => $employeeDocument->requestDetails->pluck('field_value', 'field_key')->all(),
        ];

        return Inertia::render('employee-documents/create', $formOptions);
    }

    private function documentFormOptions(?int $includeCategoryId = null): array
    {
        $categoryQuery = DocumentCategory::query()
            ->with(['documentTypes.documentTemplates' => fn ($q) => $q->where('status', 1)])
            ->where(function ($query) use ($includeCategoryId) {
                $query->where('status', 1);
                if ($includeCategoryId !== null) {
                    $query->orWhere('id', $includeCategoryId);
                }
            });

        $categories = $categoryQuery->get()->map(fn ($category) => [
            'id' => $category->id,
            'category_name' => $category->category_name,
            'short_code' => $category->short_code,
            'document_types' => $category->documentTypes->map(function ($type) {
                $hasBlank = $type->documentTemplates->isNotEmpty();

                return [
                    'id' => $type->id,
                    'category_id' => $type->category_id,
                    'document_name' => $type->document_name,
                    'document_code' => $type->document_code,
                    'field_requires' => empty($type->field_requires) ? null : (object) $type->field_requires,
                    'requires_hr_approval' => (bool) $type->requires_hr_approval,
                    'has_blank_form' => $hasBlank,
                    'blank_form_url' => $hasBlank ? route('document-types.blank-form', ['document_type' => $type->id, 'download' => 1]) : null,
                ];
            }),
        ]);

        $downloadableForms = DocumentType::query()
            ->whereHas('documentTemplates', fn ($q) => $q->where('status', 1))
            ->get(['id', 'document_name', 'document_code'])
            ->map(fn (DocumentType $type) => [
                'id' => $type->id,
                'name' => $type->document_name,
                'code' => $type->document_code,
                'download_url' => route('document-types.blank-form', ['document_type' => $type->id, 'download' => 1]),
            ]);

        $employees = Employee::query()
            ->with('user:id,name,email')
            ->get(['id', 'user_id', 'emp_num', 'designation']);

        return [
            'categories' => $categories,
            'employees' => $employees,
            'downloadableForms' => $downloadableForms,
        ];
    }

    public function store(Request $request, EmployeeDocumentService $employeeDocumentService): RedirectResponse
    {
        $documentTypeId = $request->input('document_type_id');
        $selectedDocumentType = is_scalar($documentTypeId)
            && filter_var($documentTypeId, FILTER_VALIDATE_INT) !== false
                ? DocumentType::query()->find((int) $documentTypeId)
                : null;
        $fieldRequires = $selectedDocumentType?->field_requires ?? [];
        $categoryRules = ['required', 'integer', 'exists:document_categories,id'];

        if ($selectedDocumentType) {
            $categoryRules[] = Rule::in([$selectedDocumentType->category_id]);
        }

        $request->validate([
            'employee_id' => 'required|integer',
            'category_id' => $categoryRules,
            'document_type_id' => 'required|exists:document_types,id',
            'document_template_id' => 'nullable|exists:document_templates,id',
            'organisation_id' => 'nullable|integer|exists:organisations,id',
            'document_number' => ! empty($fieldRequires['ref_number'])
                ? ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9\/-]+\z/']
                : ['nullable', 'string', 'max:100', 'regex:/\A[A-Za-z0-9\/-]+\z/'],
            'document_title' => 'required|string|max:255',
            'issue_date' => ! empty($fieldRequires['issue_date'])
                ? 'required|date|before_or_equal:today'
                : 'nullable|date|before_or_equal:today',
            'expiry_date' => ! empty($fieldRequires['expiry_date'])
                ? 'required|date|after:today'
                : 'nullable|date|after:today',
            'remarks' => 'nullable|string',
            'status' => 'nullable|string|in:submitted,approved,draft',
            'file' => ! empty($fieldRequires['attachments'])
                ? 'required|file|mimes:pdf,jpg,jpeg,png|max:10240'
                : 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'uid' => ! empty($fieldRequires['uid'])
                ? ['required', 'string', 'max:15', 'regex:/\A[0-9]{1,15}\z/']
                : ['nullable', 'string', 'max:15', 'regex:/\A[0-9]{1,15}\z/'],
            'total_experience' => ! empty($fieldRequires['total_experience'])
                ? 'required|numeric|min:0|max:80'
                : 'nullable|numeric|min:0|max:80',
        ]);
        $docType = $selectedDocumentType ?? DocumentType::query()->findOrFail($documentTypeId);
        $customFieldKeys = array_filter(
            ['uid', 'total_experience'],
            fn (string $fieldKey): bool => ! empty($fieldRequires[$fieldKey]),
        );

        $employee = Employee::query()->find($request->input('employee_id'));
        $organisationId = $request->input('organisation_id') ?? $employee?->organisation_id;
        $templateId = $request->input('document_template_id')
            ?? $docType->resolveTemplate($organisationId)?->id;

        $status = $request->input('status');
        if (! in_array($status, ['submitted', 'approved', 'draft'], true)) {
            $status = $docType->requires_hr_approval ? 'submitted' : 'approved';
        }

        DB::transaction(function () use ($request, $docType, $employeeDocumentService, $organisationId, $templateId, $status, $customFieldKeys) {
            $document = $employeeDocumentService->create(
                $docType,
                [
                    'employee_id' => $request->input('employee_id'),
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
                    'status' => $status,
                ],
                $request->file('file'),
                $request->user()?->id,
                true,
            );

            foreach ($customFieldKeys as $fieldKey) {
                if (! $request->exists($fieldKey)) {
                    continue;
                }

                $document->requestDetails()->updateOrCreate(
                    ['field_key' => $fieldKey],
                    [
                        'field_name' => Str::headline($fieldKey),
                        'field_value' => (string) $request->input($fieldKey),
                    ],
                );
            }
        });

        $message = $status === 'submitted'
            ? 'Document uploaded successfully and queued for HR approval.'
            : 'Document uploaded and approved successfully.';

        return redirect()->route('employee-documents.index')
            ->with('toast', ['type' => 'success', 'message' => $message]);
    }

    public function update(
        Request $request,
        EmployeeDocument $employeeDocument,
        EmployeeDocumentService $employeeDocumentService,
    ): RedirectResponse {
        if (! $employeeDocumentService->canEdit($employeeDocument, (int) $request->user()->id)) {
            abort(403);
        }

        $documentTypeId = $request->input('document_type_id');
        $selectedDocumentType = is_scalar($documentTypeId)
            && filter_var($documentTypeId, FILTER_VALIDATE_INT) !== false
                ? DocumentType::query()->find((int) $documentTypeId)
                : null;
        $fieldRequires = $selectedDocumentType?->field_requires ?? [];
        $categoryRules = ['required', 'integer', 'exists:document_categories,id'];

        if ($selectedDocumentType) {
            $categoryRules[] = Rule::in([$selectedDocumentType->category_id]);
        }

        $fileIsRequired = ! empty($fieldRequires['attachments'])
            && ((int) $employeeDocument->document_type_id !== (int) $selectedDocumentType?->id
                || ! $employeeDocument->files()->exists());

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists(Employee::class, 'id')],
            'category_id' => $categoryRules,
            'document_type_id' => 'required|exists:document_types,id',
            'document_number' => ! empty($fieldRequires['ref_number'])
                ? ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9\/-]+\z/']
                : ['nullable', 'string', 'max:100', 'regex:/\A[A-Za-z0-9\/-]+\z/'],
            'document_title' => 'required|string|max:255',
            'issue_date' => ! empty($fieldRequires['issue_date'])
                ? 'required|date|before_or_equal:today'
                : 'nullable|date|before_or_equal:today',
            'expiry_date' => ! empty($fieldRequires['expiry_date'])
                ? 'required|date|after:today'
                : 'nullable|date|after:today',
            'remarks' => 'nullable|string',
            'file' => $fileIsRequired
                ? 'required|file|mimes:pdf,jpg,jpeg,png|max:10240'
                : 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'uid' => ! empty($fieldRequires['uid'])
                ? ['required', 'string', 'max:15', 'regex:/\A[0-9]{1,15}\z/']
                : ['nullable', 'string', 'max:15', 'regex:/\A[0-9]{1,15}\z/'],
            'total_experience' => ! empty($fieldRequires['total_experience'])
                ? 'required|numeric|min:0|max:80'
                : 'nullable|numeric|min:0|max:80',
        ]);

        $documentType = $selectedDocumentType ?? DocumentType::query()->findOrFail($documentTypeId);
        $employee = Employee::query()->find($validated['employee_id']);
        $organisationId = $employee?->organisation_id;
        $sameDocumentType = $documentType->id === $employeeDocument->document_type_id;
        $templateId = $sameDocumentType
            && (int) $organisationId === (int) $employeeDocument->organisation_id
            ? $employeeDocument->document_template_id
            : $documentType->resolveTemplate($organisationId)?->id;
        $requestDetails = [
            'uid' => ! empty($fieldRequires['uid']) ? ($validated['uid'] ?? null) : null,
            'total_experience' => ! empty($fieldRequires['total_experience']) ? ($validated['total_experience'] ?? null) : null,
        ];

        $employeeDocumentService->updateDocument(
            $employeeDocument,
            [
                'employee_id' => $validated['employee_id'],
                'organisation_id' => $organisationId,
                'document_type_id' => $documentType->id,
                'document_template_id' => $templateId,
                'document_number' => ! empty($fieldRequires['ref_number'])
                    ? $validated['document_number']
                    : ($sameDocumentType ? $employeeDocument->document_number : null),
                'document_title' => $validated['document_title'],
                'issue_date' => ! empty($fieldRequires['issue_date'])
                    ? $validated['issue_date']
                    : ($sameDocumentType ? $employeeDocument->issue_date : null),
                'expiry_date' => ! empty($fieldRequires['expiry_date'])
                    ? $validated['expiry_date']
                    : ($sameDocumentType ? $employeeDocument->expiry_date : null),
                'remarks' => $validated['remarks'] ?? null,
                'status' => $employeeDocument->status === 'under_review' ? 'submitted' : $employeeDocument->status,
            ],
            $requestDetails,
            $request->file('file'),
            (int) $request->user()->id,
            'Document details updated by '.(SalesCrmRoles::hrmsRoleLabelForUser((int) $request->user()->id) ?? 'HRMS user'),
        );

        return redirect()->route('employee-documents.show', $employeeDocument)
            ->with('toast', ['type' => 'success', 'message' => 'Document updated successfully.']);
    }

    public function show(Request $request, EmployeeDocument $employeeDocument, EmployeeDocumentService $employeeDocumentService): Response
    {
        $employeeDocument->load([
            'documentType.category',
            'documentTemplate',
            'files' => fn ($q) => $q->orderByDesc('id'),
            'histories' => fn ($q) => $q->orderByDesc('id'),
            'requestDetails',
        ]);

        $employee = Employee::query()->with('user:id,name,email', 'department:id,name')->find($employeeDocument->employee_id);

        return Inertia::render('employee-documents/show', [
            'document' => $this->payload(
                $employeeDocument,
                $employeeDocumentService->canEdit($employeeDocument, (int) $request->user()->id),
            ),
            'employee' => $employee,
        ]);
    }

    public function approve(Request $request, EmployeeDocument $employeeDocument, EmployeeDocumentService $employeeDocumentService): RedirectResponse
    {
        $validated = $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        $employeeDocumentService->updateStatus(
            $employeeDocument,
            'approved',
            $validated['remarks'] ?? 'Approved by HR',
            $request->user()?->id,
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Document approved successfully.']);
    }

    public function reject(Request $request, EmployeeDocument $employeeDocument, EmployeeDocumentService $employeeDocumentService): RedirectResponse
    {
        $validated = $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        $employeeDocumentService->updateStatus(
            $employeeDocument,
            'rejected',
            $validated['remarks'],
            $request->user()?->id,
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Document rejected.']);
    }

    public function file(EmployeeDocument $employeeDocument): BinaryFileResponse
    {
        if ($employeeDocument->documentType?->category?->short_code === 'letter_requests'
            && $employeeDocument->created_at
            && $employeeDocument->created_at->copy()->addMonths(3)->isPast()) {
            abort(403, 'This letter request has expired and is no longer available.');
        }

        $latestFile = $employeeDocument->files()->latest('id')->first();
        if (! $latestFile || ! Storage::disk('public')->exists($latestFile->file_path)) {
            abort(404, 'Document file not found on disk.');
        }

        return response()->file(Storage::disk('public')->path($latestFile->file_path));
    }

    public function download(EmployeeDocument $employeeDocument): BinaryFileResponse
    {
        if ($employeeDocument->documentType?->category?->short_code === 'letter_requests'
            && $employeeDocument->created_at
            && $employeeDocument->created_at->copy()->addMonths(3)->isPast()) {
            abort(403, 'This letter request has expired and is no longer available.');
        }

        $latestFile = $employeeDocument->files()->latest('id')->first();
        if (! $latestFile || ! Storage::disk('public')->exists($latestFile->file_path)) {
            abort(404, 'Document file not found on disk.');
        }

        $ext = pathinfo($latestFile->file_path, PATHINFO_EXTENSION);
        $filename = Str::slug($employeeDocument->document_title ?: $employeeDocument->documentType?->document_name ?: 'document').'.'.$ext;

        return response()->download(Storage::disk('public')->path($latestFile->file_path), $filename);
    }

    protected function renderDocumentHtml(EmployeeDocument $doc, ?Employee $employee): ?string
    {
        $employee ??= Employee::query()
            ->with(['user', 'department', 'organisation'])
            ->find($doc->employee_id);

        $template = $doc->documentTemplate
            ?? $doc->documentType?->resolveTemplate($employee?->organisation_id);

        if (! $template || empty($template->template_code)) {
            return null;
        }

        $details = $doc->requestDetails->pluck('field_value', 'field_key')->all();
        $employee?->loadMissing(['user', 'department', 'organisation']);
        $profile = $employee?->profile();

        $replacements = [
            '{{company_name}}' => $employee?->organisation?->org_name ?? config('app.name', 'HRMS'),
            '{{company_address}}' => 'Dubai, United Arab Emirates',
            '{{company_phone}}' => '+971 4 000 0000',
            '{{company_email}}' => 'hr@company.com',
            '{{employee_name}}' => $employee?->user?->name ?? "Employee #{$doc->employee_id}",
            '{{employee_code}}' => $employee?->emp_num ?? "EMP-{$doc->employee_id}",
            '{{passport_number}}' => '—',
            '{{emirates_id}}' => '—',
            '{{designation}}' => $details['visa_designation'] ?? ($employee?->designation ?? 'Employee'),
            '{{department}}' => $employee?->department?->name ?? 'General',
            '{{joining_date}}' => $employee?->joining_date ? Carbon::parse($employee->joining_date)->format('d M Y') : '—',
            '{{basic_salary}}' => $employee?->basic_salary ? number_format((float) $employee->basic_salary, 2) : '0.00',
            '{{housing_allowance}}' => $employee?->housing_allowance ? number_format((float) $employee->housing_allowance, 2) : '0.00',
            '{{other_allowance}}' => '0.00',
            '{{gross_salary}}' => $employee?->gross_salary ? number_format((float) $employee->gross_salary, 2) : '0.00',
            '{{document_number}}' => $doc->document_number ?: ('DOC-'.str_pad((string) $doc->id, 5, '0', STR_PAD_LEFT)),
            '{{issue_date}}' => $doc->issue_date ? $doc->issue_date->format('d M Y') : Carbon::now()->format('d M Y'),
            '{{purpose}}' => $details['purpose'] ?? 'Official Purpose',
            '{{to_address}}' => $details['to_address'] ?? 'To Whom It May Concern',
            '{{visa_designation}}' => $details['visa_designation'] ?? ($employee?->designation ?? 'Employee'),
            '{{payroll_month}}' => Carbon::now()->format('F Y'),
            '{{net_salary}}' => $employee?->gross_salary ? number_format((float) $employee->gross_salary, 2) : '0.00',
            '{{gross_earnings}}' => $employee?->gross_salary ? number_format((float) $employee->gross_salary, 2) : '0.00',
            '{{deductions_unpaid}}' => '0.00',
            '{{deductions_other}}' => '0.00',
            '{{total_deductions}}' => '0.00',
            '{{transport_allowance}}' => '0.00',
            '{{bank_account_masked}}' => '•••• •••• •••• 1234',
            '{{bank_name}}' => 'Emirates NBD',
            '{{account_number}}' => '1234567890',
            '{{iban_number}}' => 'AE000000000000000000000',
        ];

        foreach ($details as $k => $v) {
            $replacements['{{'.$k.'}}'] = (string) $v;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $template->template_code);
    }

    protected function payload(EmployeeDocument $doc, bool $canEdit = false): array
    {
        $employee = Employee::query()->with(['user:id,name,email', 'department:id,name', 'organisation'])->find($doc->employee_id);
        $latestFile = $doc->files->first();
        $ext = $latestFile ? strtolower(pathinfo($latestFile->file_path, PATHINFO_EXTENSION)) : null;

        $isLetterRequest = $doc->documentType?->category?->short_code === 'letter_requests';
        $isExpiredLetter = $isLetterRequest && $doc->created_at && $doc->created_at->copy()->addMonths(3)->isPast();
        $canAccessFile = ! $isExpiredLetter;

        return [
            'id' => $doc->id,
            'employee_id' => $doc->employee_id,
            'employee_name' => $employee?->user?->name ?? "Employee #{$doc->employee_id}",
            'employee_email' => $employee?->user?->email,
            'emp_num' => $employee?->emp_num,
            'document_type_id' => $doc->document_type_id,
            'document_name' => $doc->documentType?->document_name,
            'document_code' => $doc->documentType?->document_code,
            'category_id' => $doc->documentType?->category_id,
            'category_name' => $doc->documentType?->category?->category_name,
            'category_code' => $doc->documentType?->category?->short_code,
            'document_title' => $doc->document_title,
            'document_number' => $doc->document_number,
            'issue_date' => $doc->issue_date?->format('Y-m-d'),
            'issue_date_display' => $doc->issue_date?->format('d M Y'),
            'expiry_date' => $doc->expiry_date?->format('Y-m-d'),
            'expiry_date_display' => $doc->expiry_date?->format('d M Y'),
            'remarks' => $doc->remarks,
            'current_version' => $doc->current_version ?? '1.0',
            'status' => $isExpiredLetter ? 'expired' : $doc->status,
            'can_edit' => $canEdit && ! $isExpiredLetter,
            'created_at' => $doc->created_at?->format('d M Y H:i'),
            'file_url' => ($latestFile && $canAccessFile) ? route('employee-documents.file', $doc->id) : null,
            'download_url' => ($latestFile && $canAccessFile) ? route('employee-documents.download', $doc->id) : null,
            'file_extension' => $ext,
            'rendered_html' => $canAccessFile ? $this->renderDocumentHtml($doc, $employee) : null,
            'template' => $doc->documentTemplate ? [
                'id' => $doc->documentTemplate->id,
                'template_name' => $doc->documentTemplate->template_name,
            ] : null,
            'files' => $doc->files->map(fn ($f) => [
                'id' => $f->id,
                'version_no' => $f->version_no,
                'file_path' => $f->file_path,
                'file_url' => route('employee-documents.file', $doc->id),
                'download_url' => route('employee-documents.download', $doc->id),
                'uploaded_date' => $f->uploaded_date?->format('d M Y H:i'),
                'change_notes' => $f->change_notes,
            ]),
            'histories' => $doc->histories->map(fn ($h) => [
                'id' => $h->id,
                'action_type' => $h->action_type,
                'remarks' => $h->remarks,
                'action_on' => $h->action_on?->format('d M Y H:i'),
            ]),
            'request_details' => $doc->requestDetails->map(fn ($d) => [
                'field_name' => $d->field_name,
                'field_key' => $d->field_key,
                'field_value' => $d->field_value,
            ]),
        ];
    }
}
