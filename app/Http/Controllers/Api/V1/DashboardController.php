<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\Reward;
use App\Models\SalesCrm\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    /**
     * Get consolidated employee mobile dashboard overview including rewards, leaves, and activity.
     */
    #[OA\Get(
        path: '/api/v1/dashboard',
        summary: 'Get consolidated employee mobile dashboard overview with rewards',
        security: [['sanctum' => []]],
        tags: ['Dashboard'],
        parameters: [
            new OA\Parameter(
                name: 'year',
                in: 'query',
                description: 'Filter stats and monthly breakdown by year (defaults to current year)',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 2026)
            ),
            new OA\Parameter(
                name: 'month',
                in: 'query',
                description: 'Filter stats by month number 1-12 (defaults to current month)',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 11)
            ),
            new OA\Parameter(
                name: 'category_id',
                in: 'query',
                description: 'Filter rewards by category ID',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 2)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dashboard data retrieved successfully'),
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

        $employee = Employee::where('user_id', $user->id)
            ->with(['department:id,name', 'officeLocation:id,office_name,city'])
            ->first();

        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $targetYear = $request->filled('year') ? (int) $request->input('year') : (int) now()->year;
        $targetMonth = $request->filled('month') ? (int) $request->input('month') : (int) now()->month;

        $monthStart = Carbon::create($targetYear, $targetMonth, 1)->startOfMonth();
        $monthEnd = (clone $monthStart)->endOfMonth();

        $yearStart = Carbon::create($targetYear, 1, 1)->startOfYear();
        $yearEnd = (clone $yearStart)->endOfYear();

        // -------------------------------------------------------------
        // 1. REWARDS MODULE SECTION
        // -------------------------------------------------------------
        $baseRewardQuery = Reward::query()
            ->where('submitted_by', $employee->id);

        if ($request->filled('category_id') && $request->input('category_id') !== 'all') {
            $baseRewardQuery->where('category_id', (int) $request->input('category_id'));
        }

        // Approved & Paid are considered earned rewards
        $earnedRewardQuery = (clone $baseRewardQuery)->whereIn('status', ['approved', 'paid']);

        // Month reward metrics
        $monthRewardQuery = (clone $earnedRewardQuery)->whereBetween('submitted_date', [$monthStart, $monthEnd]);
        $monthRewardAmount = (float) $monthRewardQuery->sum('amount');
        $monthRewardCount = $monthRewardQuery->count();

        // Year reward metrics
        $yearRewardQuery = (clone $earnedRewardQuery)->whereBetween('submitted_date', [$yearStart, $yearEnd]);
        $yearRewardAmount = (float) $yearRewardQuery->sum('amount');
        $yearRewardCount = $yearRewardQuery->count();

        // Lifetime total reward metrics
        $totalRewardAmount = (float) (clone $earnedRewardQuery)->sum('amount');
        $totalRewardCount = (clone $earnedRewardQuery)->count();

        // Pending nominations count
        $pendingRewardCount = (clone $baseRewardQuery)->where('status', 'pending')->count();

        // Monthly breakdown for selected year (charts / trends)
        $monthlyBreakdown = [];
        for ($m = 1; $m <= 12; $m++) {
            $mStart = Carbon::create($targetYear, $m, 1)->startOfMonth();
            $mEnd = (clone $mStart)->endOfMonth();

            $mRewards = (clone $earnedRewardQuery)->whereBetween('submitted_date', [$mStart, $mEnd]);
            $monthlyBreakdown[] = [
                'month_number' => $m,
                'month_name' => $mStart->format('M'),
                'amount' => round((float) $mRewards->sum('amount'), 2),
                'count' => $mRewards->count(),
            ];
        }

        // Employee rewards listing (recent / paginated preview)
        $rewardListing = (clone $baseRewardQuery)
            ->with(['category:id,reward_name,short_code'])
            ->orderByDesc('submitted_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get([
                'id',
                'claim_no',
                'category_id',
                'amount',
                'currency',
                'status',
                'submitted_date',
                'description',
            ]);

        $currency = Reward::query()->where('submitted_by', $employee->id)->value('currency') ?: 'AED';

        // -------------------------------------------------------------
        // 2. QUICK LEAVE & ATTENDANCE SUMMARY (Contextual Dashboard Data)
        // -------------------------------------------------------------
        $pendingLeavesCount = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'pending')
            ->count();

        return $this->successResponse([
            'employee' => [
                'id' => $employee->id,
                'emp_num' => $employee->emp_num,
                'name' => $user->name,
                'email' => $user->email,
                'designation' => $employee->designation,
                'department' => $employee->department?->name,
                'office' => $employee->officeLocation?->office_name,
            ],
            'rewards' => [
                'currency' => $currency,
                'this_month' => [
                    'month_number' => $targetMonth,
                    'month_name' => $monthStart->format('F Y'),
                    'amount' => round($monthRewardAmount, 2),
                    'count' => $monthRewardCount,
                ],
                'this_year' => [
                    'year' => $targetYear,
                    'amount' => round($yearRewardAmount, 2),
                    'count' => $yearRewardCount,
                ],
                'total_rewards' => [
                    'amount' => round($totalRewardAmount, 2),
                    'count' => $totalRewardCount,
                ],
                'pending_count' => $pendingRewardCount,
                'monthly_breakdown' => $monthlyBreakdown,
                'list' => $rewardListing,
            ],
            'overview' => [
                'pending_leaves_count' => $pendingLeavesCount,
            ],
        ], 'Employee dashboard overview fetched successfully.');
    }
}
