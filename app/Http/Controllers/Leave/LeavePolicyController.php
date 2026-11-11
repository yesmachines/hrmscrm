<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreLeavePolicyRequest;
use App\Http\Requests\Leave\UpdateLeavePolicyRequest;
use App\Models\LeavePolicy;
use App\Models\LeavePolicyTier;
use App\Models\LeaveType;
use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LeavePolicyController extends Controller
{
    public function index(): Response
    {
        $leavePolicies = LeavePolicy::query()
            ->with([
                'leaveType:id,leave_name,code',
                'organisation:id,org_name',
                'tiers',
            ])
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (LeavePolicy $policy): array => $this->payload($policy));

        return Inertia::render('leave-policies/index', [
            'leavePolicies' => $leavePolicies,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('leave-policies/create', [
            'leaveTypes' => $this->leaveTypes(),
            'organisations' => $this->organisations(),
        ]);
    }

    public function store(StoreLeavePolicyRequest $request): RedirectResponse
    {
        $payload = $request->payload();
        $tiers = $payload['tiers'] ?? [];
        unset($payload['tiers']);

        DB::transaction(function () use ($payload, $tiers): void {
            $policy = LeavePolicy::query()->create($payload);
            $policy->tiers()->createMany($tiers);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Leave policy created.'),
        ]);

        return to_route('leave-policies.index');
    }

    public function show(LeavePolicy $leave_policy): Response
    {
        $leave_policy->load([
            'leaveType:id,leave_name,code',
            'organisation:id,org_name',
            'tiers',
        ]);

        return Inertia::render('leave-policies/show', [
            'leavePolicy' => $this->payload($leave_policy),
        ]);
    }

    public function edit(LeavePolicy $leave_policy): Response
    {
        $leave_policy->load([
            'leaveType:id,leave_name,code',
            'organisation:id,org_name',
            'tiers',
        ]);

        return Inertia::render('leave-policies/edit', [
            'leavePolicy' => $this->payload($leave_policy),
            'leaveTypes' => $this->leaveTypes(),
            'organisations' => $this->organisations(),
        ]);
    }

    public function update(
        UpdateLeavePolicyRequest $request,
        LeavePolicy $leave_policy,
    ): RedirectResponse {
        $payload = $request->payload();
        $tiers = $payload['tiers'] ?? null;
        unset($payload['tiers']);

        DB::transaction(function () use ($leave_policy, $payload, $tiers): void {
            $leave_policy->update($payload);

            if ($tiers !== null) {
                $leave_policy->tiers()->delete();
                $leave_policy->tiers()->createMany($tiers);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Leave policy updated.'),
        ]);

        return to_route('leave-policies.show', $leave_policy);
    }

    public function destroy(LeavePolicy $leave_policy): RedirectResponse
    {
        $leave_policy->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Leave policy deleted.'),
        ]);

        return to_route('leave-policies.index');
    }

    /**
     * @return list<array{id: int, name: string, disabled: bool}>
     */
    private function leaveTypes(): array
    {
        return LeaveType::query()
            ->where('status', 1)
            ->orderBy('leave_name')
            ->get(['id', 'leave_name', 'code'])
            ->map(fn (LeaveType $leaveType): array => [
                'id' => $leaveType->id,
                'name' => $leaveType->leave_name.' ('.$leaveType->code.')',
                'disabled' => false,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function organisations(): array
    {
        return Organisation::query()
            ->orderByDesc('status')
            ->orderBy('org_name')
            ->get(['id', 'org_name', 'status'])
            ->map(fn (Organisation $organisation): array => [
                'id' => $organisation->id,
                'name' => $organisation->status === 1
                    ? $organisation->org_name
                    : $organisation->org_name.' (Inactive)',
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LeavePolicy $policy): array
    {
        return [
            'id' => $policy->id,
            'leave_type_id' => $policy->leave_type_id,
            'organisation_id' => $policy->organisation_id,
            'annual_limit' => $policy->annual_limit,
            'allow_once' => (int) $policy->allow_once,
            'allow_balance' => (int) $policy->allow_balance,
            'requires_document_after_days' => $policy->requires_document_after_days,
            'requires_weekend_document' => (int) $policy->requires_weekend_document,
            'requires_handover' => (int) $policy->requires_handover,
            'carry_forward_weeks' => $policy->carry_forward_weeks,
            'encashment' => (int) $policy->encashment,
            'remarks' => $policy->remarks,
            'requires_attachment' => (int) $policy->requires_attachment,
            'probation_applicable' => (int) $policy->probation_applicable,
            'minimum_service_months' => $policy->minimum_service_months,
            'exclude_rules' => $policy->exclude_rules,
            'tiers' => $policy->tiers
                ->sortBy('from_day')
                ->values()
                ->map(fn (LeavePolicyTier $tier): array => [
                    'id' => $tier->id,
                    'from_day' => $tier->from_day,
                    'to_day' => $tier->to_day,
                    'pay_percentage' => $tier->pay_percentage,
                ])
                ->all(),
            'leave_type' => $policy->leaveType
                ? [
                    'id' => $policy->leaveType->id,
                    'name' => $policy->leaveType->leave_name,
                    'code' => $policy->leaveType->code,
                ]
                : null,
            'organisation' => $policy->organisation
                ? [
                    'id' => $policy->organisation->id,
                    'name' => $policy->organisation->org_name,
                ]
                : null,
        ];
    }
}
