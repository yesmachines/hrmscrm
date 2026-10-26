<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Shared methods are used by Admin and API controllers; change them carefully to preserve both workflows.
 */
class AssetService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function listInventory(
        array $filters,
        ?int $assignedToId = null,
        bool $includeAssigneeEmail = false,
    ): LengthAwarePaginator {
        $userColumns = $includeAssigneeEmail ? 'id,name,email' : 'id,name';
        $query = Asset::query()->with([
            'category:id,category,shortcode',
            "currentAssignment.assignee.user:{$userColumns}",
        ]);

        if ($assignedToId !== null) {
            $query->whereHas('assignments', function ($assignmentQuery) use ($assignedToId) {
                $assignmentQuery->where('assigned_to', $assignedToId)
                    ->where('status', 'Assigned');
            });
        }

        foreach (['category_id', 'status', 'condition'] as $filter) {
            if (isset($filters[$filter]) && $filters[$filter] !== '') {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (isset($filters['search']) && (string) $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('asset_name', 'like', "%{$search}%")
                    ->orWhere('referenceno', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('id')->paginate(15);
    }

    public function assignToEmployee(
        Asset $asset,
        int $employeeId,
        string $assignedDate,
        ?string $note,
        ?int $assetRequestId = null,
        ?string $fulfillmentNote = null,
    ): AssetAssignment {
        $asset->assignments()->where('status', 'Assigned')->update([
            'returned_date' => now()->toDateString(),
            'status' => 'Returned',
        ]);

        $assignment = $asset->assignments()->create([
            'assigned_to' => $employeeId,
            'assigned_date' => $assignedDate,
            'note' => $note,
            'status' => 'Assigned',
        ]);

        $asset->update(['status' => 'Active']);

        if ($assetRequestId !== null) {
            $updates = [
                'status' => 'Completed',
                'asset_id' => $asset->id,
            ];

            if ($fulfillmentNote !== null) {
                $assetRequest = AssetRequest::query()->findOrFail($assetRequestId);
                $updates['admin_notes'] = $assetRequest->admin_notes
                    ? $assetRequest->admin_notes."\n{$fulfillmentNote}"
                    : $fulfillmentNote;
                $assetRequest->update($updates);
            } else {
                AssetRequest::query()->whereKey($assetRequestId)->update($updates);
            }
        } else {
            $openRequest = AssetRequest::query()
                ->where('requested_by', $employeeId)
                ->whereIn('status', ['Approved', 'In Progress', 'Pending', 'Under Review'])
                ->where(function ($query) use ($asset) {
                    $query->where('asset_id', $asset->id)
                        ->orWhere(function ($subQuery) use ($asset) {
                            $subQuery->whereNull('asset_id')
                                ->where('category_id', $asset->category_id);
                        });
                })
                ->latest()
                ->first();

            if ($openRequest) {
                $updates = [
                    'status' => 'Completed',
                    'asset_id' => $asset->id,
                ];

                if ($fulfillmentNote !== null) {
                    $updates['admin_notes'] = $openRequest->admin_notes
                        ? $openRequest->admin_notes."\n{$fulfillmentNote}"
                        : $fulfillmentNote;
                }

                $openRequest->update($updates);
            }
        }

        return $assignment;
    }

    public function returnAsset(
        ?Asset $asset,
        string $returnedDate,
        ?string $returnCondition,
        ?string $note,
        ?AssetAssignment $assignment = null,
    ): void {
        $assignment ??= $asset?->assignments()
            ->where('status', 'Assigned')
            ->first();

        if ($assignment) {
            $assignment->update([
                'status' => 'Returned',
                'returned_date' => $returnedDate,
                'note' => $note ?? $assignment->note,
            ]);
        }

        if ($asset) {
            $updates = ['status' => 'Returned'];
            if ($returnCondition) {
                $updates['condition'] = $returnCondition;
            }

            $asset->update($updates);
        }
    }
}
