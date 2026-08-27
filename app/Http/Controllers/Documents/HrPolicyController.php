<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentFile;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HrPolicyController extends Controller
{
    public function index(): Response
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

                return [
                    'id' => $doc->id,
                    'policy_name' => $doc->document_title ?: $doc->documentType?->document_name,
                    'document_code' => $doc->documentType?->document_code,
                    'version' => 'Version '.($doc->current_version ?? '1.0'),
                    'remarks' => $doc->remarks,
                    'updated_at' => $doc->updated_at ? $doc->updated_at->format('d M Y') : '15 Jan 2026',
                    'file_url' => $latestFile ? Storage::disk('public')->url($latestFile->file_path) : null,
                    'files' => $doc->files->map(fn ($f) => [
                        'id' => $f->id,
                        'version_no' => $f->version_no,
                        'file_url' => Storage::disk('public')->url($f->file_path),
                        'uploaded_date' => $f->uploaded_date?->format('d M Y H:i'),
                        'change_notes' => $f->change_notes,
                    ]),
                ];
            });

        $policyTypes = DocumentType::query()
            ->whereHas('category', fn ($q) => $q->where('short_code', 'hr_docs'))
            ->get(['id', 'document_name', 'document_code']);

        return Inertia::render('hr-policies/index', [
            'policies' => $policies,
            'policyTypes' => $policyTypes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'policy_name' => 'required|string|max:255',
            'version' => 'required|string|max:20',
            'remarks' => 'nullable|string',
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        DB::transaction(function () use ($request) {
            $docType = DocumentType::query()->findOrFail($request->input('document_type_id'));

            $document = EmployeeDocument::query()->create([
                'employee_id' => 0, // 0 signifies company-wide policy
                'document_type_id' => $docType->id,
                'document_title' => $request->input('policy_name'),
                'current_version' => $request->input('version'),
                'remarks' => $request->input('remarks'),
                'status' => 'approved',
                'created_by' => $request->user()?->id,
            ]);

            $filePath = $request->file('file')->store('company_policies', 'public');
            EmployeeDocumentFile::query()->create([
                'employee_document_id' => $document->id,
                'version_no' => $request->input('version'),
                'file_path' => $filePath,
                'uploaded_by' => $request->user()?->id,
                'uploaded_date' => Carbon::now(),
                'change_notes' => $request->input('remarks', 'Initial version release'),
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Policy document uploaded successfully.']);
    }
}
