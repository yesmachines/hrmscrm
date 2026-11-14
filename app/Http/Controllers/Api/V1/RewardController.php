<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use App\Models\RewardCategory;
use App\Models\SalesCrm\Employee;
use App\Services\RewardBulkImportService;
use App\Support\SalesCrmRoles;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class RewardController extends Controller
{
    /**
     * Get list of active reward categories.
     */
    #[OA\Get(
        path: '/api/v1/rewards/categories',
        summary: 'Get reward categories',
        security: [['sanctum' => []]],
        tags: ['Rewards'],
        responses: [
            new OA\Response(response: 200, description: 'Reward categories retrieved successfully'),
        ]
    )]
    public function categories(): JsonResponse
    {
        $categories = RewardCategory::where('status', 1)
            ->orderBy('reward_name')
            ->get(['id', 'reward_name', 'short_code', 'details']);

        return $this->successResponse($categories, 'Reward categories fetched successfully.');
    }

    /**
     * Get employee personal rewards summary for the mobile dashboard.
     * Shows this month's reward amount, this year's total reward amount, and total count.
     */
    #[OA\Get(
        path: '/api/v1/rewards/summary',
        summary: 'Get employee personal rewards summary metrics',
        security: [['sanctum' => []]],
        tags: ['Rewards'],
        parameters: [
            new OA\Parameter(name: 'year', in: 'query', description: 'Filter by year (e.g. 2026)', required: false, schema: new OA\Schema(type: 'integer', example: 2026)),
            new OA\Parameter(name: 'month', in: 'query', description: 'Filter by month number 1-12', required: false, schema: new OA\Schema(type: 'integer', example: 11)),
            new OA\Parameter(name: 'category_id', in: 'query', description: 'Filter by category ID', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'status', in: 'query', description: 'Filter by status (approved, paid, pending)', required: false, schema: new OA\Schema(type: 'string', example: 'approved')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rewards summary retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Employee record not found'),
        ]
    )]
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        // Optional Year filter (e.g. ?year=2025), default to current year
        $targetYear = $request->filled('year') ? (int) $request->input('year') : (int) now()->year;
        $yearStart = Carbon::create($targetYear, 1, 1)->startOfYear();
        $yearEnd = (clone $yearStart)->endOfYear();

        // Optional Month filter (e.g. ?month=11 or ?date=2026-11), default to current month
        if ($request->filled('month')) {
            $monthNum = (int) $request->input('month');
            $monthStart = Carbon::create($targetYear, $monthNum, 1)->startOfMonth();
            $monthEnd = (clone $monthStart)->endOfMonth();
        } else {
            $monthStart = now()->startOfMonth();
            $monthEnd = now()->endOfMonth();
        }

        // Base query for rewards awarded/approved/paid to this employee
        $baseQuery = Reward::query()
            ->where('submitted_by', $employee->id);

        // Optional Category filter
        if ($request->filled('category_id') && $request->input('category_id') !== 'all') {
            $baseQuery->where('category_id', (int) $request->input('category_id'));
        }

        // Optional Status filter (default to approved/paid for earned rewards stats, or allow filtering)
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $baseQuery->where('status', $request->input('status'));
        } else {
            $baseQuery->whereIn('status', ['approved', 'paid']);
        }

        // Month metrics (filtered by target month)
        $monthQuery = (clone $baseQuery)->whereBetween('submitted_date', [$monthStart, $monthEnd]);
        $monthAmount = (float) $monthQuery->sum('amount');
        $monthCount = $monthQuery->count();

        // Year metrics (filtered by target year)
        $yearQuery = (clone $baseQuery)->whereBetween('submitted_date', [$yearStart, $yearEnd]);
        $yearAmount = (float) $yearQuery->sum('amount');
        $yearCount = $yearQuery->count();

        // Lifetime total metrics
        $totalAmount = (float) (clone $baseQuery)->sum('amount');
        $totalCount = (clone $baseQuery)->count();

        // Pending nominations count for awareness
        $pendingCount = Reward::query()
            ->where('submitted_by', $employee->id)
            ->where('status', 'pending')
            ->count();

        // Monthly breakdown for charts/graphs (for the selected year)
        $monthlyBreakdown = [];
        for ($m = 1; $m <= 12; $m++) {
            $mStart = Carbon::create($targetYear, $m, 1)->startOfMonth();
            $mEnd = (clone $mStart)->endOfMonth();

            $mRewards = (clone $baseQuery)->whereBetween('submitted_date', [$mStart, $mEnd]);
            $monthlyBreakdown[] = [
                'month_number' => $m,
                'month_name' => $mStart->format('M'),
                'amount' => round((float) $mRewards->sum('amount'), 2),
                'count' => $mRewards->count(),
            ];
        }

        // Recent rewards for dashboard list preview
        $recentRewards = (clone $baseQuery)
            ->with(['category:id,reward_name,short_code'])
            ->orderByDesc('submitted_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'claim_no', 'category_id', 'amount', 'currency', 'status', 'submitted_date', 'description']);

        $currency = Reward::query()->where('submitted_by', $employee->id)->value('currency') ?: 'AED';

        return $this->successResponse([
            'currency' => $currency,
            'filters_applied' => [
                'year' => $targetYear,
                'month' => (int) $monthStart->format('n'),
                'category_id' => $request->input('category_id'),
                'status' => $request->input('status'),
            ],
            'selected_month' => [
                'month_number' => (int) $monthStart->format('n'),
                'month_name' => $monthStart->format('F Y'),
                'amount' => round($monthAmount, 2),
                'count' => $monthCount,
            ],
            'selected_year' => [
                'year' => $targetYear,
                'amount' => round($yearAmount, 2),
                'count' => $yearCount,
            ],
            'total_rewards' => [
                'amount' => round($totalAmount, 2),
                'count' => $totalCount,
            ],
            'monthly_breakdown' => $monthlyBreakdown,
            'pending_rewards_count' => $pendingCount,
            'recent_rewards' => $recentRewards,
        ], 'Employee reward summary fetched successfully.');
    }

    /**
     * Get list of rewards for the authenticated employee (Self-Service).
     */
    #[OA\Get(
        path: '/api/v1/rewards',
        summary: 'List personal rewards for the authenticated employee',
        security: [['sanctum' => []]],
        tags: ['Rewards'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', description: 'Filter by status (pending, approved, paid, rejected)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category_id', in: 'query', description: 'Filter by category ID', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'year', in: 'query', description: 'Filter by submission year (e.g. 2026)', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'month', in: 'query', description: 'Filter by submission month number (1-12)', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'date_filter', in: 'query', description: 'Quick keyword: today, this_month, last_month', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Search in claim_no, category, or description', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Pagination page size (default 15)', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', description: 'Page number', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rewards list retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Employee record not found'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $query = Reward::with([
            'category:id,reward_name,short_code',
            'employee:id,user_id,emp_num,designation',
            'employee.user:id,name',
            'approvalStatuses' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
            'approvalStatuses.doneByUser:id,name',
        ])
            ->where('submitted_by', $employee->id);

        // Filter by Status (e.g. ?status=approved, paid, pending)
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // Filter by Category (e.g. ?category_id=2)
        if ($request->filled('category_id') && $request->input('category_id') !== 'all') {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        // Filter by Year (e.g. ?year=2026)
        if ($request->filled('year')) {
            $year = (int) $request->input('year');
            $query->whereYear('submitted_date', $year);
        }

        // Filter by Month (e.g. ?month=11)
        if ($request->filled('month')) {
            $month = (int) $request->input('month');
            $query->whereMonth('submitted_date', $month);
        }

        // Filter by Date keyword (today, this_month, last_month)
        if ($request->filled('date_filter') && $request->input('date_filter') !== 'all') {
            $dateFilter = strtolower(trim((string) $request->input('date_filter')));
            if ($dateFilter === 'today') {
                $query->whereDate('submitted_date', now()->toDateString());
            } elseif ($dateFilter === 'this_month') {
                $query->whereBetween('submitted_date', [now()->startOfMonth(), now()->endOfMonth()]);
            } elseif ($dateFilter === 'last_month') {
                $query->whereBetween('submitted_date', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]);
            }
        }

        // Search in claim number or description
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $q) use ($search): void {
                $q->where('claim_no', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('category', function (Builder $catQ) use ($search): void {
                        $catQ->where('reward_name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $rewards = $query->orderByDesc('id')->paginate($perPage);

        return $this->successResponse($rewards, 'Rewards fetched successfully.');
    }

    /**
     * Get list of all rewards across employees (HR / Finance Access).
     */
    #[OA\Get(
        path: '/api/v1/hr/rewards',
        summary: 'List all rewards across employees (HR & Finance)',
        security: [['sanctum' => []]],
        tags: ['Rewards'],
        parameters: [
            new OA\Parameter(name: 'employee_id', in: 'query', description: 'Filter by specific employee ID', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', description: 'Filter by status (pending, approved, paid, rejected)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category_id', in: 'query', description: 'Filter by category ID', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'date_filter', in: 'query', description: 'Quick keyword: today, this_month, last_month', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Search across claim_no, employee name, category or description', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Pagination page size (default 15)', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', description: 'Page number', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'HR rewards list retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (HR/Finance/Admin required)'),
        ]
    )]
    public function hrIndex(Request $request): JsonResponse
    {
        $query = Reward::with([
            'category:id,reward_name,short_code',
            'employee:id,user_id,emp_num,designation',
            'employee.user:id,name,email',
            'approvalStatuses' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
            'approvalStatuses.doneByUser:id,name',
        ]);

        if ($request->filled('employee_id')) {
            $query->where('submitted_by', (int) $request->input('employee_id'));
        }

        if ($request->filled('category_id') && $request->input('category_id') !== 'all') {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $q) use ($search): void {
                $q->where('claim_no', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('employee.user', function (Builder $userQ) use ($search): void {
                        $userQ->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('category', function (Builder $catQ) use ($search): void {
                        $catQ->where('reward_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('date_filter') && $request->input('date_filter') !== 'all') {
            $dateFilter = strtolower(trim((string) $request->input('date_filter')));
            if ($dateFilter === 'today') {
                $query->whereDate('submitted_date', now()->toDateString());
            } elseif ($dateFilter === 'this_month') {
                $query->whereBetween('submitted_date', [now()->startOfMonth(), now()->endOfMonth()]);
            } elseif ($dateFilter === 'last_month') {
                $query->whereBetween('submitted_date', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]);
            } else {
                try {
                    $exact = Carbon::parse($dateFilter)->toDateString();
                    $query->whereDate('submitted_date', $exact);
                } catch (\Throwable) {
                }
            }
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $rewards = $query->orderByDesc('id')->paginate($perPage);

        return $this->successResponse($rewards, 'HR rewards list retrieved successfully.');
    }

    /**
     * Apply / Nominate for a reward (Self-Service or HR delegation).
     */
    #[OA\Post(
        path: '/api/v1/rewards',
        summary: 'Submit / nominate a reward claim',
        security: [['sanctum' => []]],
        tags: ['Rewards'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['category_id', 'amount', 'description'],
                    properties: [
                        new OA\Property(property: 'category_id', type: 'integer', example: 1),
                        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 500.00),
                        new OA\Property(property: 'currency', type: 'string', example: 'AED'),
                        new OA\Property(property: 'description', type: 'string', example: 'Completed critical project deployment'),
                        new OA\Property(property: 'employee_id', type: 'integer', description: 'Optional for HR users to submit on behalf of an employee', example: 4),
                        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'paid'], example: 'pending'),
                        new OA\Property(property: 'submitted_date', type: 'string', format: 'date', example: '2026-11-14'),
                        new OA\Property(property: 'comments', type: 'string', example: 'Approved nomination by supervisor'),
                        new OA\Property(property: 'document', type: 'string', format: 'binary', description: 'Supporting document (PDF, PNG, JPG up to 10MB)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Reward claim submitted successfully'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        try {
            $validated = $request->validate([
                'employee_id' => ['nullable', 'exists:salescrm.employees,id'],
                'submitted_by' => ['nullable', 'exists:salescrm.employees,id'],
                'category_id' => ['required', 'exists:reward_categories,id'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'currency' => ['nullable', 'string', 'max:10'],
                'description' => ['required', 'string'],
                'status' => ['nullable', Rule::in(['pending', 'approved', 'paid'])],
                'submitted_date' => ['nullable', 'date'],
                'comments' => ['nullable', 'string', 'max:1000'],
                'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        }

        $isHrOrAdmin = SalesCrmRoles::userHasAnyRole((int) $user->id, ['admin', 'hr', 'finance']);
        $targetEmployeeId = ($isHrOrAdmin && (! empty($validated['employee_id']) || ! empty($validated['submitted_by'])))
            ? ($validated['employee_id'] ?? $validated['submitted_by'])
            : Employee::where('user_id', $user->id)->value('id');

        if (! $targetEmployeeId) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $status = ($isHrOrAdmin && ! empty($validated['status']))
            ? $validated['status']
            : 'pending';

        $submittedDate = ($isHrOrAdmin && ! empty($validated['submitted_date']))
            ? Carbon::parse($validated['submitted_date'])
            : now();

        $year = $submittedDate->format('Y');
        $prefix = "REW-{$year}-";
        $lastClaim = Reward::withTrashed()->where('claim_no', 'like', "{$prefix}%")->orderByDesc('id')->value('claim_no');
        $nextSeq = 1;
        if ($lastClaim && preg_match('/REW-\d{4}-(\d+)/', $lastClaim, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }
        $claimNo = $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('rewards', 'public');
        }

        $reward = Reward::create([
            'claim_no' => $claimNo,
            'category_id' => $validated['category_id'],
            'submitted_by' => $targetEmployeeId,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'AED',
            'description' => $validated['description'],
            'document_file' => $documentPath,
            'status' => $status,
            'submitted_date' => $submittedDate,
        ]);

        $defaultComment = match ($status) {
            'paid' => 'Existing reward recorded as paid.',
            'approved' => 'Existing reward recorded as approved.',
            default => 'Claim submitted via Mobile App.',
        };

        $reward->approvalStatuses()->create([
            'done_by' => $user->id,
            'done_on' => $submittedDate,
            'comments' => ! empty($validated['comments']) ? $validated['comments'] : $defaultComment,
            'status' => $status,
        ]);

        return $this->successResponse($reward->load('category'), 'Reward claim submitted successfully.', 201);
    }

    /**
     * Add existing or new reward for an employee (HR / Finance Access).
     */
    #[OA\Post(
        path: '/api/v1/hr/rewards',
        summary: 'Add / record a reward for any employee (HR & Finance)',
        security: [['sanctum' => []]],
        tags: ['Rewards'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['category_id', 'amount', 'description'],
                    properties: [
                        new OA\Property(property: 'employee_id', type: 'integer', description: 'Employee receiving the reward', example: 4),
                        new OA\Property(property: 'category_id', type: 'integer', example: 1),
                        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 1000.00),
                        new OA\Property(property: 'currency', type: 'string', example: 'AED'),
                        new OA\Property(property: 'description', type: 'string', example: 'Annual outstanding achievement award'),
                        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'paid'], example: 'approved'),
                        new OA\Property(property: 'award_date', type: 'string', format: 'date', example: '2026-11-01'),
                        new OA\Property(property: 'comments', type: 'string', example: 'Awarded by Executive Director'),
                        new OA\Property(property: 'document', type: 'string', format: 'binary', description: 'Supporting certificate or voucher file'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Reward recorded successfully'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 403, description: 'Forbidden (HR/Finance required)'),
        ]
    )]
    public function hrStore(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        try {
            $validated = $request->validate([
                'employee_id' => ['nullable', 'exists:salescrm.employees,id'],
                'submitted_by' => ['nullable', 'exists:salescrm.employees,id'],
                'category_id' => ['required', 'exists:reward_categories,id'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'currency' => ['nullable', 'string', 'max:10'],
                'description' => ['required', 'string'],
                'status' => ['nullable', Rule::in(['pending', 'approved', 'paid'])],
                'submitted_date' => ['nullable', 'date'],
                'award_date' => ['nullable', 'date'],
                'comments' => ['nullable', 'string', 'max:1000'],
                'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        }

        $employeeId = $validated['employee_id'] ?? $validated['submitted_by'] ?? null;
        if (! $employeeId) {
            $employeeId = Employee::where('user_id', $user->id)->value('id');
        }

        if (! $employeeId) {
            return $this->errorResponse('Employee ID is required.', 422, [
                'employee_id' => ['Please specify an employee to award.'],
            ]);
        }

        $status = $validated['status'] ?? 'approved';
        $submittedDate = ! empty($validated['submitted_date'])
            ? Carbon::parse($validated['submitted_date'])
            : (! empty($validated['award_date']) ? Carbon::parse($validated['award_date']) : now());

        $year = $submittedDate->format('Y');
        $prefix = "REW-{$year}-";
        $lastClaim = Reward::withTrashed()->where('claim_no', 'like', "{$prefix}%")->orderByDesc('id')->value('claim_no');
        $nextSeq = 1;
        if ($lastClaim && preg_match('/REW-\d{4}-(\d+)/', $lastClaim, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }
        $claimNo = $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('rewards', 'public');
        }

        $reward = Reward::create([
            'claim_no' => $claimNo,
            'category_id' => $validated['category_id'],
            'submitted_by' => $employeeId,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'AED',
            'description' => $validated['description'],
            'document_file' => $documentPath,
            'status' => $status,
            'submitted_date' => $submittedDate,
        ]);

        $defaultComment = match ($status) {
            'paid' => 'Existing reward recorded as paid via HR access.',
            'approved' => 'Existing reward recorded as approved via HR access.',
            default => 'Reward claim submitted via HR access.',
        };

        $reward->approvalStatuses()->create([
            'done_by' => $user->id,
            'done_on' => $submittedDate,
            'comments' => ! empty($validated['comments']) ? $validated['comments'] : $defaultComment,
            'status' => $status,
        ]);

        return $this->successResponse(
            $reward->load(['category', 'employee.user', 'approvalStatuses.doneByUser']),
            'Reward added successfully for employee.',
            201
        );
    }

    /**
     * Get specific reward details (Self-Service).
     */
    #[OA\Get(
        path: '/api/v1/rewards/{id}',
        summary: 'Get details of an employee reward claim',
        security: [['sanctum' => []]],
        tags: ['Rewards'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Reward ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reward details fetched successfully'),
            new OA\Response(response: 404, description: 'Reward claim not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $reward = Reward::with([
            'category:id,reward_name,short_code',
            'employee:id,user_id,emp_num,designation',
            'employee.user:id,name',
            'approvalStatuses' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
            'approvalStatuses.doneByUser:id,name',
        ])
            ->where('id', $id)
            ->where('submitted_by', $employee->id)
            ->first();

        if (! $reward) {
            return $this->errorResponse('Reward claim not found.', 404);
        }

        return $this->successResponse($reward, 'Reward details fetched successfully.');
    }

    /**
     * Get specific reward details (HR / Finance Access).
     */
    #[OA\Get(
        path: '/api/v1/hr/rewards/{id}',
        summary: 'Get details of any employee reward (HR & Finance)',
        security: [['sanctum' => []]],
        tags: ['Rewards'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Reward ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reward details fetched successfully'),
            new OA\Response(response: 404, description: 'Reward record not found'),
            new OA\Response(response: 403, description: 'Forbidden (HR/Finance required)'),
        ]
    )]
    public function hrShow(Request $request, $id): JsonResponse
    {
        $reward = Reward::with([
            'category:id,reward_name,short_code',
            'employee:id,user_id,emp_num,designation',
            'employee.user:id,name,email',
            'approvalStatuses' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
            'approvalStatuses.doneByUser:id,name',
        ])
            ->where('id', $id)
            ->first();

        if (! $reward) {
            return $this->errorResponse('Reward record not found.', 404);
        }

        return $this->successResponse($reward, 'Reward details fetched successfully.');
    }

    /**
     * Update claim approval status via HR API (Approve, Mark as Paid, Reject).
     */
    #[OA\Post(
        path: '/api/v1/hr/rewards/{id}/status',
        summary: 'Update reward claim status (Approve, Pay, Reject)',
        security: [['sanctum' => []]],
        tags: ['Rewards'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Reward ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['approved', 'paid', 'rejected'], example: 'approved'),
                    new OA\Property(property: 'comments', type: 'string', description: 'Mandatory when rejecting', example: 'Approved by HR manager'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Status updated successfully'),
            new OA\Response(response: 422, description: 'Validation failed or missing rejection comments'),
            new OA\Response(response: 403, description: 'Only Finance or Admin can mark as paid'),
            new OA\Response(response: 404, description: 'Reward claim not found'),
        ]
    )]
    public function updateHrStatus(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $reward = Reward::find($id);
        if (! $reward) {
            return $this->errorResponse('Reward claim not found.', 404);
        }

        try {
            $validated = $request->validate([
                'status' => ['required', Rule::in(['approved', 'paid', 'rejected'])],
                'comments' => ['nullable', 'string', 'max:1000'],
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        }

        $newStatus = $validated['status'];
        $comments = $validated['comments'] ?? null;

        if ($newStatus === 'paid' && ! SalesCrmRoles::userHasAnyRole((int) $user->id, ['finance', 'admin'])) {
            return $this->errorResponse('Only Finance role users can mark claims as paid.', 403);
        }

        if ($newStatus === 'rejected' && empty($comments)) {
            return $this->errorResponse('Please provide a reason for rejecting this claim.', 422, [
                'comments' => ['Please provide a reason for rejecting this claim.'],
            ]);
        }

        $reward->update([
            'status' => $newStatus,
        ]);

        $reward->approvalStatuses()->create([
            'done_by' => $user->id,
            'done_on' => now(),
            'comments' => $comments ?: ucfirst($newStatus).' by HR/Finance via Mobile App.',
            'status' => $newStatus,
        ]);

        return $this->successResponse(
            $reward->load(['category', 'employee.user', 'approvalStatuses.doneByUser']),
            "Reward claim updated to {$newStatus} successfully."
        );
    }

    /**
     * Download sample CSV or Excel template for bulk importing rewards.
     */
    #[OA\Get(
        path: '/api/v1/hr/rewards/template',
        summary: 'Download sample Excel or CSV template for rewards bulk import',
        security: [['sanctum' => []]],
        tags: ['Rewards'],
        parameters: [
            new OA\Parameter(name: 'format', in: 'query', description: 'Template format: excel (default) or csv', required: false, schema: new OA\Schema(type: 'string', enum: ['excel', 'csv'], example: 'excel')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'File download stream (.xls or .csv)'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (HR/Finance required)'),
        ]
    )]
    public function downloadTemplate(Request $request, RewardBulkImportService $importService): Response
    {
        $format = strtolower((string) $request->query('format', 'excel'));

        if ($format === 'csv') {
            $csv = $importService->generateTemplateCsv();

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="rewards_bulk_import_template.csv"',
            ]);
        }

        $excelXml = $importService->generateTemplateExcel();

        return response($excelXml, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rewards_bulk_import_template.xls"',
        ]);
    }

    /**
     * Bulk import employee rewards from CSV file.
     */
    #[OA\Post(
        path: '/api/v1/hr/rewards/bulk-import',
        summary: 'Bulk import employee rewards via CSV upload (HR & Finance)',
        security: [['sanctum' => []]],
        tags: ['Rewards'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'CSV file containing employee rewards (max 5MB)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Bulk import processed with counts of success and failure'),
            new OA\Response(response: 422, description: 'CSV format or validation errors'),
            new OA\Response(response: 403, description: 'Forbidden (HR/Finance required)'),
        ]
    )]
    public function bulkImport(Request $request, RewardBulkImportService $importService): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        try {
            $request->validate([
                'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'], // 5MB limit
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        }

        try {
            $result = $importService->importCsv($request->file('file'), $user);

            return $this->successResponse($result, "Bulk import completed. Imported: {$result['imported_count']}, Failed: {$result['failed_count']}.");
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Bulk import failed: '.$e->getMessage(), 500);
        }
    }
}
