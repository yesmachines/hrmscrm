<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SalesCrm\Employee;
use App\Models\Visit;
use App\Support\SalesCrmRoles;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    /**
     * List visit history / requests.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        $isHrOrAdmin = SalesCrmRoles::userHasLoginAccess((int) $user->id);

        $query = Visit::query()->with([
            'creator:id,user_id,employee_code,emp_num,designation',
            'creator.user:id,name,email',
            'latestApproval.approver:id,name',
        ]);

        // Non-HR users can only see visits they created
        if (! $isHrOrAdmin || (! $request->boolean('all') && ! $request->has('all'))) {
            if ($employee) {
                $query->where('created_by', $employee->id);
            } else {
                $query->where('created_by', $user->id);
            }
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by date (e.g. today / current_day, tomorrow, day_after_tomorrow, or specific date YYYY-MM-DD)
        $dateFilter = $request->query('date_filter')
            ?? $request->query('day')
            ?? $request->query('date')
            ?? $request->query('filter')
            ?? $request->input('date_filter')
            ?? $request->input('day')
            ?? $request->input('date')
            ?? $request->input('filter');

        if (! empty($dateFilter)) {
            $normalized = strtolower(trim(str_replace(['_', '-'], ' ', (string) $dateFilter)));
            $normalized = preg_replace('/\s+/', ' ', $normalized);

            $targetDate = null;
            if (in_array($normalized, ['today', 'current day', 'current'])) {
                $targetDate = now()->toDateString();
            } elseif (in_array($normalized, ['tomorrow', 'tomorow'])) {
                $targetDate = now()->addDay()->toDateString();
            } elseif (in_array($normalized, ['day after tomorrow', 'day after tommarow'])) {
                $targetDate = now()->addDays(2)->toDateString();
            } elseif ($normalized === 'yesterday') {
                $targetDate = now()->subDay()->toDateString();
            } else {
                try {
                    $targetDate = Carbon::parse($dateFilter)->toDateString();
                } catch (\Throwable) {
                    $targetDate = null;
                }
            }

            if ($targetDate) {
                $query->where(function ($q) use ($targetDate) {
                    $q->whereDate('expected_start_date', $targetDate)
                        ->orWhere(function ($sub) use ($targetDate) {
                            $sub->whereNotNull('expected_end_date')
                                ->whereDate('expected_start_date', '<=', $targetDate)
                                ->whereDate('expected_end_date', '>=', $targetDate);
                        });
                });
            }
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->input('start_date'))->toDateString();
            $endDate = Carbon::parse($request->input('end_date'))->toDateString();

            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('expected_start_date', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                    ->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->whereNotNull('expected_end_date')
                            ->whereDate('expected_start_date', '<=', $endDate)
                            ->whereDate('expected_end_date', '>=', $startDate);
                    });
            });
        }

        // Search by visitor name, company, location, purpose
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('company', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('visitor_details', 'like', "%{$search}%");
            });
        }

        $visits = $query->orderBy('expected_start_date', 'desc')->paginate(15);

        return $this->successPaginatedResponse($visits, 'visits');
    }

    /**
     * Submit a new visit request.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        $creatorId = $employee ? $employee->id : $user->id;

        $validated = $request->validate([
            'total_visitors' => 'required|integer|min:1',
            'visitor_details' => 'required|array|min:1',
            'visitor_details.*.name' => 'required|string|max:255',
            'visitor_details.*.designation' => 'nullable|string|max:255',
            'company' => 'required|string|max:255',
            'contact_no' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'purpose' => 'required|string',
            'location' => 'required|string|max:255',
            'expected_start_date' => 'required|date',
            'expected_end_date' => 'nullable|date|after_or_equal:expected_start_date',
            'required_approvals' => 'nullable|string',
        ]);

        $visit = Visit::create([
            'total_visitors' => $validated['total_visitors'],
            'visitor_details' => $validated['visitor_details'],
            'company' => $validated['company'],
            'contact_no' => $validated['contact_no'],
            'email' => $validated['email'],
            'purpose' => $validated['purpose'],
            'location' => $validated['location'],
            'expected_start_date' => $validated['expected_start_date'],
            'expected_end_date' => $validated['expected_end_date'] ?? null,
            'created_by' => $creatorId,
            'required_approvals' => $validated['required_approvals'] ?? null,
            'status' => 'pending',
        ]);

        return $this->successResponse([
            'visit' => $visit->load([
                'creator:id,user_id,employee_code,emp_num,designation',
                'creator.user:id,name',
            ]),
        ], 'Visit request submitted successfully.', 201);
    }

    /**
     * Get visit details.
     */
    public function show(Visit $visit): JsonResponse
    {
        $visit->load([
            'creator:id,user_id,employee_code,emp_num,designation',
            'creator.user:id,name,email',
            'approvals.approver:id,name',
        ]);

        return $this->successResponse([
            'visit' => $visit,
        ]);
    }

    /**
     * Approve visit request by HR.
     */
    public function approve(Request $request, Visit $visit): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $validated = $request->validate([
            'instructions' => 'nullable|string',
        ]);

        $visit->update([
            'status' => 'approved',
        ]);

        $approval = $visit->approvals()->create([
            'approver_id' => $user->id,
            'instructions' => $validated['instructions'] ?? null,
        ]);

        return $this->successResponse([
            'visit' => $visit->fresh([
                'creator.user:id,name',
                'latestApproval.approver:id,name',
            ]),
            'approval' => $approval,
        ], 'Visit request approved successfully.');
    }

    /**
     * Reject visit request by HR.
     */
    public function reject(Request $request, Visit $visit): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $validated = $request->validate([
            'rejected_reason' => 'required|string',
        ]);

        $visit->update([
            'status' => 'rejected',
        ]);

        $approval = $visit->approvals()->create([
            'approver_id' => $user->id,
            'rejected_reason' => $validated['rejected_reason'],
        ]);

        return $this->successResponse([
            'visit' => $visit->fresh([
                'creator.user:id,name',
                'latestApproval.approver:id,name',
            ]),
            'approval' => $approval,
        ], 'Visit request rejected.');
    }

    /**
     * Update visit status to completed or cancelled.
     */
    public function updateStatus(Request $request, Visit $visit): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:completed,cancelled',
        ]);

        $visit->update([
            'status' => $validated['status'],
        ]);

        return $this->successResponse([
            'visit' => $visit->fresh(),
        ], 'Visit status updated to '.$validated['status'].'.');
    }
}
