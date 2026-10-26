<?php

namespace App\Services;

use App\Models\SalesCrm\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Shared methods are used by Admin and API controllers; change them carefully to preserve both workflows.
 */
class DepartmentService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Department>|LengthAwarePaginator
     */
    public function list(array $filters, int $perPage = 15, bool $all = false, bool $withEmployeeCount = false): Collection|LengthAwarePaginator
    {
        $query = Department::query();

        if ($withEmployeeCount) {
            $query->withCount('employees');
        }

        if (isset($filters['search']) && (string) $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        if (isset($filters['organisation_id']) && $filters['organisation_id'] !== '') {
            $query->where('organisation_id', (int) $filters['organisation_id']);
        }

        $allowedSortColumns = ['name', 'code', 'id', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSortColumns, true)
            ? $filters['sort_by']
            : 'name';
        $sortDirection = strtolower((string) ($filters['sort_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sortBy, $sortDirection);

        if ($all) {
            return $query->get();
        }

        return $query->paginate(max(1, min(100, $perPage)));
    }
}
