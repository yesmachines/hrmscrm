<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentTypeRequest;
use App\Http\Requests\Documents\UpdateDocumentTypeRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DocumentTypeController extends Controller
{
    public function index(): Response
    {
        $types = DocumentType::query()
            ->with('category:id,category_name')
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
            'category' => $type->category
                ? [
                    'id' => $type->category->id,
                    'name' => $type->category->category_name,
                ]
                : null,
        ];
    }
}
