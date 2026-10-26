<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\SalesCrm\Employee;
use App\Services\AssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request, AssetService $assetService): Response
    {
        $assets = $assetService->listInventory(
            $request->only(['category_id', 'status', 'condition', 'search']),
        )->withQueryString();

        $categories = AssetCategory::query()->where('status', 1)->select('id', 'category')->orderBy('category')->get();

        return Inertia::render('assets/index', [
            'assets' => $assets,
            'categories' => $categories,
            'filters' => [
                'category_id' => $request->input('category_id', ''),
                'status' => $request->input('status', ''),
                'condition' => $request->input('condition', ''),
                'search' => $request->input('search', ''),
            ],
        ]);
    }

    public function create(): Response
    {
        $categories = AssetCategory::query()->where('status', 1)->select('id', 'category', 'shortcode')->orderBy('category')->get();
        $employees = Employee::with('user:id,name')->select('id', 'user_id', 'emp_num', 'employee_code', 'designation')->where('status', 1)->orderBy('id')->get();

        return Inertia::render('assets/create', [
            'categories' => $categories,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:asset_categories,id',
            'referenceno' => 'required|string|max:100|unique:assets,referenceno',
            'asset_name' => 'required|string|max:255',
            'details' => 'nullable|string',
            'condition' => 'required|in:New,Excellent,Good,Fair,Damaged,Needs Repair',
            'status' => 'required|in:Active,Returned,Under Maintenance,Retired,Lost',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'assigned_to' => 'nullable|exists:salescrm.employees,id',
            'assigned_date' => 'nullable|date',
            'assignment_note' => 'nullable|string',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('assets', 'public');
        }

        $asset = Asset::create([
            'category_id' => $validated['category_id'],
            'referenceno' => $validated['referenceno'],
            'asset_name' => $validated['asset_name'],
            'details' => $validated['details'] ?? null,
            'condition' => $validated['condition'],
            'status' => $validated['status'],
            'attachment' => $attachmentPath,
        ]);

        // If direct assignment requested
        if (! empty($validated['assigned_to'])) {
            $asset->assignments()->create([
                'assigned_to' => $validated['assigned_to'],
                'assigned_date' => $validated['assigned_date'] ?? now()->toDateString(),
                'note' => $validated['assignment_note'] ?? null,
                'status' => 'Assigned',
            ]);
        }

        return redirect()->route('assets.show', $asset)->with('success', 'Asset added successfully.');
    }

    public function show(Asset $asset): Response
    {
        $asset->load([
            'category',
            'assignments' => function ($q) {
                $q->orderBy('assigned_date', 'desc');
            },
            'assignments.assignee.user:id,name,email',
            'requests' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
            'requests.requester.user:id,name',
        ]);

        $employees = Employee::with('user:id,name')->select('id', 'user_id', 'emp_num', 'employee_code', 'designation')->where('status', 1)->orderBy('id')->get();

        return Inertia::render('assets/show', [
            'asset' => $asset,
            'employees' => $employees,
        ]);
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:asset_categories,id',
            'referenceno' => 'required|string|max:100|unique:assets,referenceno,'.$asset->id,
            'asset_name' => 'required|string|max:255',
            'details' => 'nullable|string',
            'condition' => 'required|in:New,Excellent,Good,Fair,Damaged,Needs Repair',
            'status' => 'required|in:Active,Returned,Under Maintenance,Retired,Lost',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        if ($request->hasFile('attachment')) {
            $validated['attachment'] = $request->file('attachment')->store('assets', 'public');
        }

        $asset->update($validated);

        return back()->with('success', 'Asset updated successfully.');
    }

    public function assign(Request $request, Asset $asset, AssetService $assetService): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => 'required|exists:salescrm.employees,id',
            'assigned_date' => 'required|date',
            'note' => 'nullable|string',
            'asset_request_id' => 'nullable|exists:asset_requests,id',
        ]);

        $assetService->assignToEmployee(
            $asset,
            (int) $validated['assigned_to'],
            $validated['assigned_date'],
            $validated['note'] ?? null,
            isset($validated['asset_request_id']) ? (int) $validated['asset_request_id'] : null,
            isset($validated['asset_request_id']) ? null : "Fulfillment: Asset assigned on {$validated['assigned_date']}.",
        );

        return back()->with('success', 'Asset assigned to employee successfully.');
    }

    public function returnAsset(Request $request, AssetAssignment $assignment, AssetService $assetService): RedirectResponse
    {
        $validated = $request->validate([
            'returned_date' => 'required|date',
            'return_condition' => 'nullable|in:New,Excellent,Good,Fair,Damaged,Needs Repair',
            'note' => 'nullable|string',
        ]);

        $asset = $assignment->asset;
        $note = $validated['note']
            ? ($assignment->note ? $assignment->note."\nReturn Note: ".$validated['note'] : $validated['note'])
            : $assignment->note;

        $assetService->returnAsset(
            $asset,
            $validated['returned_date'],
            $validated['return_condition'] ?? null,
            $note,
            $assignment,
        );

        return back()->with('success', 'Asset marked as returned successfully.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        if ($asset->assignments()->where('status', 'Assigned')->exists()) {
            return back()->with('error', 'Cannot delete an asset that is currently assigned to an employee.');
        }

        $asset->delete();

        return redirect()->route('assets.index')->with('success', 'Asset deleted successfully.');
    }
}
