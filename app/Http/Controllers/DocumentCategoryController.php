<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentCategoryRequest;
use App\Http\Requests\UpdateDocumentCategoryRequest;
use App\Models\DocumentCategory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DocumentCategoryController extends Controller
{
    public function index(): Response
    {
        $categories = DocumentCategory::query()
            ->with('parent:id,category_name')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (DocumentCategory $category): array => $this->payload($category));

        return Inertia::render('document-categories/index', [
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('document-categories/create', [
            'parents' => $this->parentOptions(),
        ]);
    }

    public function store(StoreDocumentCategoryRequest $request): RedirectResponse
    {
        $category = DocumentCategory::query()->create($request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Document category created.'),
        ]);

        return to_route('document-categories.show', $category);
    }

    public function show(DocumentCategory $document_category): Response
    {
        $document_category->load('parent:id,category_name');

        return Inertia::render('document-categories/show', [
            'category' => $this->payload($document_category),
        ]);
    }

    public function edit(DocumentCategory $document_category): Response
    {
        $document_category->load('parent:id,category_name');

        return Inertia::render('document-categories/edit', [
            'category' => $this->payload($document_category),
            'parents' => $this->parentOptions($document_category->id),
        ]);
    }

    public function update(
        UpdateDocumentCategoryRequest $request,
        DocumentCategory $document_category,
    ): RedirectResponse {
        $document_category->update($request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Document category updated.'),
        ]);

        return to_route('document-categories.show', $document_category);
    }

    public function destroy(DocumentCategory $document_category): RedirectResponse
    {
        if ($document_category->children()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Cannot delete a category that has child categories.'),
            ]);

            return back();
        }

        if ($document_category->documentTypes()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Cannot delete a category that has document types.'),
            ]);

            return back();
        }

        $document_category->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Document category deleted.'),
        ]);

        return to_route('document-categories.index');
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function parentOptions(?int $excludeId = null): array
    {
        return DocumentCategory::query()
            ->when($excludeId, fn ($query) => $query->where('id', '<>', $excludeId))
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
    private function payload(DocumentCategory $category): array
    {
        return [
            'id' => $category->id,
            'category_name' => $category->category_name,
            'short_code' => $category->short_code,
            'status' => $category->status,
            'parent_id' => $category->parent_id,
            'parent' => $category->parent
                ? [
                    'id' => $category->parent->id,
                    'name' => $category->parent->category_name,
                ]
                : null,
        ];
    }
}
