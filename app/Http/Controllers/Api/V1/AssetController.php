<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetRequest;
use App\Models\SalesCrm\Employee;
use App\Support\SalesCrmRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    /**
     * List all assets assigned to the authenticated employee (or all inventory for HR/Admin with ?all=1).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        $isHrOrAdmin = SalesCrmRoles::userHasLoginAccess((int) $user->id);

        // If HR/Admin requests all inventory
        if ($isHrOrAdmin && ($request->boolean('all') || $request->has('all'))) {
            $query = Asset::with([
                'category:id,category,shortcode',
                'currentAssignment.assignee.user:id,name,email',
            ]);

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->input('category_id'));
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('asset_name', 'like', "%{$search}%")
                        ->orWhere('referenceno', 'like', "%{$search}%")
                        ->orWhere('details', 'like', "%{$search}%");
                });
            }

            $assets = $query->orderBy('id', 'desc')->paginate(15);

            return $this->successPaginatedResponse($assets, 'assets');
        }

        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $assignments = AssetAssignment::with([
            'asset:id,category_id,referenceno,asset_name,details,condition,status,attachment',
            'asset.category:id,category,shortcode',
        ])
            ->where('assigned_to', $employee->id)
            ->where('status', 'Assigned')
            ->orderBy('assigned_date', 'desc')
            ->get();

        $items = $assignments->map(function ($assignment) use ($employee) {
            $asset = $assignment->asset;

            return [
                'assignment_id' => $assignment->id,
                'asset_id' => $asset?->id,
                'asset_name' => $asset?->asset_name,
                'referenceno' => $asset?->referenceno,
                'category' => $asset?->category?->category,
                'category_shortcode' => $asset?->category?->shortcode,
                'details' => $asset?->details,
                'condition' => $asset?->condition,
                'status' => $asset?->status,
                'attachment_url' => $asset?->attachment ? asset('storage/'.$asset->attachment) : null,
                'assigned_date' => $assignment->assigned_date?->format('Y-m-d'),
                'condition_during_assign' => $assignment->note,
                'assigned_to' => [
                    'id' => $employee->id,
                    'name' => $employee->user?->name,
                    'employee_code' => $employee->employee_code ?? $employee->emp_num,
                ],
            ];
        });

        return $this->successResponse([
            'assets' => $items,
        ]);
    }

    /**
     * View detailed asset profile.
     */
    public function show(Request $request, Asset $asset): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $asset->load([
            'category:id,category,shortcode',
            'currentAssignment.assignee.user:id,name,email',
            'assignments.assignee.user:id,name',
        ]);

        $assignment = $asset->currentAssignment;

        return $this->successResponse([
            'asset' => [
                'id' => $asset->id,
                'asset_name' => $asset->asset_name,
                'referenceno' => $asset->referenceno,
                'category' => $asset->category?->category,
                'category_shortcode' => $asset->category?->shortcode,
                'details' => $asset->details,
                'condition' => $asset->condition,
                'status' => $asset->status,
                'attachment_url' => $asset->attachment ? asset('storage/'.$asset->attachment) : null,
                'assigned_to' => $assignment?->assignee?->user?->name,
                'assigned_to_id' => $assignment?->assigned_to,
                'assigned_date' => $assignment?->assigned_date?->format('Y-m-d'),
                'condition_during_assign' => $assignment?->note,
                'assignments_history' => $asset->assignments->map(fn ($a) => [
                    'id' => $a->id,
                    'assigned_to' => $a->assignee?->user?->name,
                    'assigned_date' => $a->assigned_date?->format('Y-m-d'),
                    'returned_date' => $a->returned_date?->format('Y-m-d'),
                    'status' => $a->status,
                    'note' => $a->note,
                ]),
            ],
        ]);
    }

    /**
     * List active asset categories for new asset requests.
     */
    public function categories(): JsonResponse
    {
        $categories = AssetCategory::query()
            ->where('status', 1)
            ->select('id', 'category', 'shortcode')
            ->orderBy('category')
            ->get();

        return $this->successResponse([
            'categories' => $categories,
        ]);
    }

    /**
     * List assets currently assigned to the authenticated employee for dropdown selection.
     */
    public function assignedDropdown(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $assignments = AssetAssignment::with([
            'asset:id,category_id,referenceno,asset_name,condition,status,attachment',
            'asset.category:id,category,shortcode',
        ])
            ->where('assigned_to', $employee->id)
            ->where('status', 'Assigned')
            ->orderBy('assigned_date', 'desc')
            ->get();

        $dropdown = $assignments->map(function ($assignment) {
            $asset = $assignment->asset;
            if (! $asset) {
                return null;
            }

            return [
                'id' => $asset->id,
                'asset_id' => $asset->id,
                'asset_name' => $asset->asset_name,
                'referenceno' => $asset->referenceno,
                'category_id' => $asset->category_id,
                'category' => $asset->category?->category,
                'condition' => $asset->condition,
                'condition_during_assign' => $assignment->note,
                'assigned_date' => $assignment->assigned_date?->format('Y-m-d'),
                'label' => "{$asset->asset_name} ({$asset->referenceno})",
                'attachment_url' => $asset->attachment ? asset('storage/'.$asset->attachment) : null,
            ];
        })->filter()->values();

        return $this->successResponse([
            'assigned_assets' => $dropdown,
        ], 'Assigned assets retrieved successfully for dropdown.');
    }

    /**
     * Submit an asset request (New, Repair, Replacement, Lost, Damage).
     */
    public function submitRequest(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $validated = $request->validate([
            'request_type' => 'required|in:New,Repair,Replacement,Lost,Damage',
            'asset_id' => 'nullable|exists:assets,id',
            'category_id' => 'nullable|exists:asset_categories,id',
            'description' => 'required|string',
            'priority' => 'nullable|in:Low,Normal,High,Urgent',
        ]);

        $requestType = $validated['request_type'];
        $assetId = null;
        $categoryId = null;

        if ($requestType === 'New') {
            // New asset request requires selecting a category
            if (empty($validated['category_id'])) {
                return $this->errorResponse('Please select an asset category for your new asset request.', 422);
            }
            $categoryId = $validated['category_id'];
        } else {
            // Repair, Replacement, Lost, Damage requires selecting from assigned assets
            if (empty($validated['asset_id'])) {
                return $this->errorResponse('Please select one of your assigned assets for '.$requestType.' request.', 422);
            }

            // Verify that the asset is currently assigned to this employee
            $isAssigned = AssetAssignment::where('asset_id', $validated['asset_id'])
                ->where('assigned_to', $employee->id)
                ->where('status', 'Assigned')
                ->exists();

            if (! $isAssigned) {
                return $this->errorResponse('The selected asset is not currently assigned to you. Please select an asset from your assigned assets.', 422);
            }

            $assetId = $validated['asset_id'];
            $targetAsset = Asset::find($assetId);
            $categoryId = $targetAsset?->category_id ?? ($validated['category_id'] ?? null);
        }

        $requestNo = 'AR-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));

        $assetRequest = AssetRequest::create([
            'request_no' => $requestNo,
            'request_type' => $requestType,
            'asset_id' => $assetId,
            'category_id' => $categoryId,
            'requested_by' => $employee->id,
            'requested_date' => now()->toDateString(),
            'description' => $validated['description'],
            'priority' => $validated['priority'] ?? 'Normal',
            'status' => 'Pending',
        ]);

        return $this->successResponse([
            'request' => $assetRequest->load([
                'asset:id,asset_name,referenceno',
                'category:id,category',
            ]),
        ], 'Asset request submitted successfully.', 201);
    }

    /**
     * List asset requests (for employee, or all for HR/Admin with ?all=1).
     */
    public function requests(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        $isHrOrAdmin = SalesCrmRoles::userHasLoginAccess((int) $user->id);

        $query = AssetRequest::with([
            'asset:id,asset_name,referenceno,condition,status',
            'category:id,category,shortcode',
            'requester:id,user_id,employee_code,emp_num,designation',
            'requester.user:id,name,email',
            'approver:id,name',
        ]);

        if (! $isHrOrAdmin || (! $request->boolean('all') && ! $request->has('all'))) {
            if ($employee) {
                $query->where('requested_by', $employee->id);
            } else {
                $query->where('requested_by', 0);
            }
        }

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

        $requests = $query->orderBy('created_at', 'desc')->paginate(15);

        return $this->successPaginatedResponse($requests, 'requests');
    }

    /**
     * View single asset request details.
     */
    public function requestDetails(Request $request, AssetRequest $asset_request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $asset_request->load([
            'asset.category',
            'category',
            'requester.user',
            'approver:id,name',
        ]);

        return $this->successResponse([
            'request' => $asset_request,
        ]);
    }

    /**
     * HR approves an asset request.
     */
    public function approveRequest(Request $request, AssetRequest $asset_request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if (! SalesCrmRoles::userHasLoginAccess((int) $user->id)) {
            return $this->errorResponse('Unauthorized. Only HR or Admin can approve asset requests.', 403);
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string',
        ]);

        $asset_request->update([
            'status' => 'Approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        return $this->successResponse([
            'request' => $asset_request->fresh(['approver:id,name', 'requester.user', 'asset', 'category']),
        ], 'Asset request approved successfully.');
    }

    /**
     * HR rejects an asset request.
     */
    public function rejectRequest(Request $request, AssetRequest $asset_request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if (! SalesCrmRoles::userHasLoginAccess((int) $user->id)) {
            return $this->errorResponse('Unauthorized. Only HR or Admin can reject asset requests.', 403);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $asset_request->update([
            'status' => 'Rejected',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return $this->successResponse([
            'request' => $asset_request->fresh(['approver:id,name', 'requester.user', 'asset', 'category']),
        ], 'Asset request rejected.');
    }

    /**
     * HR updates asset request status (In Progress, Completed, Cancelled).
     */
    public function updateRequestStatus(Request $request, AssetRequest $asset_request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if (! SalesCrmRoles::userHasLoginAccess((int) $user->id)) {
            return $this->errorResponse('Unauthorized. Only HR or Admin can update request status.', 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:Pending,Under Review,Approved,Rejected,In Progress,Completed,Cancelled',
            'admin_notes' => 'nullable|string',
        ]);

        $data = ['status' => $validated['status']];
        if (isset($validated['admin_notes'])) {
            $data['admin_notes'] = $validated['admin_notes'];
        }

        $asset_request->update($data);

        return $this->successResponse([
            'request' => $asset_request->fresh(['approver:id,name', 'requester.user', 'asset', 'category']),
        ], 'Asset request status updated successfully.');
    }

    /**
     * Admin assigns an asset to an employee.
     */
    public function assign(Request $request, Asset $asset): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if (! SalesCrmRoles::userHasLoginAccess((int) $user->id)) {
            return $this->errorResponse('Unauthorized. Only HR or Admin can assign assets.', 403);
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:salescrm.employees,id',
            'assigned_date' => 'required|date',
            'condition_during_assign' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        // Mark any previous active assignments as Returned
        AssetAssignment::where('asset_id', $asset->id)
            ->where('status', 'Assigned')
            ->update([
                'status' => 'Returned',
                'returned_date' => now()->toDateString(),
            ]);

        $assignment = AssetAssignment::create([
            'asset_id' => $asset->id,
            'assigned_to' => $validated['employee_id'],
            'assigned_date' => $validated['assigned_date'],
            'note' => $validated['condition_during_assign'] ?? $validated['note'] ?? null,
            'status' => 'Assigned',
        ]);

        $asset->update(['status' => 'Active']);

        return $this->successResponse([
            'assignment' => $assignment->load('assignee.user'),
            'asset' => $asset->fresh(['category', 'currentAssignment.assignee.user']),
        ], 'Asset assigned successfully.', 201);
    }

    /**
     * Admin marks an assigned asset as returned.
     */
    public function returnAsset(Request $request, Asset $asset): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if (! SalesCrmRoles::userHasLoginAccess((int) $user->id)) {
            return $this->errorResponse('Unauthorized. Only HR or Admin can mark assets returned.', 403);
        }

        $validated = $request->validate([
            'returned_date' => 'nullable|date',
            'return_condition' => 'nullable|in:New,Excellent,Good,Fair,Damaged,Needs Repair',
            'note' => 'nullable|string',
        ]);

        $activeAssignment = AssetAssignment::where('asset_id', $asset->id)
            ->where('status', 'Assigned')
            ->first();

        if ($activeAssignment) {
            $activeAssignment->update([
                'status' => 'Returned',
                'returned_date' => $validated['returned_date'] ?? now()->toDateString(),
                'note' => $validated['note'] ?? $activeAssignment->note,
            ]);
        }

        $assetUpdates = ['status' => 'Returned'];
        if (! empty($validated['return_condition'])) {
            $assetUpdates['condition'] = $validated['return_condition'];
        }
        $asset->update($assetUpdates);

        return $this->successResponse([
            'asset' => $asset->fresh(['category']),
        ], 'Asset marked as returned successfully.');
    }
}
