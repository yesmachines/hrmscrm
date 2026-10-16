<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AssetDashboardController extends Controller
{
    /**
     * Return assets dashboard summary for HR.
     *
     * Response shape mirrors project ApiResponse trait: { statusCode, message, data }
     */
    public function index(Request $request): JsonResponse
    {
        $total = Asset::query()->count();

        // Assigned assets: assets that have an active assignment
        $assigned = Asset::whereHas('assignments', function ($q) {
            $q->where('status', 'Assigned');
        })->count();

        // Available assets: not assigned and not Retired/Lost/Under Maintenance
        $available = Asset::whereDoesntHave('assignments', function ($q) {
            $q->where('status', 'Assigned');
        })->whereNotIn('status', ['Retired', 'Lost', 'Under Maintenance'])->count();

        // Under Maintenance: status 'Under Maintenance' or condition 'Needs Repair'
        $underMaintenance = Asset::where(function ($q) {
            $q->where('status', 'Under Maintenance')
                ->orWhere('condition', 'Needs Repair');
        })->count();

        $assetsSummary = [
            'total' => (int) $total,
            'assigned' => (int) $assigned,
            'available' => (int) $available,
            'under_maintenance' => (int) $underMaintenance,
        ];

        // Pending HR requests: use Pending and Under Review statuses
        $pendingStatuses = ['Pending', 'Under Review'];

        $pendingCount = AssetRequest::whereIn('status', $pendingStatuses)->count();

        $pendingRequests = AssetRequest::with(['requester.user:id,name', 'asset:id,asset_name,referenceno', 'category:id,category'])
            ->whereIn('status', $pendingStatuses)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'request_no' => $r->request_no,
                    'request_type' => $r->request_type,
                    'asset' => $r->asset ? [
                        'id' => $r->asset->id,
                        'asset_name' => $r->asset->asset_name,
                        'referenceno' => $r->asset->referenceno,
                    ] : null,
                    'category' => $r->category ? ['id' => $r->category->id, 'category' => $r->category->category] : null,
                    'requester_name' => $r->requester?->user?->name ?? null,
                    'requester_designation' => $r->requester?->designation ?? null,
                    'requested_date' => $r->requested_date?->toDateString(),
                    'requested_date_label' => $r->requested_date ? (
                        (function ($d) {
                            $now = \Illuminate\Support\Carbon::now()->startOfDay();
                            $target = $d->startOfDay();

                            // Future date
                            if ($target->greaterThan($now)) {
                                $days = $now->diffInDays($target);
                                if ($days === 0) {
                                    return 'Today';
                                }
                                if ($days === 1) {
                                    return 'Tomorrow';
                                }

                                return 'in '.$days.' days';
                            }

                            // Past date
                            $diff = $target->diffInDays($now);
                            if ($diff === 0) {
                                return 'Today';
                            }
                            if ($diff === 1) {
                                return 'Yesterday';
                            }
                            if ($diff <= 6) {
                                return $diff.' days ago';
                            }

                            return $d->format('M j');
                        })($r->requested_date)
                    ) : null,
                    'status' => $r->status,
                    'priority' => $r->priority,
                ];
            });

        return $this->successResponse([
            'assets_summary' => $assetsSummary,
            'requests' => [
                'pending_count' => $pendingCount,
                'pending' => $pendingRequests,
            ],
        ], 'HR assets dashboard data retrieved successfully.');
    }
}
