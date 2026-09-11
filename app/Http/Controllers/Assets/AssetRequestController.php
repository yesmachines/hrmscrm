<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetRequest;
use App\Models\SalesCrm\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AssetRequest::with([
            'requester:id,user_id,employee_code,emp_num,designation',
            'requester.user:id,name',
            'asset:id,referenceno,asset_name',
            'category:id,category',
            'approver:id,name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('request_type')) {
            $query->where('request_type', $request->input('request_type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('request_no', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('asset', function ($aq) use ($search) {
                        $aq->where('asset_name', 'like', "%{$search}%")
                            ->orWhere('referenceno', 'like', "%{$search}%");
                    })
                    ->orWhereHas('requester.user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $requests = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        return Inertia::render('assets/requests/index', [
            'requests' => $requests,
            'filters' => [
                'status' => $request->input('status', ''),
                'priority' => $request->input('priority', ''),
                'request_type' => $request->input('request_type', ''),
                'search' => $request->input('search', ''),
            ],
        ]);
    }

    public function create(): Response
    {
        $assignments = AssetAssignment::with(['asset.category'])
            ->where('status', 'Assigned')
            ->get()
            ->groupBy('assigned_to');

        $employees = Employee::with('user:id,name,email')
            ->where('status', 1)
            ->get()
            ->map(function ($emp) use ($assignments) {
                $assignedList = $assignments->get($emp->id, collect())->map(function ($a) {
                    return [
                        'id' => $a->asset_id,
                        'asset_id' => $a->asset_id,
                        'asset_name' => $a->asset?->asset_name,
                        'referenceno' => $a->asset?->referenceno,
                        'category_id' => $a->asset?->category_id,
                        'category' => $a->asset?->category?->category,
                        'condition' => $a->asset?->condition,
                        'label' => $a->asset ? "{$a->asset->asset_name} ({$a->asset->referenceno})" : '',
                    ];
                })->filter(fn ($item) => ! empty($item['id']))->values();

                return [
                    'id' => $emp->id,
                    'name' => $emp->user?->name ?? 'Unknown',
                    'code' => $emp->employee_code ?? $emp->emp_num,
                    'assigned_assets' => $assignedList,
                ];
            });

        $categories = AssetCategory::where('status', 1)->select('id', 'category')->get();

        return Inertia::render('assets/requests/create', [
            'employees' => $employees,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'requested_by' => 'required|exists:salescrm.employees,id',
            'request_type' => 'required|in:New,Repair,Replacement,Lost,Damage',
            'asset_id' => 'nullable|exists:assets,id',
            'category_id' => 'nullable|exists:asset_categories,id',
            'description' => 'required|string',
            'priority' => 'required|in:Low,Normal,High,Urgent',
        ]);

        $requestType = $validated['request_type'];
        $assetId = null;
        $categoryId = null;

        if ($requestType === 'New') {
            if (empty($validated['category_id'])) {
                return back()->withErrors(['category_id' => 'Please select an asset category for new asset request.']);
            }
            $categoryId = $validated['category_id'];
        } else {
            if (empty($validated['asset_id'])) {
                return back()->withErrors(['asset_id' => 'Please select one of the employee\'s assigned assets.']);
            }

            // Verify assignment
            $isAssigned = AssetAssignment::where('asset_id', $validated['asset_id'])
                ->where('assigned_to', $validated['requested_by'])
                ->where('status', 'Assigned')
                ->exists();

            if (! $isAssigned) {
                return back()->withErrors(['asset_id' => 'The selected asset is not currently assigned to this employee.']);
            }

            $assetId = $validated['asset_id'];
            $asset = Asset::find($assetId);
            $categoryId = $asset?->category_id ?? ($validated['category_id'] ?? null);
        }

        $requestNo = 'AR-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));

        $assetRequest = AssetRequest::create([
            'request_no' => $requestNo,
            'request_type' => $requestType,
            'asset_id' => $assetId,
            'category_id' => $categoryId,
            'requested_by' => $validated['requested_by'],
            'requested_date' => now()->toDateString(),
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'status' => 'Pending',
        ]);

        return redirect()->route('asset-requests.show', $assetRequest->id)
            ->with('success', 'Asset request created successfully.');
    }

    public function show(AssetRequest $asset_request): Response
    {
        $asset_request->load([
            'requester:id,user_id,employee_code,emp_num,designation',
            'requester.user:id,name,email',
            'asset.category',
            'category',
            'approver:id,name',
        ]);

        return Inertia::render('assets/requests/show', [
            'assetRequest' => $asset_request,
        ]);
    }

    public function approve(Request $request, AssetRequest $asset_request): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => 'nullable|string',
        ]);

        $asset_request->update([
            'status' => 'Approved',
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        return back()->with('success', 'Asset request approved successfully.');
    }

    public function reject(Request $request, AssetRequest $asset_request): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $asset_request->update([
            'status' => 'Rejected',
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return back()->with('success', 'Asset request rejected.');
    }

    public function updateStatus(Request $request, AssetRequest $asset_request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:Under Review,In Progress,Completed,Cancelled',
            'admin_notes' => 'nullable|string',
        ]);

        $updateData = ['status' => $validated['status']];
        if (! empty($validated['admin_notes'])) {
            $updateData['admin_notes'] = $validated['admin_notes'];
        }

        $asset_request->update($updateData);

        return back()->with('success', 'Asset request status updated to '.$validated['status'].'.');
    }
}
