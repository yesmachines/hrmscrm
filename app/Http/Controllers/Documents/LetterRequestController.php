<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use App\Services\LetterRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LetterRequestController extends Controller
{
    public function __construct(private LetterRequestService $letterRequestService) {}

    public function index(Request $request): Response
    {
        $requests = $this->letterRequestService->listForHr($request)
            ->through(fn (EmployeeDocument $doc) => $this->payload($doc));

        return Inertia::render('letter-requests/index', [
            'requests' => $requests,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function show(EmployeeDocument $letterRequest): Response
    {
        $letterRequest = $this->letterRequestService->findForHr($letterRequest->id);

        return Inertia::render('letter-requests/show', [
            'request' => $this->payload($letterRequest),
            'employee' => $letterRequest->employee,
        ]);
    }

    public function approve(Request $request, EmployeeDocument $letterRequest): RedirectResponse
    {
        $request->validate([
            'salary_amount' => 'nullable|string|max:50',
            'bank_iban' => 'nullable|string|max:100',
            'to_address' => 'nullable|string|max:500',
            'destination_country' => 'nullable|string|max:100',
            'purpose' => 'nullable|string|max:1000',
            'remarks' => 'nullable|string|max:500',
        ]);

        $this->letterRequestService->approve(
            $letterRequest,
            $request->only(['salary_amount', 'bank_iban', 'to_address', 'destination_country', 'purpose', 'remarks']),
            (int) $request->user()->id,
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Letter request approved successfully.']);
    }

    public function reject(Request $request, EmployeeDocument $letterRequest): RedirectResponse
    {
        $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        $this->letterRequestService->reject(
            $letterRequest,
            $request->input('remarks'),
            (int) $request->user()->id,
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Letter request rejected.']);
    }

    protected function payload(EmployeeDocument $doc): array
    {
        $doc->loadMissing([
            'employee.user:id,name,email',
            'employee.department:id,name',
            'employee.organisation:id,org_name',
            'documentType',
            'documentTemplate',
            'requestDetails',
            'histories' => fn ($query) => $query->orderByDesc('id'),
            'files' => fn ($query) => $query->orderByDesc('id'),
        ]);
        $employee = $doc->employee;
        $details = $doc->requestDetails->pluck('field_value', 'field_key');

        $latestFile = $doc->files->first();

        return [
            'id' => $doc->id,
            'employee_id' => $doc->employee_id,
            'employee_name' => $employee?->user?->name ?? "Employee #{$doc->employee_id}",
            'employee_email' => $employee?->user?->email,
            'emp_num' => $employee?->emp_num,
            'document_name' => $doc->documentType?->document_name,
            'document_code' => $doc->documentType?->document_code,
            'purpose' => $details['purpose'] ?? 'General Request',
            'details' => $details['details'] ?? $doc->remarks,
            'to_address' => $details['to_address'] ?? null,
            'destination_country' => $details['destination_country'] ?? null,
            'visa_designation' => $details['visa_designation'] ?? null,
            'salary_amount' => $details['salary_amount'] ?? null,
            'bank_iban' => $details['bank_iban'] ?? null,
            'status' => $doc->status,
            'remarks' => $doc->remarks,
            'applied_date' => $doc->created_at?->format('d M Y'),
            'approved_date' => $doc->status === 'approved' ? $doc->updated_at?->format('d M Y') : null,
            'rendered_html' => $this->letterRequestService->renderDocumentHtml($doc, $employee),
            'file_url' => $latestFile ? route('employee-documents.file', $doc->id) : null,
            'download_url' => $latestFile ? route('employee-documents.download', $doc->id) : null,
            'histories' => $doc->histories->map(fn ($h) => [
                'id' => $h->id,
                'action_type' => $h->action_type,
                'remarks' => $h->remarks,
                'action_on' => $h->action_on?->format('d M Y H:i'),
            ]),
        ];
    }
}
