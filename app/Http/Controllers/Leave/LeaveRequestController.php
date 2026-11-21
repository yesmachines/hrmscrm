<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveApplicationRequest;
use App\Http\Requests\Leave\PreviewLeaveTemplateRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveTemplate;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Services\FestivalLeaveService;
use App\Services\LeavePdfGenerationService;
use App\Services\LeaveTypeEligibilityService;
use App\Services\LeaveTypeRules;
use App\Services\LeaveValidationService;
use App\Services\WorkFromHomeService;
use App\Support\SalesCrmRoles;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly FestivalLeaveService $festivalLeaveService,
        private readonly LeaveTypeRules $leaveTypeRules,
        private readonly WorkFromHomeService $workFromHomeService,
        private readonly LeavePdfGenerationService $leavePdfGenerationService,
    ) {}

    public function index(Request $request): Response
    {
        $canManageWorkFromHome = $this->canManageWorkFromHome($request);
        $filters = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:salescrm.employees,id'],
            'status' => ['nullable', Rule::in(config('params.leave_request.statuses'))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $requests = LeaveRequest::with([
            'employee:id,user_id,employee_code,image_url',
            'employee.user:id,name',
            'leaveType:id,leave_name,is_paid,code',
        ])
            ->when(! $canManageWorkFromHome, fn ($query) => $query->whereHas(
                'leaveType',
                fn ($leaveTypeQuery) => $leaveTypeQuery->where('code', '!=', WorkFromHomeService::LEAVE_TYPE_CODE),
            ))
            ->when(isset($filters['employee_id']), fn ($query) => $query->where('employee_id', $filters['employee_id']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['start_date']), fn ($query) => $query->whereDate('end_date', '>=', $filters['start_date']))
            ->when(isset($filters['end_date']), fn ($query) => $query->whereDate('start_date', '<=', $filters['end_date']))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $employees = Employee::query()
            ->with('user:id,name')
            ->select('id', 'user_id', 'employee_code')
            ->where('status', 1)
            ->orderBy('id')
            ->get();

        return Inertia::render('leave-requests/index', [
            'requests' => $requests,
            'employees' => $employees,
            'filters' => [
                'employee_id' => $filters['employee_id'] ?? '',
                'status' => $filters['status'] ?? '',
                'start_date' => $filters['start_date'] ?? '',
                'end_date' => $filters['end_date'] ?? '',
            ],
        ]);
    }

    public function create(Request $request, LeaveTypeEligibilityService $eligibilityService): Response
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
            ->when(! $this->canManageWorkFromHome($request), fn ($query) => $query->where('code', '!=', WorkFromHomeService::LEAVE_TYPE_CODE))
            ->select('id', 'leave_name', 'code', 'is_paid', 'requires_approval', 'status', 'gender', 'religion')
            ->orderByRaw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
            ->orderBy('leave_name')
            ->get();

        $leaveTypes->each(function (LeaveType $type) use ($eligibilityService, $employees, $profiles): void {
            $type->setAttribute('eligible_employee_ids', $eligibilityService->eligibleEmployeeIds($type, $employees, $profiles));
            $this->leaveTypeRules->prepareForDisplay($type);
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
            'leaveTemplates' => $this->activeTemplateOptions(),
        ]);
    }

    public function templatePreview(PreviewLeaveTemplateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $employee = Employee::query()->findOrFail($validated['employee_id']);
        $leaveType = LeaveType::query()->findOrFail($validated['leave_type_id']);
        $template = $this->leavePdfGenerationService->resolveTemplate($leaveType, $employee->organisation_id);

        if ($template === null) {
            return response()->json([
                'message' => 'No active template is configured for this leave type and organisation.',
            ], 404);
        }

        $values = $validated['values'] ?? [];
        $values['leave_type_name'] = $leaveType->leave_name;

        return response()->json([
            'template_name' => $template->template_name,
            'html' => $this->leavePdfGenerationService->render($template, $employee, $values),
        ]);
    }

    public function validateForm(
        LeaveApplicationRequest $request,
        LeaveValidationService $leaveValidationService,
    ): JsonResponse {
        $validated = $request->validated();

        $employee = Employee::query()->findOrFail($validated['employee_id']);
        $existing = empty($validated['ignore_leave_request_id'])
            ? null
            : LeaveRequest::query()->with('details')->whereKey($validated['ignore_leave_request_id'])->firstOrFail();
        if ($existing !== null) {
            $validated = $leaveValidationService->dataForUpdate($existing, $validated);
        }

        try {
            $validation = $leaveValidationService->validateApplication(
                $employee,
                $validated,
                $request->hasFile('certificate') || ($existing?->files()->exists() ?? false),
                $validated['ignore_leave_request_id'] ?? null,
                in_array($validated['status'] ?? 'applied', config('params.leave_request.active_statuses'), true),
                $existing === null
                    || Carbon::parse($validated['start_date'])->toDateString() !== Carbon::parse($existing->start_date)->toDateString()
                    || Carbon::parse($validated['end_date'])->toDateString() !== Carbon::parse($existing->end_date)->toDateString(),
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'valid' => false,
                'errors' => $exception->errors(),
            ]);
        }
        $this->assertWorkFromHomeAccess($request, $validation['leave_type']);
        $leaveType = $validation['leave_type'];

        return response()->json([
            'valid' => true,
            'total_days' => $validation['total_days'],
            'annual_limit' => $this->leaveTypeRules->visibleAnnualLimit($leaveType, $validation['policy']),
            'attachment_required' => $validation['attachment_required'],
            'handover_required' => $leaveValidationService->requiresHandover($leaveType, $validation['policy'], $validation['total_days']),
            'application_requirements' => $this->leaveTypeRules->applicationRequirements($leaveType),
            'required_fields' => $leaveValidationService->detailFields($leaveType),
            'field_definitions' => $this->leaveTypeRules->fieldDefinitions($leaveType),
        ]);
    }

    public function store(
        LeaveApplicationRequest $request,
        LeaveValidationService $leaveValidationService,
    ): RedirectResponse {
        $validated = $request->validated();

        $employee = Employee::query()->findOrFail($validated['employee_id']);
        $validation = $leaveValidationService->validateApplication($employee, $validated, $request->hasFile('certificate'));
        $leaveType = $validation['leave_type'];
        $this->assertWorkFromHomeAccess($request, $leaveType);
        $requiresApproval = (bool) $leaveType->requires_approval;
        $status = $requiresApproval ? $validated['status'] : 'approved';
        $requestType = $validation['request_type'];
        $totalDays = $validation['total_days'];
        $handoverPersonId = $validated['handover_person_id'] ?? null;

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

        $leaveValidationService->syncLeaveTypeDetails($leaveRequest, $leaveType, $validated);
        $workFromHomeSchedule = $validation['wfh_schedule'];
        if ($workFromHomeSchedule !== null) {
            $this->workFromHomeService->linkRequestToSchedule($leaveRequest, $workFromHomeSchedule);
            $this->workFromHomeService->applyRequestBalanceTransition(
                $leaveRequest,
                '',
                0,
                null,
                $status,
                (float) $totalDays,
                (int) $workFromHomeSchedule->id,
            );
        }

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

            if ($workFromHomeSchedule === null && $balance && $this->leaveTypeRules->allowsBalance($leaveType, null)) {
                $balance->used += $totalDays;
                $balance->balance = max(0, $balance->balance - $totalDays);
                $balance->save();
            }

            $this->leavePdfGenerationService->generateIfAvailable($leaveRequest, $request->user()?->id);
        }

        return redirect()->route('leave-requests.index')
            ->with('success', 'Leave request submitted successfully on behalf of employee.');
    }

    public function show(Request $request, LeaveRequest $leave_request): Response
    {
        $leave_request->loadMissing('leaveType');
        $this->assertWorkFromHomeAccess($request, $leave_request->leaveType);
        $leave_request->load([
            'employee:id,user_id,employee_code,emp_num,phone,designation,department_id,organisation_id,image_url',
            'employee.user:id,name,email',
            'employee.department:id,name',
            'leaveType:id,leave_name,code,is_paid',
            'files',
            'details',
            'histories' => function ($query) {
                $query->orderBy('action_on', 'desc');
            },
            'histories.doneBy:id,name',
        ]);
        $generatedPdf = $leave_request->generatedFiles()->orderByDesc('id')->first();
        if ($generatedPdf !== null && ! Storage::disk('public')->exists($generatedPdf->file_path)) {
            $generatedPdf = null;
        }
        $templateAvailable = $leave_request->leaveType?->resolveTemplate($leave_request->employee?->organisation_id) !== null;

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
            'generatedPdfUrl' => $generatedPdf
                ? route('leave-requests.pdf', $leave_request)
                : null,
            'pdfNotice' => $leave_request->status === 'approved' && $generatedPdf === null
                ? ($templateAvailable
                    ? 'This approved request does not have a generated PDF yet. Edit and save it to generate one.'
                    : 'No active leave template is configured for this leave type.')
                : null,
            'fieldDefinitions' => $leave_request->leaveType
                ? $this->leaveTypeRules->fieldDefinitions($leave_request->leaveType)
                : [],
        ]);
    }

    public function downloadPdf(LeaveRequest $leave_request): StreamedResponse
    {
        abort_unless($leave_request->status === 'approved', 404);

        $generatedPdf = $leave_request->generatedFiles()->orderByDesc('id')->first();
        abort_unless(
            $generatedPdf !== null && Storage::disk('public')->exists($generatedPdf->file_path),
            404,
        );

        return Storage::disk('public')->download(
            $generatedPdf->file_path,
            "leave-request-{$leave_request->id}.pdf",
        );
    }

    public function edit(
        Request $request,
        LeaveRequest $leave_request,
        LeaveTypeEligibilityService $eligibilityService,
    ): Response {
        $leave_request->loadMissing('leaveType');
        $this->assertWorkFromHomeAccess($request, $leave_request->leaveType);
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
            $this->leaveTypeRules->prepareForDisplay($leave_request->leaveType);
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
            ->when(! $this->canManageWorkFromHome($request), fn ($query) => $query->where('code', '!=', WorkFromHomeService::LEAVE_TYPE_CODE))
            ->select('id', 'leave_name', 'code', 'is_paid', 'status', 'gender', 'religion')
            ->orderByRaw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
            ->orderBy('leave_name')
            ->get();

        $leaveTypes->each(function (LeaveType $type) use ($eligibilityService, $employees, $profiles): void {
            $type->setAttribute('eligible_employee_ids', $eligibilityService->eligibleEmployeeIds($type, $employees, $profiles));
            $this->leaveTypeRules->prepareForDisplay($type);
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
        $travelingOutsideCountryDetail = $leave_request->details->firstWhere('field_key', 'traveling_outside_country');

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
                'local_contact_no' => $leave_request->details->firstWhere('field_key', 'local_contact_no')?->field_value,
                'traveling_outside_country' => $travelingOutsideCountryDetail
                    ? (filter_var($travelingOutsideCountryDetail->field_value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0')
                    : '',
                'destination' => $leave_request->details->firstWhere('field_key', 'destination')?->field_value,
                'travel_contact_no' => $leave_request->details->firstWhere('field_key', 'travel_contact_no')?->field_value,
                'ticket_status' => $leave_request->details->firstWhere('field_key', 'ticket_status')?->field_value,
                'files' => $leave_request->files,
            ],
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
            'leaveBalances' => $leaveBalances,
            'activeLeaves' => $activeLeaves,
            'festivals' => $festivals,
            'compassionateRelationships' => config('params.leave_type.compassionate_relationships'),
            'leaveTemplates' => $this->activeTemplateOptions(),
        ]);
    }

    public function update(
        LeaveApplicationRequest $request,
        LeaveRequest $leave_request,
        LeaveValidationService $leaveValidationService,
    ): RedirectResponse {
        $leave_request->loadMissing('leaveType');
        $this->assertWorkFromHomeAccess($request, $leave_request->leaveType);

        // If full edit form submission (has start_date)
        if ($request->has('start_date')) {
            $validated = $leaveValidationService->dataForUpdate($leave_request, $request->validated());

            $employeeId = $validated['employee_id'] ?? $leave_request->employee_id;
            $employee = Employee::query()->findOrFail($employeeId);
            $validation = $leaveValidationService->validateApplication(
                $employee,
                $validated,
                $request->hasFile('certificate') || $leave_request->files()->exists(),
                (int) $leave_request->id,
                in_array($validated['status'], config('params.leave_request.active_statuses'), true),
                $validated['start_date'] !== Carbon::parse($leave_request->start_date)->toDateString()
                    || $validated['end_date'] !== Carbon::parse($leave_request->end_date)->toDateString(),
            );
            $leaveType = $validation['leave_type'];
            $wasWorkFromHome = $this->workFromHomeService->isWorkFromHomeType($leave_request->leaveType);
            $isWorkFromHome = $this->workFromHomeService->isWorkFromHomeType($leaveType);
            if ($wasWorkFromHome !== $isWorkFromHome
                || ($isWorkFromHome && (int) $employeeId !== (int) $leave_request->employee_id)) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ['A WFH request cannot be changed to another leave type or employee.'],
                ]);
            }
            $requestType = $validation['request_type'];
            $totalDays = $validation['total_days'];
            $handoverPersonId = $validated['handover_person_id'] ?? null;

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

            $oldScheduleId = $wasWorkFromHome
                ? $this->workFromHomeService->scheduleIdForRequest($leave_request)
                : null;
            $workFromHomeSchedule = $validation['wfh_schedule'];
            if ($wasWorkFromHome) {
                if ($workFromHomeSchedule !== null) {
                    $this->workFromHomeService->linkRequestToSchedule($leave_request, $workFromHomeSchedule);
                }
                $this->workFromHomeService->applyRequestBalanceTransition(
                    $leave_request,
                    $oldState['status'],
                    $oldState['total_days'],
                    $oldScheduleId,
                    $newState['status'],
                    $newState['total_days'],
                    $workFromHomeSchedule?->id,
                );
            } else {
                $this->adjustLeaveBalances($leave_request, $oldState, $newState);
            }

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

            $leaveValidationService->syncLeaveTypeDetails($leave_request, $leaveType, $validated);

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

            if ($newState['status'] === 'approved') {
                $this->leavePdfGenerationService->generateIfAvailable($leave_request, $request->user()?->id);
            }

            return redirect()->route('leave-requests.index')
                ->with('success', 'Leave request updated successfully. Any affected leave balances were updated.');
        }

        // Quick status update (Approve, Reject, Cancel buttons)
        $validated = $request->validated();

        $leaveType = $leave_request->leaveType;
        $validation = null;
        if (in_array($validated['status'], config('params.leave_request.active_statuses'), true)) {
            $employee = Employee::query()->findOrFail($leave_request->employee_id);
            $validation = $leaveValidationService->validateApplication(
                $employee,
                $leaveValidationService->dataForUpdate($leave_request, []),
                $leave_request->files()->exists(),
                (int) $leave_request->id,
                true,
                false,
            );
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

        if ($this->workFromHomeService->isWorkFromHomeType($leaveType)) {
            $oldScheduleId = $this->workFromHomeService->scheduleIdForRequest($leave_request);
            $workFromHomeSchedule = $validation['wfh_schedule'] ?? null;
            if ($workFromHomeSchedule !== null) {
                $this->workFromHomeService->linkRequestToSchedule($leave_request, $workFromHomeSchedule);
            }
            $this->workFromHomeService->applyRequestBalanceTransition(
                $leave_request,
                $oldState['status'],
                $oldState['total_days'],
                $oldScheduleId,
                $newState['status'],
                $newState['total_days'],
                $workFromHomeSchedule?->id,
            );
        } else {
            $this->adjustLeaveBalances($leave_request, $oldState, $newState);
        }

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

        if ($oldState['status'] !== 'approved' && $newState['status'] === 'approved') {
            $this->leavePdfGenerationService->generateIfAvailable($leave_request, $request->user()?->id);
        }

        return back()->with('success', 'Leave request status updated successfully.');
    }

    public function cancel(Request $request, LeaveRequest $leave_request): RedirectResponse
    {
        $leave_request->loadMissing('leaveType');
        $this->assertWorkFromHomeAccess($request, $leave_request->leaveType);

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

        if ($this->workFromHomeService->isWorkFromHomeType($leave_request->leaveType)) {
            $this->workFromHomeService->applyRequestBalanceTransition(
                $leave_request,
                $oldState['status'],
                $oldState['total_days'],
                $this->workFromHomeService->scheduleIdForRequest($leave_request),
                $newState['status'],
                $newState['total_days'],
                null,
            );
        } else {
            $this->adjustLeaveBalances($leave_request, $oldState, $newState);
        }

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

        $leaveType = LeaveType::query()->find($leaveTypeId);
        if ($leaveType !== null && ! $this->leaveTypeRules->allowsBalance($leaveType, null)) {
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

        $leaveType = LeaveType::query()->find($leaveTypeId);
        if ($leaveType !== null && ! $this->leaveTypeRules->allowsBalance($leaveType, null)) {
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

    /**
     * @return list<array{leave_type_id: int, organisation_id: int|null}>
     */
    private function activeTemplateOptions(): array
    {
        return LeaveTemplate::query()
            ->active()
            ->get(['leave_type_id', 'organisation_id'])
            ->map(fn (LeaveTemplate $template): array => [
                'leave_type_id' => $template->leave_type_id,
                'organisation_id' => $template->organisation_id,
            ])
            ->all();
    }

    private function canManageWorkFromHome(Request $request): bool
    {
        return SalesCrmRoles::userHasAnyRole((int) $request->user()?->id, ['admin', 'hr']);
    }

    private function assertWorkFromHomeAccess(Request $request, ?LeaveType $leaveType): void
    {
        if (
            $leaveType !== null
            && $this->workFromHomeService->isWorkFromHomeType($leaveType)
            && ! $this->canManageWorkFromHome($request)
        ) {
            abort(403);
        }
    }
}
