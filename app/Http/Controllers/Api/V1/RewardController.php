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
     * Get list of rewards for the authenticated employee (Self-Service).
     */
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

        $rewards = Reward::with([
            'category:id,reward_name,short_code',
            'employee:id,user_id,emp_num,designation',
            'employee.user:id,name',
            'approvalStatuses' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
            'approvalStatuses.doneByUser:id,name',
        ])
            ->where('submitted_by', $employee->id)
            ->orderByDesc('id')
            ->paginate(15);

        return $this->successResponse($rewards, 'Rewards fetched successfully.');
    }

    /**
     * Get list of all rewards across employees (HR / Finance Access).
     */
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
