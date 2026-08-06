<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentTemplateRequest;
use App\Http\Requests\UpdateDocumentTemplateRequest;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DocumentTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = DocumentTemplate::query()
            ->with('documentType:id,document_name,document_code')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (DocumentTemplate $template): array => $this->payload($template));

        return Inertia::render('document-templates/index', [
            'templates' => $templates,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('document-templates/create', [
            'documentTypes' => $this->documentTypes(),
        ]);
    }

    public function store(StoreDocumentTemplateRequest $request): RedirectResponse
    {
        $template = DocumentTemplate::query()->create($request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Document template created.'),
        ]);

        return to_route('document-templates.show', $template);
    }

    public function show(DocumentTemplate $document_template): Response
    {
        $document_template->load('documentType:id,document_name,document_code');

        return Inertia::render('document-templates/show', [
            'template' => $this->payload($document_template),
        ]);
    }

    public function edit(DocumentTemplate $document_template): Response
    {
        $document_template->load('documentType:id,document_name,document_code');

        return Inertia::render('document-templates/edit', [
            'template' => $this->payload($document_template),
            'documentTypes' => $this->documentTypes(),
        ]);
    }

    public function update(
        UpdateDocumentTemplateRequest $request,
        DocumentTemplate $document_template,
    ): RedirectResponse {
        $document_template->update($request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Document template updated.'),
        ]);

        return to_route('document-templates.show', $document_template);
    }

    public function destroy(DocumentTemplate $document_template): RedirectResponse
    {
        $document_template->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Document template deleted.'),
        ]);

        return to_route('document-templates.index');
    }

    /**
     * @return list<array{id: int, name: string, code: string}>
     */
    private function documentTypes(): array
    {
        return DocumentType::query()
            ->orderBy('document_name')
            ->get(['id', 'document_name', 'document_code'])
            ->map(fn (DocumentType $type): array => [
                'id' => $type->id,
                'name' => $type->document_name,
                'code' => $type->document_code,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DocumentTemplate $template): array
    {
        return [
            'id' => $template->id,
            'document_type_id' => $template->document_type_id,
            'template_name' => $template->template_name,
            'template_code' => $template->template_code,
            'status' => $template->status,
            'document_type' => $template->documentType
                ? [
                    'id' => $template->documentType->id,
                    'name' => $template->documentType->document_name,
                    'code' => $template->documentType->document_code,
                ]
                : null,
        ];
    }
}
