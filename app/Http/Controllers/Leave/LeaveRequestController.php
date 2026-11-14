<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Services\FestivalLeaveService;
use App\Services\LeaveDurationCalculator;
use App\Services\LeaveTypeEligibilityService;
use App\Services\LeaveValidationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly FestivalLeaveService $festivalLeaveService) {}

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

    public function create(LeaveTypeEligibilityService $eligibilityService): Response
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
        $countryIdsByEmployee = $this->festivalLeaveService->countryIdsForProfiles($profiles);
        $employees->each(function (Employee $emp) use ($profiles, $countryIdsByEmployee) {
            $profile = $profiles->get($emp->id);
            $emp->country_ids = $countryIdsByEmployee->get($emp->id, []);
            $emp->gender = $profile?->gender;
            $emp->religion = $profile?->religion;
            $emp->marital_status = $profile?->marital_status;
            if ($profile) {
                $profile->makeVisible(['nationality', 'home_country']);
            }
            $emp->setRelation('profile', $profile);
        });

        $leaveTypes = LeaveType::query()
            ->with('policies:id,leave_type_id,organisation_id,annual_limit,requires_document_after_days,requires_attachment,requires_handover,requires_weekend_document')
            ->select('id', 'leave_name', 'code', 'is_paid', 'requires_approval', 'status', 'gender', 'religion')
            ->orderByRaw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
            ->orderBy('leave_name')
            ->get();

        $leaveTypes->each(function (LeaveType $type) use ($eligibilityService, $employees, $profiles): void {
            $type->setAttribute('eligible_employee_ids', $eligibilityService->eligibleEmployeeIds($type, $employees, $profiles));
        });

        $currentYear = (int) date('Y');
        $leaveBalances = LeaveBalance::query()
            ->where('year', $currentYear)
            ->select('id', 'employee_id', 'leave_type_id', 'allocated', 'used', 'balance')
            ->get();

        $activeLeaves = LeaveRequest::query()
            ->whereIn('status', ['applied', 'approved'])
            ->whereDate('end_date', '>=', now()->startOfYear()->toDateString())
            ->select('id', 'employee_id', 'start_date', 'end_date', 'status')
            ->get();

        return Inertia::render('leave-requests/create', [
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
            'leaveBalances' => $leaveBalances,
            'activeLeaves' => $activeLeaves,
            'compassionateRelationships' => config('params.leave_type.compassionate_relationships'),
            'festivals' => $this->festivalLeaveService->optionsForEmployees($employees, $profiles, $countryIdsByEmployee),
        ]);
    }

    public function validateForm(
        Request $request,
        LeaveValidationService $leaveValidationService,
    ): JsonResponse {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:salescrm.employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'request_type' => ['nullable', 'in:fullday,firsthalf,secondhalf'],
            'due_date' => ['nullable', 'date'],
            'child_birth_date' => ['nullable', 'date'],
            'family_relationship' => ['nullable', 'string'],
            'emergency_contact_no' => ['nullable', 'string', 'regex:/^\+?[0-9]+$/'],
            'travel_contact_no' => ['nullable', 'string', 'regex:/^\+?[0-9]+$/'],
            'traveling_outside_country' => ['nullable', 'boolean'],
            'destination' => ['nullable', 'string'],
            'handover_person_id' => ['nullable', 'exists:salescrm.employees,id'],
            'handover_description' => ['nullable', 'string'],
            'festival_id' => ['nullable', 'integer', 'exists:festivals,id'],
            'certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'ignore_leave_request_id' => ['nullable', 'integer', Rule::exists('leave_requests', 'id')->where('employee_id', $request->input('employee_id'))],
        ]);

        $employee = Employee::query()->findOrFail($validated['employee_id']);
        $existing = empty($validated['ignore_leave_request_id'])
            ? null
            : LeaveRequest::query()->with('details')->findOrFail($validated['ignore_leave_request_id']);
        if ($existing !== null) {
            if ((int) $validated['leave_type_id'] !== (int) $existing->leave_type_id) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ['Leave type cannot be changed after the request is created.'],
                ]);
            }

            $validated += ['request_type' => $existing->request_type ?? 'fullday'];
            foreach (['due_date', 'child_birth_date', 'family_relationship', 'festival_id', 'handover_person_id', 'handover_description'] as $field) {
                if (! array_key_exists($field, $validated)) {
                    $validated[$field] = $existing->details->firstWhere('field_key', $field)?->field_value;
                }
            }
        }

        try {
            $validation = $leaveValidationService->validateApplication(
                $employee,
                array_merge($validated, [
                    'employee_profile' => EmployeeProfile::query()
                        ->where('employee_id', $employee->id)
                        ->first(),
                ]),
                $request->hasFile('certificate') || ($existing?->files()->exists() ?? false),
                $validated['ignore_leave_request_id'] ?? null,
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'valid' => false,
                'errors' => $exception->errors(),
            ]);
        }
        $leaveType = $validation['leave_type'];

        return response()->json([
            'valid' => true,
            'total_days' => $validation['total_days'],
            'annual_limit' => $validation['policy']?->annual_limit,
            'attachment_required' => $validation['attachment_required'],
            'handover_required' => $validation['policy']?->requiresHandoverForDuration($validation['total_days']) ?? false,
            'required_fields' => $leaveValidationService->detailFields($leaveType),
        ]);
    }

    public function store(
        Request $request,
        LeaveTypeEligibilityService $eligibilityService,
        LeaveValidationService $leaveValidationService,
        LeaveDurationCalculator $leaveDurationCalculator,
    ): RedirectResponse {
        $validated = $request->validate([
            'employee_id' => 'required|exists:salescrm.employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'request_type' => 'nullable|in:fullday,firsthalf,secondhalf',
            'total_days' => 'nullable|numeric|min:0',
            'status' => 'required|in:applied,approved',
            'remarks' => 'nullable|string',
            'certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'handover_person_id' => 'nullable|exists:salescrm.employees,id',
            'handover_description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'child_birth_date' => 'nullable|date',
            'family_relationship' => 'nullable|string',
            'festival_id' => 'nullable|integer|exists:festivals,id',
        ]);

        $employee = Employee::query()->findOrFail($validated['employee_id']);
        $leaveType = LeaveType::query()->findOrFail($validated['leave_type_id']);
        $policy = $leaveType->policyForOrganisation($employee->organisation_id);
        $requiresApproval = (bool) $leaveType->requires_approval;
        $status = $requiresApproval ? $validated['status'] : 'approved';

        $profile = EmployeeProfile::query()->where('employee_id', $employee->id)->first();
        $rawEmployeeGender = $profile?->gender ?? $employee->gender ?? null;
        $eligibilityService->assertEligible($leaveType, $rawEmployeeGender, $profile?->religion, $profile?->marital_status);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();
        $requestType = $validated['request_type'] ?? 'fullday';

        if ($requestType !== 'fullday' && ! $startDate->isSameDay($endDate)) {
            throw ValidationException::withMessages([
                'request_type' => ['Half-day leave must start and end on the same date.'],
            ]);
        }

        if ($requestType !== 'fullday' && ! $leaveDurationCalculator->isWeekday($startDate)) {
            throw ValidationException::withMessages([
                'request_type' => ['Half-day leave must be on a weekday.'],
            ]);
        }

        $totalDays = $requestType === 'fullday'
            ? $leaveDurationCalculator->countWeekdays($startDate, $endDate)
            : 0.5;

        if ($totalDays < 0.5) {
            throw ValidationException::withMessages([
                'total_days' => ['Selected dates do not include any weekdays.'],
            ]);
        }

        $leaveValidationService->validateLeaveTypeDetails(
            $leaveType,
            [
                ...$validated,
                'employee_profile' => $profile,
            ],
            $startDate,
            (float) $totalDays,
        );
        $this->festivalLeaveService->validate(
            $employee,
            $leaveType,
            $validated['festival_id'] ?? null,
            $profile,
            $startDate,
            $endDate,
        );

        $leaveValidationService->validatePolicyEligibility(
            $employee,
            $leaveType,
            $policy,
            $startDate,
            (float) $totalDays,
        );

        $requiresHandover = $policy?->requiresHandoverForDuration((float) $totalDays) ?? false;
        $requiresAttachment = ($policy?->requiresAttachmentForDuration((float) $totalDays) ?? false)
            || ($policy?->requiresWeekendAttachment($startDate, $endDate) ?? false);
        $handoverPersonId = $validated['handover_person_id'] ?? null;

        if ($requiresHandover && empty($handoverPersonId)) {
            throw ValidationException::withMessages([
                'handover_person_id' => ['A handover person is required by this leave policy.'],
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
                ->whereDate('start_date', '<=', $endDate->toDateString())
                ->whereDate('end_date', '>=', $startDate->toDateString())
                ->exists();

            if ($handoverOnLeave) {
                throw ValidationException::withMessages([
                    'handover_person_id' => ['The selected handover person is also on leave during the requested dates.'],
                ]);
            }

            if ($requiresHandover && empty($validated['handover_description'])) {
                throw ValidationException::withMessages([
                    'handover_description' => ['Handover description is required.'],
                ]);
            }
        }

        if ($requiresAttachment && ! $request->hasFile('certificate')) {
            throw ValidationException::withMessages([
                'certificate' => ['A supporting document is required by this leave policy.'],
            ]);
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $validated['employee_id'],
            'leave_type_id' => $validated['leave_type_id'],
            'request_type' => $requestType,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'total_days' => $totalDays,
            'remarks' => $validated['remarks'] ?? null,
            'status' => $status,
            'created_by' => $request->user()?->id,
        ]);

        $hrName = $request->user()?->name ?? 'HR';
        $historyRemarks = $requiresApproval
            ? "Applied by HR ({$hrName}) on behalf of employee."
            : "Automatically approved because this leave type does not require approval. Applied by HR ({$hrName}) on behalf of employee.";
        if (! empty($validated['remarks'])) {
            $historyRemarks .= ' Reason: '.$validated['remarks'];
        }

        $leaveRequest->histories()->create([
            'action_type' => $status,
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

        foreach ($leaveValidationService->leaveTypeDetailValues($leaveType, $validated) as $fieldKey => $fieldValue) {
            $fieldName = match ($fieldKey) {
                'due_date' => 'Expected Due Date',
                'family_relationship' => 'Family Relationship',
                default => 'Child Birth Date',
            };
            $leaveRequest->details()->create([
                'field_name' => $fieldName,
                'field_key' => $fieldKey,
                'field_value' => $fieldValue,
            ]);
        }
        $this->festivalLeaveService->syncSelection($leaveRequest, $leaveType, $validated['festival_id'] ?? null);

        if ($status === 'approved') {
            if ($requiresApproval) {
                $leaveRequest->approvals()->create([
                    'approval_level' => 'HR Direct Approval',
                    'approver_id' => $request->user()?->id,
                    'remarks' => 'Approved directly by HR upon submission.',
                    'approved_date' => now(),
                ]);
            }

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
            'leaveType:id,leave_name,is_paid',
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

    public function edit(LeaveRequest $leave_request, LeaveTypeEligibilityService $eligibilityService): Response
    {
        $leave_request->load([
            'employee:id,user_id,emp_num,employee_code,designation,department_id,organisation_id',
            'employee.user:id,name',
            'employee.department:id,name',
            'leaveType:id,leave_name,code',
            'details',
            'files',
        ]);

        if ($leave_request->leaveType) {
            $leave_request->leaveType->setRelation(
                'policy',
                $leave_request->leaveType->policyForOrganisation($leave_request->employee?->organisation_id),
            );
        }

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
        $countryIdsByEmployee = $this->festivalLeaveService->countryIdsForProfiles($profiles);
        $employees->each(function (Employee $emp) use ($profiles, $countryIdsByEmployee) {
            $profile = $profiles->get($emp->id);
            $emp->country_ids = $countryIdsByEmployee->get($emp->id, []);
            $emp->gender = $profile?->gender;
            $emp->religion = $profile?->religion;
            $emp->marital_status = $profile?->marital_status;
            if ($profile) {
                $profile->makeVisible(['nationality', 'home_country']);
            }
            $emp->setRelation('profile', $profile);
        });

        $leaveTypes = LeaveType::query()
            ->with('policies:id,leave_type_id,organisation_id,annual_limit,requires_document_after_days,requires_attachment,requires_handover,requires_weekend_document')
            ->select('id', 'leave_name', 'code', 'is_paid', 'status', 'gender', 'religion')
            ->orderByRaw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
            ->orderBy('leave_name')
            ->get();

        $leaveTypes->each(function (LeaveType $type) use ($eligibilityService, $employees, $profiles): void {
            $type->setAttribute('eligible_employee_ids', $eligibilityService->eligibleEmployeeIds($type, $employees, $profiles));
        });

        $festivals = $this->festivalLeaveService->optionsForEmployees($employees, $profiles, $countryIdsByEmployee);

        $year = (int) Carbon::parse($leave_request->start_date)->format('Y');
        $leaveBalances = LeaveBalance::query()
            ->where('year', $year)
            ->select('id', 'employee_id', 'leave_type_id', 'allocated', 'used', 'balance')
            ->get();

        $activeLeaves = LeaveRequest::query()
            ->where('id', '!=', $leave_request->id)
            ->whereIn('status', ['applied', 'approved'])
            ->whereDate('end_date', '>=', now()->startOfYear()->toDateString())
            ->select('id', 'employee_id', 'start_date', 'end_date', 'status')
            ->get();

        $handoverDetail = $leave_request->details->firstWhere('field_key', 'handover_person_id');
        $handoverDescDetail = $leave_request->details->firstWhere('field_key', 'handover_description');
        $childBirthDateDetail = $leave_request->details->firstWhere('field_key', 'child_birth_date');

        return Inertia::render('leave-requests/edit', [
            'leaveRequest' => [
                'id' => $leave_request->id,
                'employee_id' => $leave_request->employee_id,
                'leave_type_id' => $leave_request->leave_type_id,
                'start_date' => Carbon::parse($leave_request->start_date)->format('Y-m-d'),
                'end_date' => Carbon::parse($leave_request->end_date)->format('Y-m-d'),
                'request_type' => $leave_request->request_type ?? 'fullday',
                'total_days' => $leave_request->total_days,
                'status' => $leave_request->status,
                'remarks' => $leave_request->remarks,
                'employee' => $leave_request->employee,
                'leave_type' => $leave_request->leaveType,
                'employee_profile' => $leave_request->employee?->profile(),
                'handover_person_id' => $handoverDetail?->field_value,
                'handover_description' => $handoverDescDetail?->field_value,
                'child_birth_date' => $childBirthDateDetail?->field_value,
                'due_date' => $leave_request->details->firstWhere('field_key', 'due_date')?->field_value,
                'family_relationship' => $leave_request->details->firstWhere('field_key', 'family_relationship')?->field_value,
                'festival_id' => $leave_request->details->firstWhere('field_key', 'festival_id')?->field_value,
                'files' => $leave_request->files,
            ],
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
            'leaveBalances' => $leaveBalances,
            'activeLeaves' => $activeLeaves,
            'festivals' => $festivals,
            'compassionateRelationships' => config('params.leave_type.compassionate_relationships'),
        ]);
    }

    public function update(
        Request $request,
        LeaveRequest $leave_request,
        LeaveTypeEligibilityService $eligibilityService,
        LeaveValidationService $leaveValidationService,
        LeaveDurationCalculator $leaveDurationCalculator,
    ) {
        // If full edit form submission (has start_date)
        if ($request->has('start_date')) {
            $validated = $request->validate([
                'employee_id' => 'nullable|exists:salescrm.employees,id',
                'leave_type_id' => 'required|exists:leave_types,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'request_type' => 'nullable|in:fullday,firsthalf,secondhalf',
                'total_days' => 'nullable|numeric|min:0',
                'status' => 'required|in:applied,approved,rejected,cancelled',
                'remarks' => 'nullable|string',
                'certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
                'handover_person_id' => 'nullable|exists:salescrm.employees,id',
                'handover_description' => 'nullable|string',
                'due_date' => 'nullable|date',
                'child_birth_date' => 'nullable|date',
                'family_relationship' => 'nullable|string',
                'festival_id' => 'nullable|integer|exists:festivals,id',
            ]);

            if ((int) $validated['leave_type_id'] !== (int) $leave_request->leave_type_id) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ['Leave type cannot be changed after the request is created.'],
                ]);
            }

            $employeeId = $validated['employee_id'] ?? $leave_request->employee_id;
            $employee = Employee::query()->findOrFail($employeeId);
            $leaveType = LeaveType::query()->findOrFail($leave_request->leave_type_id);
            $policy = $leaveType->policyForOrganisation($employee->organisation_id);

            $profile = EmployeeProfile::query()->where('employee_id', $employee->id)->first();
            $rawEmployeeGender = $profile?->gender ?? $employee->gender ?? null;

            $startDate = Carbon::parse($validated['start_date'])->startOfDay();
            $endDate = Carbon::parse($validated['end_date'])->endOfDay();
            $requestType = $validated['request_type'] ?? 'fullday';

            if ($requestType !== 'fullday' && ! $startDate->isSameDay($endDate)) {
                throw ValidationException::withMessages([
                    'request_type' => ['Half-day leave must start and end on the same date.'],
                ]);
            }

            if ($requestType !== 'fullday' && ! $leaveDurationCalculator->isWeekday($startDate)) {
                throw ValidationException::withMessages([
                    'request_type' => ['Half-day leave must be on a weekday.'],
                ]);
            }

            $totalDays = $requestType === 'fullday'
                ? $leaveDurationCalculator->countWeekdays($startDate, $endDate)
                : 0.5;

            if ($totalDays < 0.5) {
                throw ValidationException::withMessages([
                    'total_days' => ['Selected dates do not include any weekdays.'],
                ]);
            }

            $isActiveRequest = in_array($validated['status'], ['applied', 'approved'], true);
            if (! array_key_exists('family_relationship', $validated)) {
                $validated['family_relationship'] = $leave_request->details->firstWhere('field_key', 'family_relationship')?->field_value;
            }
            $festivalId = array_key_exists('festival_id', $validated)
                ? $validated['festival_id']
                : $leave_request->details->firstWhere('field_key', 'festival_id')?->field_value;

            if ($isActiveRequest) {
                $eligibilityService->assertEligible($leaveType, $rawEmployeeGender, $profile?->religion, $profile?->marital_status);
                $leaveValidationService->validateLeaveTypeDetails(
                    $leaveType,
                    [
                        ...$validated,
                        'employee_profile' => $profile,
                    ],
                    $startDate,
                    (float) $totalDays,
                );
                $this->festivalLeaveService->validate(
                    $employee,
                    $leaveType,
                    $festivalId,
                    $profile,
                    $startDate,
                    $endDate,
                    (int) $leave_request->id,
                );

                $leaveValidationService->validatePolicyEligibility(
                    $employee,
                    $leaveType,
                    $policy,
                    $startDate,
                    (float) $totalDays,
                    (int) $leave_request->id,
                );
            }

            $requiresHandover = $isActiveRequest
                && ($policy?->requiresHandoverForDuration((float) $totalDays) ?? false);
            $requiresAttachment = $isActiveRequest
                && (($policy?->requiresAttachmentForDuration((float) $totalDays) ?? false)
                    || ($policy?->requiresWeekendAttachment($startDate, $endDate) ?? false));

            // Check overlapping active/approved leave for the employee (excluding this leave request!)
            if (in_array($validated['status'], ['applied', 'approved'])) {
                $overlapping = LeaveRequest::where('employee_id', $employee->id)
                    ->where('id', '!=', $leave_request->id)
                    ->whereIn('status', ['applied', 'approved'])
                    ->whereDate('start_date', '<=', $endDate->toDateString())
                    ->whereDate('end_date', '>=', $startDate->toDateString())
                    ->exists();

                if ($overlapping) {
                    throw ValidationException::withMessages([
                        'start_date' => ['The employee already has an active or approved leave application overlapping with the selected dates.'],
                    ]);
                }
            }

            $handoverPersonId = $validated['handover_person_id'] ?? null;

            if ($requiresHandover && empty($handoverPersonId)) {
                throw ValidationException::withMessages([
                    'handover_person_id' => ['A handover person is required by this leave policy.'],
                ]);
            }

            if (! empty($handoverPersonId)) {
                if ((int) $handoverPersonId === (int) $employee->id) {
                    throw ValidationException::withMessages([
                        'handover_person_id' => ['You cannot select the employee themselves as the handover person.'],
                    ]);
                }

                $handoverOnLeave = LeaveRequest::where('employee_id', $handoverPersonId)
                    ->where('id', '!=', $leave_request->id)
                    ->whereIn('status', ['applied', 'approved'])
                    ->whereDate('start_date', '<=', $endDate->toDateString())
                    ->whereDate('end_date', '>=', $startDate->toDateString())
                    ->exists();

                if ($handoverOnLeave) {
                    throw ValidationException::withMessages([
                        'handover_person_id' => ['The selected handover person is also on leave during the requested dates.'],
                    ]);
                }

                if ($requiresHandover && empty($validated['handover_description'])) {
                    throw ValidationException::withMessages([
                        'handover_description' => ['Handover description is required.'],
                    ]);
                }
            }

            if (
                $requiresAttachment
                && ! $request->hasFile('certificate')
                && ! $leave_request->files()->exists()
            ) {
                throw ValidationException::withMessages([
                    'certificate' => ['A supporting document is required by this leave policy.'],
                ]);
            }

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
                'request_type' => $requestType,
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

            $detailValues = $leaveValidationService->leaveTypeDetailValues($leaveType, $validated);
            foreach (['due_date' => 'Expected Due Date', 'child_birth_date' => 'Child Birth Date', 'family_relationship' => 'Family Relationship'] as $fieldKey => $fieldName) {
                if (array_key_exists($fieldKey, $detailValues)) {
                    $leave_request->details()->updateOrCreate(
                        ['field_key' => $fieldKey],
                        ['field_name' => $fieldName, 'field_value' => $detailValues[$fieldKey]],
                    );
                } elseif (in_array($leaveType->code, ['MATERNITY', 'PARENTAL', 'PATERNITY', 'COMPASSIONATE'], true)) {
                    $leave_request->details()->where('field_key', $fieldKey)->delete();
                }
            }
            $this->festivalLeaveService->syncSelection($leave_request, $leaveType, $festivalId);

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

            if ($leaveType->requires_approval && in_array($validated['status'], ['approved', 'rejected'])) {
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

        $leaveType = $leave_request->leaveType;
        if (
            in_array($validated['status'], ['applied', 'approved'], true)
            && $leaveType instanceof LeaveType
        ) {
            $employee = Employee::query()->findOrFail($leave_request->employee_id);
            $profile = $employee->profile();
            $policy = $leaveType->policyForOrganisation($employee->organisation_id);
            $startDate = Carbon::parse($leave_request->start_date)->startOfDay();
            $endDate = Carbon::parse($leave_request->end_date)->endOfDay();
            $totalDays = (float) $leave_request->total_days;
            $eligibilityService->assertEligible($leaveType, $profile?->gender ?? $employee->gender, $profile?->religion, $profile?->marital_status);
            $leaveValidationService->validatePolicyEligibility($employee, $leaveType, $policy, $startDate, $totalDays, (int) $leave_request->id);
            $leaveValidationService->validateLeaveTypeDetails(
                $leaveType,
                [...$leave_request->details->pluck('field_value', 'field_key')->all(), 'employee_profile' => $profile],
                $startDate,
                $totalDays,
            );
            $this->festivalLeaveService->validate(
                $employee,
                $leaveType,
                $leave_request->details->firstWhere('field_key', 'festival_id')?->field_value,
                $profile,
                $startDate,
                $endDate,
                (int) $leave_request->id,
            );

            if (($policy?->requiresAttachmentForDuration($totalDays)
                    || $policy?->requiresWeekendAttachment($startDate, $endDate))
                && ! $leave_request->files()->exists()) {
                throw ValidationException::withMessages([
                    'certificate' => ['A supporting document is required by this leave policy.'],
                ]);
            }

            if ($policy?->requiresHandoverForDuration($totalDays)
                && (! $leave_request->details->firstWhere('field_key', 'handover_person_id')?->field_value
                    || ! $leave_request->details->firstWhere('field_key', 'handover_description')?->field_value)) {
                throw ValidationException::withMessages([
                    'handover_person_id' => ['A handover person and description are required by this leave policy.'],
                ]);
            }
        }

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

        $leave_request->loadMissing('leaveType');
        if ($leave_request->leaveType?->requires_approval && in_array($validated['status'], ['approved', 'rejected'])) {
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
