<?php

namespace App\Services;

use App\Models\AssetAssignment;
use App\Models\SalesCrm\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Shared methods are used by Admin and API controllers; change them carefully to preserve both workflows.
 */
class EmployeeService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Employee>|LengthAwarePaginator
     */
    public function list(array $filters, int $perPage, bool $all = false, ?int $excludeUserId = null): Collection|LengthAwarePaginator
    {
        $query = Employee::query()
            ->with(['user:id,name,email', 'department:id,name']);

        if (isset($filters['search']) && (string) $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('emp_num', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('designation', 'like', "%{$search}%")
                    ->orWhere('division', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (isset($filters['department_id']) && $filters['department_id'] !== '') {
            $query->where('department_id', $filters['department_id']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        if (! empty($filters['exclude_me']) && $excludeUserId !== null) {
            $query->where('user_id', '!=', $excludeUserId);
        }

        if (! empty($filters['type'])) {
            $assignedIds = AssetAssignment::query()
                ->where('status', 'Assigned')
                ->distinct()
                ->pluck('assigned_to')
                ->filter()
                ->all();

            if (strtolower((string) $filters['type']) === 'assigned') {
                $query->whereIn('id', $assignedIds ?: [0]);
            } elseif (strtolower((string) $filters['type']) === 'unassigned' && $assignedIds !== []) {
                $query->whereNotIn('id', $assignedIds);
            }
        }

        $allowedSortColumns = ['id', 'emp_num', 'employee_code', 'designation', 'joining_date', 'status'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSortColumns, true)
            ? $filters['sort_by']
            : 'id';
        $sortDirection = ($filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDirection);

        if ($all) {
            return $query->get();
        }

        return $query->paginate(max(1, min(100, $perPage)));
    }
}
