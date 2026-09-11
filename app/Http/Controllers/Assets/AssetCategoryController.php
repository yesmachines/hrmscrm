<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AssetCategory::query()->withCount('assets');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('category', 'like', "%{$search}%")
                ->orWhere('shortcode', 'like', "%{$search}%");
        }

        $categories = $query->orderBy('category')->paginate(15)->withQueryString();

        return Inertia::render('assets/categories/index', [
            'categories' => $categories,
            'filters' => [
                'search' => $request->input('search', ''),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'shortcode' => 'required|string|max:50|unique:asset_categories,shortcode',
            'status' => 'required|in:0,1',
        ]);

        AssetCategory::create([
            'category' => $validated['category'],
            'shortcode' => strtoupper($validated['shortcode']),
            'status' => (int) $validated['status'],
        ]);

        return back()->with('success', 'Asset category created successfully.');
    }

    public function update(Request $request, AssetCategory $asset_category): RedirectResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'shortcode' => 'required|string|max:50|unique:asset_categories,shortcode,'.$asset_category->id,
            'status' => 'required|in:0,1',
        ]);

        $asset_category->update([
            'category' => $validated['category'],
            'shortcode' => strtoupper($validated['shortcode']),
            'status' => (int) $validated['status'],
        ]);

        return back()->with('success', 'Asset category updated successfully.');
    }

    public function destroy(AssetCategory $asset_category): RedirectResponse
    {
        if ($asset_category->assets()->exists()) {
            return back()->with('error', 'Cannot delete category that has existing assets.');
        }

        $asset_category->delete();

        return back()->with('success', 'Asset category deleted successfully.');
    }
}
