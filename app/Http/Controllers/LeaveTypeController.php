<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveTypeRequest;
use App\Http\Requests\UpdateLeaveTypeRequest;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LeaveTypeController extends Controller
{
    public function index(): Response
    {
        $leaveTypes = LeaveType::query()
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (LeaveType $leaveType): array => $this->payload($leaveType));

        return Inertia::render('leave-types/index', [
            'leaveTypes' => $leaveTypes,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('leave-types/create');
    }

    public function store(StoreLeaveTypeRequest $request): RedirectResponse
    {
        $leaveType = LeaveType::query()->create($request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Leave type created.'),
        ]);

        return to_route('leave-types.show', $leaveType);
    }

    public function show(LeaveType $leave_type): Response
    {
        return Inertia::render('leave-types/show', [
            'leaveType' => $this->payload($leave_type),
        ]);
    }

    public function edit(LeaveType $leave_type): Response
    {
        return Inertia::render('leave-types/edit', [
            'leaveType' => $this->payload($leave_type),
        ]);
    }

    public function update(
        UpdateLeaveTypeRequest $request,
        LeaveType $leave_type,
    ): RedirectResponse {
        $leave_type->update($request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Leave type updated.'),
        ]);

        return to_route('leave-types.show', $leave_type);
    }

    public function destroy(LeaveType $leave_type): RedirectResponse
    {
        if ($leave_type->policies()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Cannot delete a leave type that has policies.'),
            ]);

            return back();
        }

        if ($leave_type->requests()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Cannot delete a leave type that has leave requests.'),
            ]);

            return back();
        }

        if ($leave_type->balances()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Cannot delete a leave type that has leave balances.'),
            ]);

            return back();
        }

        $leave_type->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Leave type deleted.'),
        ]);

        return to_route('leave-types.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LeaveType $leaveType): array
    {
        return [
            'id' => $leaveType->id,
            'leave_name' => $leaveType->leave_name,
            'code' => $leaveType->code,
            'is_paid' => (int) $leaveType->is_paid,
            'requires_attachment' => (int) $leaveType->requires_attachment,
            'requires_approval' => (int) $leaveType->requires_approval,
            'max_days' => $leaveType->max_days,
            'annual_limit' => $leaveType->annual_limit,
            'gender' => $leaveType->gender,
            'allow_once' => (int) $leaveType->allow_once,
            'allow_balance' => (int) $leaveType->allow_balance,
            'status' => (int) $leaveType->status,
            'requires_handover' => (int) $leaveType->requires_handover,
        ];
    }
}
