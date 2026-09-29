<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $requests = LeaveRequest::with([
            'employee:id,user_id,employee_code,image_url',
            'employee.user:id,name',
            'leaveType:id,leave_name,is_paid',
        ])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('leave-requests/index', [
            'requests' => $requests,
        ]);
    }

    public function create(): Response
    {
        $employees = Employee::query()
            ->with([
                'user:id,name',
                'department:id,name',
            ])
            ->select('id', 'user_id', 'emp_num', 'employee_code', 'designation', 'department_id', 'organisation_id')
            ->where('status', 1)
            ->orderBy('id')
            ->get();

        $profiles = Employee::profilesFor($employees);
        $employees->each(function (Employee $emp) use ($profiles) {
            $profile = $profiles->get($emp->id);
            $emp->gender = $profile?->gender;
            $emp->religion = $profile?->religion;
            $emp->setRelation('profile', $profile);
        });

        $leaveTypes = LeaveType::query()
            ->select('id', 'leave_name', 'code', 'is_paid', 'requires_attachment', 'status', 'gender', 'requires_handover')
            ->orderByRaw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
            ->orderBy('leave_name')
            ->get();

        $currentYear = (int) date('Y');
        $leaveBalances = LeaveBalance::query()
            ->where('year', $currentYear)
            ->select('id', 'employee_id', 'leave_type_id', 'allocated', 'used', 'balance')
            ->get();

        $activeLeaves = LeaveRequest::query()
            ->whereIn('status', ['applied', 'approved'])
            ->where('end_date', '>=', now()->startOfYear())
            ->select('id', 'employee_id', 'start_date', 'end_date', 'status')
            ->get();

        return Inertia::render('leave-requests/create', [
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
            'leaveBalances' => $leaveBalances,
            'activeLeaves' => $activeLeaves,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:salescrm.employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'total_days' => 'nullable|numeric|min:0.5',
            'status' => 'required|in:applied,approved',
            'remarks' => 'nullable|string',
            'certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'handover_person_id' => 'nullable|exists:salescrm.employees,id',
            'handover_description' => 'nullable|string',
        ]);

        $employee = Employee::query()->findOrFail($validated['employee_id']);
        $leaveType = LeaveType::query()->findOrFail($validated['leave_type_id']);

        $profile = EmployeeProfile::query()->where('employee_id', $employee->id)->first();
        $rawEmployeeGender = $profile?->gender ?? $employee->gender ?? null;
        $employeeGender = match (strtolower(trim($rawEmployeeGender ?? ''))) {
            'm', 'male' => 'male',
            'f', 'female' => 'female',
            default => null,
        };

        $isMaternity = $leaveType->code === 'MATERNITY' || str_contains(strtolower($leaveType->leave_name), 'maternity');
        $isPaternity = $leaveType->code === 'PATERNITY' || str_contains(strtolower($leaveType->leave_name), 'paternity');

        $leaveTypeGender = match (strtolower(trim($leaveType->gender ?? ''))) {
            'female', 'f' => 'female',
            'male', 'm' => 'male',
            default => null,
        };

        if ($isMaternity) {
            $leaveTypeGender = 'female';
        } elseif ($isPaternity) {
            $leaveTypeGender = 'male';
        }

        if ($leaveTypeGender !== null && $employeeGender !== null && $employeeGender !== $leaveTypeGender) {
            throw ValidationException::withMessages([
                'leave_type_id' => ["{$leaveType->leave_name} is only applicable for {$leaveTypeGender} employees."],
            ]);
        }

        $isPilgrimage = $leaveType->code === 'PILGRIMAGE' || str_contains(strtolower($leaveType->leave_name), 'pilgrim') || str_contains(strtolower($leaveType->leave_name), 'hajj');
        if ($isPilgrimage) {
            $rawEmployeeReligion = strtolower(trim($profile?->religion ?? ''));
            $isMuslim = in_array($rawEmployeeReligion, ['muslim', 'islam', 'islamic'])
                || str_contains($rawEmployeeReligion, 'muslim')
                || str_contains($rawEmployeeReligion, 'islam');

            if (! $isMuslim) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ['Pilgrimage Leave is only applicable for Muslim employees.'],
                ]);
            }
        }

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        $handoverPersonId = $validated['handover_person_id'] ?? null;

        if ($leaveType->requires_handover && empty($handoverPersonId)) {
            throw ValidationException::withMessages([
                'handover_person_id' => ['A handover person is required for this leave type.'],
            ]);
        }

        if (! empty($handoverPersonId)) {
            if ((int) $handoverPersonId === (int) $employee->id) {
                throw ValidationException::withMessages([
                    'handover_person_id' => ['You cannot select the employee themselves as the handover person.'],
                ]);
            }

            $handoverOnLeave = LeaveRequest::where('employee_id', $handoverPersonId)
                ->whereIn('status', ['applied', 'approved'])
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->exists();

            if ($handoverOnLeave) {
                throw ValidationException::withMessages([
                    'handover_person_id' => ['The selected handover person is also on leave during the requested dates.'],
                ]);
            }

            if ($leaveType->requires_handover && empty($validated['handover_description'])) {
                throw ValidationException::withMessages([
                    'handover_description' => ['Handover description is required.'],
                ]);
            }
        }

        $totalDays = $validated['total_days'] ?? (
            Carbon::parse($validated['end_date'])->diffInDays(Carbon::parse($validated['start_date'])) + 1
        );

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $validated['employee_id'],
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'total_days' => $totalDays,
            'remarks' => $validated['remarks'] ?? null,
            'status' => $validated['status'],
            'created_by' => $request->user()?->id,
        ]);

        $hrName = $request->user()?->name ?? 'HR';
        $historyRemarks = "Applied by HR ({$hrName}) on behalf of employee.";
        if (! empty($validated['remarks'])) {
            $historyRemarks .= ' Reason: '.$validated['remarks'];
        }

        $leaveRequest->histories()->create([
            'action_type' => $validated['status'],
            'remarks' => $historyRemarks,
            'done_by' => $request->user()?->id,
            'action_on' => now(),
        ]);

        if (! empty($handoverPersonId)) {
            $leaveRequest->details()->create([
                'field_name' => 'Handover Person ID',
                'field_key' => 'handover_person_id',
                'field_value' => (string) $handoverPersonId,
            ]);

            if (! empty($validated['handover_description'])) {
                $leaveRequest->details()->create([
                    'field_name' => 'Handover Description',
                    'field_key' => 'handover_description',
                    'field_value' => (string) $validated['handover_description'],
                ]);
            }
        }

        if ($request->hasFile('certificate')) {
            $path = $request->file('certificate')->store('leave-certificates', 'public');

            $leaveRequest->files()->create([
                'file_path' => $path,
                'uploaded_by' => $request->user()?->id,
                'uploaded_date' => now(),
            ]);
        }

        if ($validated['status'] === 'approved') {
            $leaveRequest->approvals()->create([
                'approval_level' => 'HR Direct Approval',
                'approver_id' => $request->user()?->id,
                'remarks' => 'Approved directly by HR upon submission.',
                'approved_date' => now(),
            ]);

            $currentYear = (int) Carbon::parse($validated['start_date'])->format('Y');
            $balance = LeaveBalance::where('employee_id', $validated['employee_id'])
                ->where('leave_type_id', $validated['leave_type_id'])
                ->where('year', $currentYear)
                ->first();

            if ($balance) {
                $balance->used += $totalDays;
                $balance->balance = max(0, $balance->balance - $totalDays);
                $balance->save();
            }
        }

        return redirect()->route('leave-requests.index')
            ->with('success', 'Leave request submitted successfully on behalf of employee.');
    }

    public function show(LeaveRequest $leave_request)
    {
        $leave_request->load([
            'employee:id,user_id,employee_code,designation,department_id,image_url',
            'employee.user:id,name,email',
            'employee.department:id,name',
            'leaveType:id,leave_name,is_paid,requires_handover',
            'files',
            'details',
            'histories' => function ($query) {
                $query->orderBy('action_on', 'desc');
            },
            'histories.doneBy:id,name',
        ]);

        $handoverDetail = $leave_request->details->firstWhere('field_key', 'handover_person_id');
        $handoverEmployee = null;
        if ($handoverDetail && ! empty($handoverDetail->field_value)) {
            $handoverEmployee = Employee::with([
                'user:id,name,email',
                'department:id,name',
            ])->find($handoverDetail->field_value);
        }

        return Inertia::render('leave-requests/show', [
            'leave_request' => $leave_request,
            'handoverEmployee' => $handoverEmployee,
        ]);
    }

    public function edit(LeaveRequest $leave_request): Response
    {
        $leave_request->load([
            'employee:id,user_id,emp_num,employee_code,designation,department_id',
            'employee.user:id,name',
            'employee.department:id,name',
            'leaveType:id,leave_name,code,requires_handover',
            'details',
            'files',
        ]);

        $employees = Employee::query()
            ->with([
                'user:id,name',
                'department:id,name',
            ])
            ->select('id', 'user_id', 'emp_num', 'employee_code', 'designation', 'department_id', 'organisation_id')
            ->where('status', 1)
            ->orderBy('id')
            ->get();

        $profiles = Employee::profilesFor($employees);
        $employees->each(function (Employee $emp) use ($profiles) {
            $profile = $profiles->get($emp->id);
            $emp->gender = $profile?->gender;
            $emp->religion = $profile?->religion;
            $emp->setRelation('profile', $profile);
        });

        $leaveTypes = LeaveType::query()
            ->select('id', 'leave_name', 'code', 'is_paid', 'requires_attachment', 'status', 'gender', 'requires_handover')
            ->orderByRaw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
            ->orderBy('leave_name')
            ->get();

        $year = (int) Carbon::parse($leave_request->start_date)->format('Y');
        $leaveBalances = LeaveBalance::query()
            ->where('year', $year)
            ->select('id', 'employee_id', 'leave_type_id', 'allocated', 'used', 'balance')
            ->get();

        $activeLeaves = LeaveRequest::query()
            ->where('id', '!=', $leave_request->id)
            ->whereIn('status', ['applied', 'approved'])
            ->where('end_date', '>=', now()->startOfYear())
            ->select('id', 'employee_id', 'start_date', 'end_date', 'status')
            ->get();

        $handoverDetail = $leave_request->details->firstWhere('field_key', 'handover_person_id');
        $handoverDescDetail = $leave_request->details->firstWhere('field_key', 'handover_description');

        return Inertia::render('leave-requests/edit', [
            'leaveRequest' => [
                'id' => $leave_request->id,
                'employee_id' => $leave_request->employee_id,
                'leave_type_id' => $leave_request->leave_type_id,
                'start_date' => Carbon::parse($leave_request->start_date)->format('Y-m-d'),
                'end_date' => Carbon::parse($leave_request->end_date)->format('Y-m-d'),
                'total_days' => $leave_request->total_days,
                'status' => $leave_request->status,
                'remarks' => $leave_request->remarks,
                'employee' => $leave_request->employee,
                'leave_type' => $leave_request->leaveType,
                'handover_person_id' => $handoverDetail?->field_value,
                'handover_description' => $handoverDescDetail?->field_value,
                'files' => $leave_request->files,
            ],
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
            'leaveBalances' => $leaveBalances,
            'activeLeaves' => $activeLeaves,
        ]);
    }

    public function update(Request $request, LeaveRequest $leave_request)
    {
        // If full edit form submission (has start_date)
        if ($request->has('start_date')) {
            $validated = $request->validate([
                'employee_id' => 'nullable|exists:salescrm.employees,id',
                'leave_type_id' => 'required|exists:leave_types,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'total_days' => 'nullable|numeric|min:0.5',
                'status' => 'required|in:applied,approved,rejected,cancelled',
                'remarks' => 'nullable|string',
                'certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
                'handover_person_id' => 'nullable|exists:salescrm.employees,id',
                'handover_description' => 'nullable|string',
            ]);

            $employeeId = $validated['employee_id'] ?? $leave_request->employee_id;
            $employee = Employee::query()->findOrFail($employeeId);
            $leaveType = LeaveType::query()->findOrFail($validated['leave_type_id']);

            $profile = EmployeeProfile::query()->where('employee_id', $employee->id)->first();
            $rawEmployeeGender = $profile?->gender ?? $employee->gender ?? null;
            $employeeGender = match (strtolower(trim($rawEmployeeGender ?? ''))) {
                'm', 'male' => 'male',
                'f', 'female' => 'female',
                default => null,
            };

            $isMaternity = $leaveType->code === 'MATERNITY' || str_contains(strtolower($leaveType->leave_name), 'maternity');
            $isPaternity = $leaveType->code === 'PATERNITY' || str_contains(strtolower($leaveType->leave_name), 'paternity');

            $leaveTypeGender = match (strtolower(trim($leaveType->gender ?? ''))) {
                'female', 'f' => 'female',
                'male', 'm' => 'male',
                default => null,
            };

            if ($isMaternity) {
                $leaveTypeGender = 'female';
            } elseif ($isPaternity) {
                $leaveTypeGender = 'male';
            }

            if ($leaveTypeGender !== null && $employeeGender !== null && $employeeGender !== $leaveTypeGender) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ["{$leaveType->leave_name} is only applicable for {$leaveTypeGender} employees."],
                ]);
            }

            $isPilgrimage = $leaveType->code === 'PILGRIMAGE' || str_contains(strtolower($leaveType->leave_name), 'pilgrim') || str_contains(strtolower($leaveType->leave_name), 'hajj');
            if ($isPilgrimage) {
                $rawEmployeeReligion = strtolower(trim($profile?->religion ?? ''));
                $isMuslim = in_array($rawEmployeeReligion, ['muslim', 'islam', 'islamic'])
                    || str_contains($rawEmployeeReligion, 'muslim')
                    || str_contains($rawEmployeeReligion, 'islam');

                if (! $isMuslim) {
                    throw ValidationException::withMessages([
                        'leave_type_id' => ["{$leaveType->leave_name} is only applicable for Muslim employees."],
                    ]);
                }
            }

            $startDate = Carbon::parse($validated['start_date'])->startOfDay();
            $endDate = Carbon::parse($validated['end_date'])->endOfDay();

            // Check overlapping active/approved leave for the employee (excluding this leave request!)
            if (in_array($validated['status'], ['applied', 'approved'])) {
                $overlapping = LeaveRequest::where('employee_id', $employee->id)
                    ->where('id', '!=', $leave_request->id)
                    ->whereIn('status', ['applied', 'approved'])
                    ->where('start_date', '<=', $endDate)
                    ->where('end_date', '>=', $startDate)
                    ->exists();

                if ($overlapping) {
                    throw ValidationException::withMessages([
                        'start_date' => ['The employee already has an active or approved leave application overlapping with the selected dates.'],
                    ]);
                }
            }

            $handoverPersonId = $validated['handover_person_id'] ?? null;
            if (! empty($handoverPersonId)) {
                if ((int) $handoverPersonId === (int) $employee->id) {
                    throw ValidationException::withMessages([
                        'handover_person_id' => ['You cannot select the employee themselves as the handover person.'],
                    ]);
                }

                $handoverOnLeave = LeaveRequest::where('employee_id', $handoverPersonId)
                    ->where('id', '!=', $leave_request->id)
                    ->whereIn('status', ['applied', 'approved'])
                    ->where('start_date', '<=', $endDate)
                    ->where('end_date', '>=', $startDate)
                    ->exists();

                if ($handoverOnLeave) {
                    throw ValidationException::withMessages([
                        'handover_person_id' => ['The selected handover person is also on leave during the requested dates.'],
                    ]);
                }

                if ($leaveType->requires_handover && empty($validated['handover_description'])) {
                    throw ValidationException::withMessages([
                        'handover_description' => ['Handover description is required.'],
                    ]);
                }
            }

            $totalDays = $validated['total_days'] ?? (
                Carbon::parse($validated['end_date'])->diffInDays(Carbon::parse($validated['start_date'])) + 1
            );

            $oldState = [
                'status' => $leave_request->status,
                'total_days' => (float) $leave_request->total_days,
                'leave_type_id' => (int) $leave_request->leave_type_id,
                'employee_id' => (int) $leave_request->employee_id,
                'start_date' => $leave_request->start_date,
            ];

            $newState = [
                'status' => $validated['status'],
                'total_days' => (float) $totalDays,
                'leave_type_id' => (int) $validated['leave_type_id'],
                'employee_id' => (int) $employeeId,
                'start_date' => $validated['start_date'],
            ];

            // Adjust leave balances according to the changes
            $this->adjustLeaveBalances($leave_request, $oldState, $newState);

            $leave_request->update([
                'employee_id' => $employeeId,
                'leave_type_id' => $validated['leave_type_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'total_days' => $totalDays,
                'remarks' => $validated['remarks'] ?? null,
                'status' => $validated['status'],
            ]);

            // Update details (handover)
            if (! empty($handoverPersonId)) {
                $leave_request->details()->updateOrCreate(
                    ['field_key' => 'handover_person_id'],
                    ['field_name' => 'Handover Person ID', 'field_value' => (string) $handoverPersonId]
                );

                if (! empty($validated['handover_description'])) {
                    $leave_request->details()->updateOrCreate(
                        ['field_key' => 'handover_description'],
                        ['field_name' => 'Handover Description', 'field_value' => (string) $validated['handover_description']]
                    );
                } else {
                    $leave_request->details()->where('field_key', 'handover_description')->delete();
                }
            } else {
                $leave_request->details()->whereIn('field_key', ['handover_person_id', 'handover_description'])->delete();
            }

            if ($request->hasFile('certificate')) {
                $path = $request->file('certificate')->store('leave-certificates', 'public');

                $leave_request->files()->create([
                    'file_path' => $path,
                    'uploaded_by' => $request->user()?->id,
                    'uploaded_date' => now(),
                ]);
            }

            $hrName = $request->user()?->name ?? 'HR';
            $changesDesc = [];
            if ($oldState['status'] !== $newState['status']) {
                $changesDesc[] = "Status: {$oldState['status']} -> {$newState['status']}";
            }
            if ($oldState['total_days'] != $newState['total_days']) {
                $changesDesc[] = "Days: {$oldState['total_days']} -> {$newState['total_days']}";
            }
            $historyRemarks = "Edited by HR ({$hrName}). ".implode(', ', $changesDesc);
            if (! empty($validated['remarks'])) {
                $historyRemarks .= ' Remarks: '.$validated['remarks'];
            }

            $leave_request->histories()->create([
                'action_type' => $validated['status'],
                'remarks' => $historyRemarks,
                'done_by' => $request->user()?->id,
                'action_on' => now(),
            ]);

            if (in_array($validated['status'], ['approved', 'rejected'])) {
                $leave_request->approvals()->create([
                    'approval_level' => 'HR Update Approval',
                    'approver_id' => $request->user()?->id,
                    'remarks' => $validated['remarks'] ?? 'Updated directly by HR.',
                    'approved_date' => now(),
                ]);
            }

            return redirect()->route('leave-requests.index')
                ->with('success', 'Leave request updated successfully. Any affected leave balances were updated.');
        }

        // Quick status update (Approve, Reject, Cancel buttons)
        $validated = $request->validate([
            'status' => 'required|in:applied,approved,rejected,cancelled',
            'remarks' => 'nullable|string',
        ]);

        $oldState = [
            'status' => $leave_request->status,
            'total_days' => (float) $leave_request->total_days,
            'leave_type_id' => (int) $leave_request->leave_type_id,
            'employee_id' => (int) $leave_request->employee_id,
            'start_date' => $leave_request->start_date,
        ];

        $newState = [
            'status' => $validated['status'],
            'total_days' => (float) $leave_request->total_days,
            'leave_type_id' => (int) $leave_request->leave_type_id,
            'employee_id' => (int) $leave_request->employee_id,
            'start_date' => $leave_request->start_date,
        ];

        $this->adjustLeaveBalances($leave_request, $oldState, $newState);

        $leave_request->update([
            'status' => $validated['status'],
        ]);

        $leave_request->histories()->create([
            'action_type' => $validated['status'],
            'remarks' => $validated['remarks'] ?? null,
            'done_by' => $request->user()?->id,
            'action_on' => now(),
        ]);

        if (in_array($validated['status'], ['approved', 'rejected'])) {
            $leave_request->approvals()->create([
                'approval_level' => 'Final',
                'approver_id' => $request->user()?->id,
                'remarks' => $validated['remarks'] ?? null,
                'approved_date' => now(),
            ]);
        }

        return back()->with('success', 'Leave request status updated successfully.');
    }

    public function cancel(Request $request, LeaveRequest $leave_request): RedirectResponse
    {
        $validated = $request->validate([
            'remarks' => 'nullable|string',
        ]);

        if ($leave_request->status === 'cancelled') {
            return back()->with('info', 'This leave request is already cancelled.');
        }

        $oldState = [
            'status' => $leave_request->status,
            'total_days' => (float) $leave_request->total_days,
            'leave_type_id' => (int) $leave_request->leave_type_id,
            'employee_id' => (int) $leave_request->employee_id,
            'start_date' => $leave_request->start_date,
        ];

        $newState = [
            'status' => 'cancelled',
            'total_days' => (float) $leave_request->total_days,
            'leave_type_id' => (int) $leave_request->leave_type_id,
            'employee_id' => (int) $leave_request->employee_id,
            'start_date' => $leave_request->start_date,
        ];

        $this->adjustLeaveBalances($leave_request, $oldState, $newState);

        $leave_request->update([
            'status' => 'cancelled',
        ]);

        $hrName = $request->user()?->name ?? 'HR';
        $remarks = "Cancelled by HR ({$hrName}).";
        if (! empty($validated['remarks'])) {
            $remarks .= ' Reason: '.$validated['remarks'];
        }

        $leave_request->histories()->create([
            'action_type' => 'cancelled',
            'remarks' => $remarks,
            'done_by' => $request->user()?->id,
            'action_on' => now(),
        ]);

        return back()->with('success', 'Leave request cancelled successfully. Any deducted balance has been refunded.');
    }

    protected function adjustLeaveBalances(LeaveRequest $leaveRequest, array $oldState, array $newState): void
    {
        $oldStatus = $oldState['status'] ?? null;
        $newStatus = $newState['status'] ?? null;

        $oldDays = (float) ($oldState['total_days'] ?? 0);
        $newDays = (float) ($newState['total_days'] ?? 0);

        $oldEmpId = (int) ($oldState['employee_id'] ?? 0);
        $newEmpId = (int) ($newState['employee_id'] ?? 0);

        $oldTypeId = (int) ($oldState['leave_type_id'] ?? 0);
        $newTypeId = (int) ($newState['leave_type_id'] ?? 0);

        $oldYear = ! empty($oldState['start_date']) ? (int) Carbon::parse($oldState['start_date'])->format('Y') : (int) date('Y');
        $newYear = ! empty($newState['start_date']) ? (int) Carbon::parse($newState['start_date'])->format('Y') : (int) date('Y');

        // Case 1: Was approved previously
        if ($oldStatus === 'approved') {
            if ($newStatus !== 'approved') {
                // Refund the old approved days back to balance
                $this->refundDaysToBalance($oldEmpId, $oldTypeId, $oldYear, $oldDays);
            } else {
                // Still approved, but could have changed employee, leave type, year, or days
                if ($oldEmpId !== $newEmpId || $oldTypeId !== $newTypeId || $oldYear !== $newYear) {
                    // Refund old balance, deduct from new balance
                    $this->refundDaysToBalance($oldEmpId, $oldTypeId, $oldYear, $oldDays);
                    $this->deductDaysFromBalance($newEmpId, $newTypeId, $newYear, $newDays);
                } else {
                    // Same employee, leave type, and year: check days difference
                    $diff = $newDays - $oldDays;
                    if ($diff > 0) {
                        // Days increased: deduct additional days
                        $this->deductDaysFromBalance($newEmpId, $newTypeId, $newYear, $diff);
                    } elseif ($diff < 0) {
                        // Days decreased: refund the difference
                        $this->refundDaysToBalance($newEmpId, $newTypeId, $newYear, abs($diff));
                    }
                }
            }
        } elseif ($newStatus === 'approved') {
            // Case 2: Was NOT approved before, but is now approved
            $this->deductDaysFromBalance($newEmpId, $newTypeId, $newYear, $newDays);
        }
    }

    protected function deductDaysFromBalance(int $employeeId, int $leaveTypeId, int $year, float $days): void
    {
        if ($days <= 0) {
            return;
        }

        $balance = LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        if ($balance) {
            $balance->used = (float) $balance->used + $days;
            $balance->balance = max(0.0, (float) $balance->balance - $days);
            $balance->save();
        }
    }

    protected function refundDaysToBalance(int $employeeId, int $leaveTypeId, int $year, float $days): void
    {
        if ($days <= 0) {
            return;
        }

        $balance = LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        if ($balance) {
            $balance->used = max(0.0, (float) $balance->used - $days);
            $balance->balance = (float) $balance->balance + $days;
            $balance->save();
        }
    }
}
