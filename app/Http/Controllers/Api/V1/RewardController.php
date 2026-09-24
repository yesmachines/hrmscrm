<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use App\Models\RewardCategory;
use App\Models\SalesCrm\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RewardController extends Controller
{
    /**
     * Get list of active reward categories.
     */
    public function categories(): JsonResponse
    {
        $categories = RewardCategory::where('status', 1)
            ->orderBy('reward_name')
            ->get(['id', 'reward_name', 'short_code', 'details']);

        return $this->successResponse($categories, 'Reward categories fetched successfully.');
    }

    /**
     * Get list of rewards for the authenticated employee.
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
     * Apply / Nominate for a reward.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        try {
            $validated = $request->validate([
                'category_id' => ['required', 'exists:reward_categories,id'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'description' => ['required', 'string'],
                'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        }

        $year = now()->year;
        $latestClaim = Reward::whereYear('created_at', $year)->orderByDesc('id')->first();
        $sequence = $latestClaim ? intval(substr($latestClaim->claim_no, -4)) + 1 : 1;
        $claimNo = sprintf('REW-%s-%04d', $year, $sequence);

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('rewards/documents', 'public');
        }

        $reward = Reward::create([
            'claim_no' => $claimNo,
            'category_id' => $validated['category_id'],
            'submitted_by' => $employee->id,
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'document_path' => $documentPath,
            'status' => 'pending',
            'submitted_date' => now()->toDateString(),
        ]);

        $reward->approvalStatuses()->create([
            'done_by' => $user->id,
            'done_on' => now(),
            'comments' => 'Claim submitted via Mobile App.',
            'status' => 'pending',
        ]);

        return $this->successResponse($reward->load('category'), 'Reward claim submitted successfully.', 201);
    }

    /**
     * Get specific reward details.
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
}
