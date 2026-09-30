<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentTypeRequest;
use App\Http\Requests\Documents\UpdateDocumentTypeRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentType;
use App\Models\SalesCrm\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DocumentTypeController extends Controller
{
    public function index(): Response
    {
        $types = DocumentType::query()
            ->with(['category:id,category_name', 'documentTemplates' => fn ($q) => $q->where('status', 1)])
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (DocumentType $type): array => $this->payload($type));

        return Inertia::render('document-types/index', [
            'documentTypes' => $types,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('document-types/create', [
            'categories' => $this->categories(),
        ]);
    }

    public function store(StoreDocumentTypeRequest $request): RedirectResponse
    {
        $type = DocumentType::query()->create($request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Document type created.'),
        ]);

        return to_route('document-types.show', $type);
    }

    public function show(DocumentType $document_type): Response
    {
        $document_type->load('category:id,category_name');

        return Inertia::render('document-types/show', [
            'documentType' => $this->payload($document_type),
        ]);
    }

    public function edit(DocumentType $document_type): Response
    {
        $document_type->load('category:id,category_name');

        return Inertia::render('document-types/edit', [
            'documentType' => $this->payload($document_type),
            'categories' => $this->categories(),
        ]);
    }

    public function update(
        UpdateDocumentTypeRequest $request,
        DocumentType $document_type,
    ): RedirectResponse {
        $document_type->update($request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Document type updated.'),
        ]);

        return to_route('document-types.show', $document_type);
    }

    public function destroy(DocumentType $document_type): RedirectResponse
    {
        if ($document_type->templates()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Cannot delete a document type that has templates.'),
            ]);

            return back();
        }

        $document_type->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Document type deleted.'),
        ]);

        return to_route('document-types.index');
    }

    /**
     * Download or stream the official blank form template PDF for a document type.
     */
    public function downloadBlankForm(Request $request, DocumentType $document_type): HttpResponse
    {
        $user = $request->user();
        $employee = null;
        if ($user) {
            $employee = Employee::where('user_id', $user->id)
                ->with(['department', 'user', 'organisation'])
                ->first();
        }

        $template = $document_type->resolveTemplate($employee?->organisation_id);

        if (! $template || empty($template->template_code)) {
            abort(404, 'No blank form template available for this document type.');
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

        $pdfHtml = '<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>'.htmlspecialchars($document_type->document_name).'</title>
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

        $pdf = Pdf::loadHTML($pdfHtml);
        $pdf->setPaper('a4', 'portrait');

        $filename = Str::slug($document_type->document_name).'-blank-form.pdf';

        if ($request->query('stream') === '1' || $request->query('view') === '1') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function categories(): array
    {
        return DocumentCategory::query()
            ->where('status', 1)
            ->orderBy('category_name')
            ->get(['id', 'category_name'])
            ->map(fn (DocumentCategory $category): array => [
                'id' => $category->id,
                'name' => $category->category_name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DocumentType $type): array
    {
        $hasTemplate = $type->relationLoaded('documentTemplates')
            ? $type->documentTemplates->isNotEmpty()
            : $type->documentTemplates()->where('status', 1)->exists();

        return [
            'id' => $type->id,
            'category_id' => $type->category_id,
            'document_name' => $type->document_name,
            'document_code' => $type->document_code,
            'requires_number' => $type->requires_number,
            'requires_expiry' => $type->requires_expiry,
            'editable_before_approval' => $type->editable_before_approval,
            'requires_hr_approval' => $type->requires_hr_approval,
            'requires_reminder' => $type->requires_reminder,
            'record_source' => $type->record_source,
            'requires_attachments' => $type->requires_attachments,
            'validity_days' => $type->validity_days,
            'has_blank_form' => $hasTemplate,
            'blank_form_url' => $hasTemplate ? route('document-types.blank-form', $type->id) : null,
            'category' => $type->category
                ? [
                    'id' => $type->category->id,
                    'name' => $type->category->category_name,
                ]
                : null,
        ];
    }
}
