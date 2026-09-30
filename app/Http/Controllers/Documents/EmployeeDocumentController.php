<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\DocumentCategory;
use App\Models\DocumentReminder;
use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentFile;
use App\Models\EmployeeDocumentHistory;
use App\Models\EmployeeProfile;
use App\Models\SalesCrm\Employee;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeDocumentController extends Controller
{
    public function index(Request $request): Response
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
            ->through(fn (EmployeeDocument $doc) => $this->payload($doc));

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
        $categories = DocumentCategory::query()
            ->with(['documentTypes.documentTemplates' => fn ($q) => $q->where('status', 1)])
            ->where('status', 1)
            ->get()
            ->map(function ($cat) {
                return [
                    'id' => $cat->id,
                    'category_name' => $cat->category_name,
                    'short_code' => $cat->short_code,
                    'document_types' => $cat->documentTypes->map(function ($type) {
                        $hasBlank = $type->documentTemplates->isNotEmpty();

                        return [
                            'id' => $type->id,
                            'category_id' => $type->category_id,
                            'document_name' => $type->document_name,
                            'document_code' => $type->document_code,
                            'requires_number' => (bool) $type->requires_number,
                            'requires_expiry' => (bool) $type->requires_expiry,
                            'requires_hr_approval' => (bool) $type->requires_hr_approval,
                            'has_blank_form' => $hasBlank,
                            'blank_form_url' => $hasBlank ? route('document-types.blank-form', ['document_type' => $type->id, 'download' => 1]) : null,
                        ];
                    }),
                ];
            });

        $downloadableForms = DocumentType::query()
            ->whereHas('documentTemplates', fn ($q) => $q->where('status', 1))
            ->get(['id', 'document_name', 'document_code'])
            ->map(fn (DocumentType $t) => [
                'id' => $t->id,
                'name' => $t->document_name,
                'code' => $t->document_code,
                'download_url' => route('document-types.blank-form', ['document_type' => $t->id, 'download' => 1]),
            ]);

        $employees = Employee::query()->with('user:id,name,email')->get(['id', 'user_id', 'emp_num', 'designation']);

        return Inertia::render('employee-documents/create', [
            'categories' => $categories,
            'employees' => $employees,
            'downloadableForms' => $downloadableForms,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'employee_id' => 'required|integer',
            'document_type_id' => 'required|exists:document_types,id',
            'document_template_id' => 'nullable|exists:document_templates,id',
            'organisation_id' => 'nullable|integer|exists:organisations,id',
            'document_number' => 'nullable|string|max:100',
            'document_title' => 'nullable|string|max:255',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'remarks' => 'nullable|string',
            'status' => 'nullable|string|in:submitted,approved,draft',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $docType = DocumentType::query()->findOrFail($request->input('document_type_id'));
        $employee = Employee::query()->find($request->input('employee_id'));
        $organisationId = $request->input('organisation_id') ?? $employee?->organisation_id;
        $templateId = $request->input('document_template_id')
            ?? $docType->resolveTemplate($organisationId)?->id;

        $status = $request->input('status');
        if (! in_array($status, ['submitted', 'approved', 'draft'], true)) {
            $status = $docType->requires_hr_approval ? 'submitted' : 'approved';
        }

        DB::transaction(function () use ($request, $docType, $status, $organisationId, $templateId) {
            $document = EmployeeDocument::query()->create([
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
            ]);

            $filePath = $request->file('file')->store('employee_documents', 'public');
            EmployeeDocumentFile::query()->create([
                'employee_document_id' => $document->id,
                'version_no' => '1.0',
                'file_path' => $filePath,
                'uploaded_by' => $request->user()?->id,
                'uploaded_date' => Carbon::now(),
                'change_notes' => $status === 'submitted' ? 'Uploaded document awaiting HR approval' : 'Uploaded by HR',
            ]);

            EmployeeDocumentHistory::query()->create([
                'employee_document_id' => $document->id,
                'action_type' => $status,
                'remarks' => $status === 'submitted' ? 'Document uploaded and awaiting HR approval' : 'Document uploaded and approved by HR',
                'done_by' => $request->user()?->id,
                'action_on' => Carbon::now(),
            ]);

            if ($status === 'approved') {
                $this->syncWithProfile($document);
            }

            if ($docType->requires_reminder && $request->filled('expiry_date')) {
                $expiryDate = Carbon::parse($request->input('expiry_date'));
                DocumentReminder::query()->create([
                    'employee_document_id' => $document->id,
                    'reminder_date' => (clone $expiryDate)->subDays(90),
                    'days_before' => 90,
                    'status' => 'pending',
                ]);
            }
        });

        $message = $status === 'submitted'
            ? 'Document uploaded successfully and queued for HR approval.'
            : 'Document uploaded and approved successfully.';

        return redirect()->route('employee-documents.index')
            ->with('toast', ['type' => 'success', 'message' => $message]);
    }

    public function show(EmployeeDocument $employeeDocument): Response
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
            'document' => $this->payload($employeeDocument),
            'employee' => $employee,
        ]);
    }

    public function approve(Request $request, EmployeeDocument $employeeDocument): RedirectResponse
    {
        $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($request, $employeeDocument) {
            $employeeDocument->update([
                'status' => 'approved',
            ]);

            EmployeeDocumentHistory::query()->create([
                'employee_document_id' => $employeeDocument->id,
                'action_type' => 'approved',
                'remarks' => $request->input('remarks', 'Approved by HR'),
                'done_by' => $request->user()?->id,
                'action_on' => Carbon::now(),
            ]);

            $this->syncWithProfile($employeeDocument);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Document approved successfully.']);
    }

    public function reject(Request $request, EmployeeDocument $employeeDocument): RedirectResponse
    {
        $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        $employeeDocument->update([
            'status' => 'rejected',
            'remarks' => $request->input('remarks'),
        ]);

        EmployeeDocumentHistory::query()->create([
            'employee_document_id' => $employeeDocument->id,
            'action_type' => 'rejected',
            'remarks' => $request->input('remarks'),
            'done_by' => $request->user()?->id,
            'action_on' => Carbon::now(),
        ]);

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

    /**
     * Auto-sync approved document metadata with the employee profile.
     */
    protected function syncWithProfile(EmployeeDocument $doc): void
    {
        $profile = EmployeeProfile::query()->where('employee_id', $doc->employee_id)->first();
        if (! $profile) {
            return;
        }

        $code = $doc->documentType?->document_code;

        if ($code === 'passport' && $doc->expiry_date) {
            $profile->update(['dob_passport' => $doc->issue_date]);
        } elseif ($code === 'education') {
            $profile->update(['highest_education' => $doc->document_title]);
        }
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

    protected function payload(EmployeeDocument $doc): array
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
