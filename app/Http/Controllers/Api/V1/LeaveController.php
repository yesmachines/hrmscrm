<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestFile;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Services\LeaveTypeEligibilityService;
use App\Services\LeaveValidationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class LeaveController extends Controller
{
    /**
     * Get the metadata for the leave application screen.
     */
    #[OA\Get(
        path: '/api/v1/leaves/meta',
        summary: 'Get leave metadata (types, balances, policies)',
        security: [['sanctum' => []]],
        tags: ['Leave Management'],
        responses: [
            new OA\Response(response: 200, description: 'Leave metadata retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
    public function meta(Request $request, LeaveTypeEligibilityService $eligibilityService)
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        // The user ID maps to an Employee in the salescrm DB
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        // Fetch active leave types filtered by employee gender
        $profile = EmployeeProfile::where('employee_id', $employee->id)->first();
        $rawEmployeeGender = $profile?->gender ?? $employee->gender ?? null;
        $rawEmployeeReligion = $profile?->religion;
        $leaveTypes = $eligibilityService->filterEligible(
            LeaveType::query()
                ->with([
                    'policies' => fn ($query) => $query
                        ->where('organisation_id', $employee->organisation_id),
                ])
                ->where('status', 1)
                ->get(),
            $rawEmployeeGender,
            $rawEmployeeReligion,
        );

        $typesData = $leaveTypes->map(function ($type) use ($employee) {
            $policy = $type->policyForOrganisation($employee->organisation_id);

            // Get current year balance
            $balance = LeaveBalance::where('leave_type_id', $type->id)
                ->where('employee_id', $employee->id)
                ->where('year', date('Y'))
                ->first();

            return [
                'id' => $type->id,
                'leave_name' => $type->leave_name,
                'code' => $type->code,
                'is_paid' => (bool) $type->is_paid,
                'requires_approval' => (bool) $type->requires_approval,
                'annual_limit' => $policy?->annual_limit,
                'gender' => $type->gender,
                'religion' => $type->religion,
                'allow_once' => (bool) ($policy?->allow_once ?? false),
                'allow_balance' => (bool) ($policy?->allow_balance ?? true),
                'balance' => [
                    'total' => $balance ? (float) $balance->allocated : 0,
                    'used' => $balance ? (float) $balance->used : 0,
                    'balance' => $balance ? (float) $balance->balance : 0,
                    'pending' => $balance ? (float) $balance->pending : 0,
                ],
                'policy' => $policy ? [
                    'id' => $policy->id,
                    'requires_document_after_days' => $policy->requires_document_after_days,
                    'requires_weekend_document' => (bool) $policy->requires_weekend_document,
                    'requires_attachment' => (bool) $policy->requires_attachment,
                    'requires_handover' => (bool) $policy->requires_handover,
                    'encashment' => (bool) $policy->encashment,
                    'probation_applicable' => (bool) $policy->probation_applicable,
                    'minimum_service_months' => $policy->minimum_service_months,
                    'remarks' => $policy->remarks,
                ] : null,
            ];
        });

        return $this->successResponse([
            'employee' => [
                'id' => $employee->id,
                'name' => $user->name,
                'image_url' => $employee->image_url,
            ],
            'leave_types' => $typesData,
            'types' => $typesData,
        ]);
    }

    /**
     * Get eligible festivals and holidays for the employee, optionally filtered by month and year.
     */
    public function festivals(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        [$month, $year] = $this->parseMonthAndYear(
            $request->query('month') ?? $request->input('month'),
            $request->query('year') ?? $request->input('year')
        );

        $data = $this->getFestivalsAndHolidaysForEmployee($employee, $month, $year);

        // Fetch leaves applied by this employee for this month/year (excluding rejected leaves)
        $leavesQuery = LeaveRequest::with('leaveType:id,leave_name,code,is_paid')
            ->where('employee_id', $employee->id)
            ->where('status', '!=', 'rejected');

        if ($month && $year) {
            $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
            $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

            $leavesQuery->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->where(function ($sub) use ($startOfMonth, $endOfMonth) {
                    $sub->whereDate('start_date', '<=', $endOfMonth)
                        ->where(function ($inner) use ($startOfMonth) {
                            $inner->whereDate('end_date', '>=', $startOfMonth)
                                ->orWhereNull('end_date');
                        });
                });
            });
        } elseif ($month) {
            $leavesQuery->where(function ($q) use ($month) {
                $q->whereMonth('start_date', $month)
                    ->orWhereMonth('end_date', $month);
            });
        } elseif ($year) {
            $leavesQuery->where(function ($q) use ($year) {
                $q->whereYear('start_date', $year)
                    ->orWhereYear('end_date', $year);
            });
        }

        $appliedLeaves = $leavesQuery
            ->orderBy('start_date', 'asc')
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'id' => $leave->id,
                'leave_type_id' => $leave->leave_type_id,
                'leave_type_name' => $leave->leaveType?->leave_name,
                'leave_type_code' => $leave->leaveType?->code,
                'leave_type' => $leave->leaveType ? [
                    'id' => $leave->leaveType->id,
                    'leave_name' => $leave->leaveType->leave_name,
                    'code' => $leave->leaveType->code,
                    'is_paid' => (bool) $leave->leaveType->is_paid,
                ] : null,
                'start_date' => $leave->start_date ? Carbon::parse($leave->start_date)->format('Y-m-d') : null,
                'end_date' => $leave->end_date ? Carbon::parse($leave->end_date)->format('Y-m-d') : null,
                'total_days' => (float) $leave->total_days,
                'status' => $leave->status,
                'remarks' => $leave->remarks,
                'created_at' => $leave->created_at?->format('Y-m-d H:i:s'),
            ]);

        $data['month'] = $month;
        $data['year'] = $year;
        $data['leaves'] = $appliedLeaves;

        return $this->successResponse($data);
    }

    /**
     * Submit a new leave request.
     */
    #[OA\Post(
        path: '/api/v1/leaves',
        summary: 'Submit a new leave request',
        security: [['sanctum' => []]],
        tags: ['Leave Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['leave_type_id', 'start_date', 'end_date'],
                    properties: [
                        new OA\Property(property: 'leave_type_id', type: 'integer'),
                        new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                        new OA\Property(property: 'end_date', type: 'string', format: 'date'),
                        new OA\Property(property: 'request_type', type: 'string', enum: ['fullday', 'firsthalf', 'secondhalf']),
                        new OA\Property(property: 'reason', type: 'string'),
                        new OA\Property(property: 'handover_person_id', type: 'integer'),
                        new OA\Property(property: 'handover_description', type: 'string'),
                        new OA\Property(property: 'emergency_contact_no', type: 'string', description: 'Digits only, with an optional leading +.'),
                        new OA\Property(property: 'travel_contact_no', type: 'string', description: 'Digits only, with an optional leading +.'),
                        new OA\Property(property: 'files', type: 'array', items: new OA\Items(type: 'string', format: 'binary')),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Leave request submitted successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request, LeaveValidationService $validationService)
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $request->validate([
            'emergency_contact_no' => ['nullable', 'string', 'regex:/^\+?[0-9]+$/'],
            'travel_contact_no' => ['nullable', 'string', 'regex:/^\+?[0-9]+$/'],
        ]);

        try {
            $validation = $validationService->validateApplication(
                $employee,
                $request->all(),
                $request->hasFile('certificate')
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        }

        $leaveType = $validation['leave_type'];
        $policy = $validation['policy'];
        $startDate = $validation['start_date'];
        $endDate = $validation['end_date'];
        $requestType = $validation['request_type'];
        $totalDays = $validation['total_days'];
        $remarks = $validation['remarks'];
        $requiresApproval = (bool) $leaveType->requires_approval;
        $initialStatus = $requiresApproval ? 'applied' : 'approved';

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'request_type' => $requestType,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_days' => $totalDays,
            'remarks' => $remarks,
            'status' => $initialStatus,
            'created_by' => $user->id,
        ]);

        $leaveRequest->histories()->create([
            'action_type' => $initialStatus,
            'remarks' => $requiresApproval
                ? 'Leave application submitted via API.'
                : 'Automatically approved because this leave type does not require approval.',
            'done_by' => $user->id,
            'action_on' => now(),
        ]);

        if ($request->filled('festival_id')) {
            $leaveRequest->details()->create([
                'field_name' => 'Festival ID',
                'field_key' => 'festival_id',
                'field_value' => (string) $request->input('festival_id'),
            ]);
        }

        if ($request->hasFile('certificate')) {
            $path = $request->file('certificate')->store('leave-certificates', 'public');

            LeaveRequestFile::create([
                'leave_request_id' => $leaveRequest->id,
                'file_path' => $path,
                'original_name' => $request->file('certificate')->getClientOriginalName(),
                'mime_type' => $request->file('certificate')->getMimeType(),
                'size' => $request->file('certificate')->getSize(),
                'uploaded_by' => $user->id,
                'uploaded_date' => now(),
            ]);
        }

        // Store signature oath if uploaded as image file
        if ($request->hasFile('signature')) {
            $request->validate([
                'signature' => 'file|image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            $signaturePath = $request->file('signature')->store('leave-signatures', 'public');

            $leaveRequest->details()->create([
                'field_name' => 'Signature',
                'field_key' => 'signature_path',
                'field_value' => $signaturePath,
            ]);
        }

        // Store explicit declaration text or status if provided
        if ($request->filled('declaration')) {
            $leaveRequest->details()->create([
                'field_name' => 'Declaration',
                'field_key' => 'declaration_text',
                'field_value' => (string) $request->input('declaration'),
            ]);
        }

        // Store handover details if required
        if ($policy?->requiresHandoverForDuration($totalDays)) {
            $details = [
                ['field_name' => 'Handover Person ID', 'field_key' => 'handover_person_id', 'field_value' => (string) $request->input('handover_person_id')],
                ['field_name' => 'Handover Description', 'field_key' => 'handover_description', 'field_value' => (string) $request->input('handover_description')],
            ];

            if ($request->filled('emergency_contact_no')) {
                $details[] = ['field_name' => 'Emergency Contact No', 'field_key' => 'emergency_contact_no', 'field_value' => (string) $request->input('emergency_contact_no')];
            }

            if (filter_var($request->input('traveling_outside_country'), FILTER_VALIDATE_BOOLEAN)) {
                $details[] = ['field_name' => 'Traveling Outside Country', 'field_key' => 'traveling_outside_country', 'field_value' => 'Yes'];
                $details[] = ['field_name' => 'Destination', 'field_key' => 'destination', 'field_value' => (string) $request->input('destination')];
                $details[] = ['field_name' => 'Travel Contact No', 'field_key' => 'travel_contact_no', 'field_value' => (string) $request->input('travel_contact_no')];
            } else {
                $details[] = ['field_name' => 'Declaration Signed', 'field_key' => 'declaration_signed', 'field_value' => 'Yes'];
            }

            foreach ($details as $detail) {
                $leaveRequest->details()->create($detail);
            }
        }

        // Store special details
        if ($leaveType->code === 'MATERNITY' && $request->filled('due_date')) {
            $leaveRequest->details()->create([
                'field_name' => 'Expected Due Date',
                'field_key' => 'due_date',
                'field_value' => (string) $request->input('due_date'),
            ]);
        }

        if ($leaveType->code === 'PARENTAL' && $request->filled('child_birth_date')) {
            $leaveRequest->details()->create([
                'field_name' => 'Child Birth Date',
                'field_key' => 'child_birth_date',
                'field_value' => (string) $request->input('child_birth_date'),
            ]);
        }

        // Update pending balance if balance tracking enabled
        if ($policy?->allow_balance ?? true) {
            $currentYear = $startDate->year;
            $balance = LeaveBalance::where('leave_type_id', $leaveType->id)
                ->where('employee_id', $employee->id)
                ->where('year', $currentYear)
                ->first();

            if ($balance) {
                if ($initialStatus === 'approved') {
                    $balance->used = (float) $balance->used + $totalDays;
                    $balance->balance = max(0.0, (float) $balance->balance - $totalDays);
                } else {
                    $balance->pending = (float) $balance->pending + $totalDays;
                }

                $balance->save();
            }
        }

        return $this->successResponse(
            $leaveRequest->load(['files', 'details', 'leaveType']),
            'Leave request submitted successfully.',
            201
        );
    }

    /**
     * Update an applied leave request belonging to the authenticated employee.
     */
    #[OA\Patch(
        path: '/api/v1/leaves/{id}',
        summary: 'Update an applied leave request',
        security: [['sanctum' => []]],
        tags: ['Leave Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Leave request updated successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Leave request not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Request $request, int|string $id, LeaveValidationService $validationService)
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $leaveRequest = LeaveRequest::with('details')
            ->where('employee_id', $employee->id)
            ->find($id);

        if (! $leaveRequest) {
            return $this->errorResponse('Leave request not found.', 404);
        }

        if ($leaveRequest->status !== 'applied') {
            return $this->errorResponse(
                'Only leave requests with applied status can be edited.',
                422,
                ['status' => ['Only leave requests with applied status can be edited.']],
            );
        }

        $validated = $request->validate([
            'leave_type_id' => ['sometimes', 'integer', 'exists:leave_types,id'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'request_type' => ['sometimes', 'in:fullday,firsthalf,secondhalf'],
            'total_days' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:applied'],
            'remarks' => ['sometimes', 'nullable', 'string'],
            'certificate' => ['sometimes', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'signature' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'handover_person_id' => ['sometimes', 'nullable', 'exists:salescrm.employees,id'],
            'handover_description' => ['sometimes', 'nullable', 'string'],
            'emergency_contact_no' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9]+$/'],
            'travel_contact_no' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9]+$/'],
            'traveling_outside_country' => ['sometimes', 'boolean'],
            'destination' => ['sometimes', 'nullable', 'string'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'child_birth_date' => ['sometimes', 'nullable', 'date'],
            'festival_id' => ['sometimes', 'nullable', 'exists:festivals,id'],
            'declaration' => ['sometimes', 'nullable', 'string'],
        ]);

        if (
            isset($validated['leave_type_id'])
            && (int) $validated['leave_type_id'] !== (int) $leaveRequest->leave_type_id
        ) {
            return $this->errorResponse(
                'Leave type cannot be changed after the request is created.',
                422,
                ['leave_type_id' => ['Leave type cannot be changed after the request is created.']],
            );
        }

        $details = $leaveRequest->details->keyBy('field_key');
        $detailValue = fn (string $key): ?string => $details->get($key)?->field_value;
        $datesChanged = $request->exists('start_date') || $request->exists('end_date');

        $validationData = array_merge([
            'leave_type_id' => $leaveRequest->leave_type_id,
            'start_date' => $leaveRequest->start_date->toDateString(),
            'end_date' => $leaveRequest->end_date?->toDateString(),
            'request_type' => $leaveRequest->request_type ?? 'fullday',
            'total_days' => $datesChanged ? null : $leaveRequest->total_days,
            'remarks' => $leaveRequest->remarks,
            'handover_person_id' => $detailValue('handover_person_id'),
            'handover_description' => $detailValue('handover_description'),
            'emergency_contact_no' => $detailValue('emergency_contact_no'),
            'travel_contact_no' => $detailValue('travel_contact_no'),
            'traveling_outside_country' => $detailValue('traveling_outside_country') === 'Yes',
            'destination' => $detailValue('destination'),
            'due_date' => $detailValue('due_date'),
            'child_birth_date' => $detailValue('child_birth_date'),
            'festival_id' => $detailValue('festival_id'),
        ], $validated);

        try {
            $validation = $validationService->validateApplication(
                $employee,
                $validationData,
                $request->hasFile('certificate') || $leaveRequest->files()->exists(),
                (int) $leaveRequest->id,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        }

        if (! $request->exists('remarks')) {
            $validation['remarks'] = $leaveRequest->remarks;
        }

        $oldYear = $leaveRequest->start_date->year;
        $newYear = $validation['start_date']->year;
        $oldTotalDays = (float) $leaveRequest->total_days;
        $balanceTrackingEnabled = $validation['policy']?->allow_balance ?? true;

        DB::transaction(function () use (
            $request,
            $user,
            $leaveRequest,
            $validation,
            $validationData,
            $balanceTrackingEnabled,
            $oldYear,
            $newYear,
            $oldTotalDays,
        ): void {
            $leaveRequest->update([
                'start_date' => $validation['start_date']->toDateString(),
                'end_date' => $validation['end_date']->toDateString(),
                'request_type' => $validation['request_type'],
                'total_days' => $validation['total_days'],
                'remarks' => $validation['remarks'],
            ]);

            if ($balanceTrackingEnabled) {
                $pendingChanges = [$oldYear => -$oldTotalDays];
                $pendingChanges[$newYear] = ($pendingChanges[$newYear] ?? 0.0) + $validation['total_days'];

                foreach ($pendingChanges as $year => $change) {
                    if ($change === 0.0) {
                        continue;
                    }

                    $balance = LeaveBalance::query()
                        ->where('employee_id', $leaveRequest->employee_id)
                        ->where('leave_type_id', $leaveRequest->leave_type_id)
                        ->where('year', $year)
                        ->lockForUpdate()
                        ->first();

                    if ($balance) {
                        $balance->pending = max(0, (float) $balance->pending + $change);
                        $balance->save();
                    }
                }
            }

            if ($validation['policy']?->requiresHandoverForDuration($validation['total_days'])) {
                $this->updateLeaveRequestDetail(
                    $leaveRequest,
                    'handover_person_id',
                    'Handover Person ID',
                    $validationData['handover_person_id'] ?? null,
                );
                $this->updateLeaveRequestDetail(
                    $leaveRequest,
                    'handover_description',
                    'Handover Description',
                    $validationData['handover_description'] ?? null,
                );
            }

            $detailUpdates = [
                'emergency_contact_no' => ['Emergency Contact No', 'emergency_contact_no'],
                'travel_contact_no' => ['Travel Contact No', 'travel_contact_no'],
                'destination' => ['Destination', 'destination'],
                'due_date' => ['Expected Due Date', 'due_date'],
                'child_birth_date' => ['Child Birth Date', 'child_birth_date'],
                'declaration' => ['Declaration', 'declaration_text'],
            ];

            foreach ($detailUpdates as $input => [$label, $key]) {
                if ($request->exists($input)) {
                    $this->updateLeaveRequestDetail(
                        $leaveRequest,
                        $key,
                        $label,
                        $request->input($input),
                    );
                }
            }

            if ($request->exists('festival_id')) {
                $this->updateLeaveRequestDetail(
                    $leaveRequest,
                    'festival_id',
                    'Festival ID',
                    $request->input('festival_id'),
                );
            }

            if ($request->exists('traveling_outside_country')) {
                if ($request->boolean('traveling_outside_country')) {
                    $this->updateLeaveRequestDetail($leaveRequest, 'traveling_outside_country', 'Traveling Outside Country', 'Yes');
                } else {
                    $leaveRequest->details()
                        ->whereIn('field_key', ['traveling_outside_country', 'destination', 'travel_contact_no'])
                        ->delete();
                    $this->updateLeaveRequestDetail($leaveRequest, 'declaration_signed', 'Declaration Signed', 'Yes');
                }
            }

            if ($request->hasFile('certificate')) {
                $path = $request->file('certificate')->store('leave-certificates', 'public');
                $leaveRequest->files()->create([
                    'file_path' => $path,
                    'uploaded_by' => $user->id,
                    'uploaded_date' => now(),
                ]);
            }

            if ($request->hasFile('signature')) {
                $signaturePath = $request->file('signature')->store('leave-signatures', 'public');
                $this->updateLeaveRequestDetail($leaveRequest, 'signature_path', 'Signature', $signaturePath);
            }

            $leaveRequest->histories()->create([
                'action_type' => 'applied',
                'remarks' => 'Leave request updated via API.',
                'done_by' => $user->id,
                'action_on' => now(),
            ]);
        });

        return $this->successResponse(
            $leaveRequest->fresh()->load(['files', 'details', 'leaveType']),
            'Leave request updated successfully.',
        );
    }

    private function updateLeaveRequestDetail(
        LeaveRequest $leaveRequest,
        string $fieldKey,
        string $fieldName,
        mixed $value,
    ): void {
        if ($value === null || $value === '') {
            $leaveRequest->details()->where('field_key', $fieldKey)->delete();

            return;
        }

        $leaveRequest->details()->updateOrCreate(
            ['field_key' => $fieldKey],
            ['field_name' => $fieldName, 'field_value' => (string) $value],
        );
    }

    /**
     * Get the leave history (requests) for the authenticated employee.
     */
    #[OA\Get(
        path: '/api/v1/leaves',
        summary: 'Get leave requests history',
        security: [['sanctum' => []]],
        tags: ['Leave Management'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'year', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Leave requests retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $query = LeaveRequest::with('leaveType:id,leave_name')
            ->where('employee_id', $employee->id)
            ->orderBy('created_at', 'desc');

        // Optional filters
        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        [$filterMonth, $filterYear] = $this->parseMonthAndYear(
            $request->query('month') ?? $request->input('month'),
            $request->query('year') ?? $request->input('year')
        );

        if ($filterYear) {
            $query->whereYear('created_at', $filterYear);
        }

        if ($filterMonth) {
            $query->whereMonth('created_at', $filterMonth);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date]);
        }

        $leaveRequests = $query->paginate(15);

        $extra = [];
        if ($request->filled('month') || $request->boolean('include_festivals') || $request->boolean('include_holidays')) {
            $festivalData = $this->getFestivalsAndHolidaysForEmployee($employee, $filterMonth, $filterYear);
            $extra['festivals'] = $festivalData['festivals'];
            $extra['holidays'] = $festivalData['holidays'];
        }

        return $this->successPaginatedResponse($leaveRequests, 'leave_requests', 'Success', 200, $extra);
    }

    /**
     * Get details of a single leave request for the authenticated employee.
     */
    #[OA\Get(
        path: '/api/v1/leaves/{id}',
        summary: 'Get leave request details',
        security: [['sanctum' => []]],
        tags: ['Leave Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Leave request details retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Leave request not found'),
        ]
    )]
    public function show(Request $request, int|string $id)
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $leaveRequest = LeaveRequest::with([
            'leaveType:id,leave_name,code,is_paid,requires_approval',
            'files',
            'details',
            'histories' => function ($query) {
                $query->orderBy('action_on', 'asc');
            },
            'histories.doneBy:id,name,email',
            'approvals' => function ($query) {
                $query->orderBy('approved_date', 'asc');
            },
            'approvals.approver:id,name,email',
        ])
            ->where('employee_id', $employee->id)
            ->find($id);

        if (! $leaveRequest) {
            return $this->errorResponse('Leave request not found.', 404);
        }

        $files = $leaveRequest->files->map(function ($file) {
            return [
                'id' => $file->id,
                'file_path' => $file->file_path,
                'file_url' => asset('storage/'.$file->file_path),
                'original_name' => $file->original_name ?? basename($file->file_path),
                'mime_type' => $file->mime_type ?? null,
                'size' => $file->size ?? null,
                'uploaded_date' => $file->uploaded_date?->toIso8601String(),
            ];
        });

        $details = $leaveRequest->details->map(function ($detail) {
            $item = [
                'id' => $detail->id,
                'field_name' => $detail->field_name,
                'field_key' => $detail->field_key,
                'field_value' => $detail->field_value,
            ];

            if ($detail->field_key === 'signature_path' && ! empty($detail->field_value)) {
                $item['file_url'] = asset('storage/'.$detail->field_value);
            }

            return $item;
        });

        $histories = $leaveRequest->histories->map(function ($history) {
            return [
                'id' => $history->id,
                'action_type' => $history->action_type,
                'remarks' => $history->remarks,
                'action_on' => $history->action_on?->toIso8601String(),
                'done_by' => $history->doneBy ? [
                    'id' => $history->doneBy->id,
                    'name' => $history->doneBy->name,
                    'email' => $history->doneBy->email,
                ] : null,
            ];
        });

        $approvals = $leaveRequest->approvals->map(function ($approval) {
            return [
                'id' => $approval->id,
                'approval_level' => $approval->approval_level,
                'approver_id' => $approval->approver_id,
                'remarks' => $approval->remarks,
                'approved_date' => $approval->approved_date?->toIso8601String(),
                'approver' => $approval->approver ? [
                    'id' => $approval->approver->id,
                    'name' => $approval->approver->name,
                    'email' => $approval->approver->email,
                ] : null,
            ];
        });

        $signatureDetail = $leaveRequest->details->firstWhere('field_key', 'signature_path');
        $signatureUrl = $signatureDetail && ! empty($signatureDetail->field_value)
            ? asset('storage/'.$signatureDetail->field_value)
            : null;

        return $this->successResponse([
            'id' => $leaveRequest->id,
            'employee_id' => $leaveRequest->employee_id,
            'leave_type_id' => $leaveRequest->leave_type_id,
            'leave_type' => $leaveRequest->leaveType,
            'request_type' => $leaveRequest->request_type ?? 'fullday',
            'start_date' => $leaveRequest->start_date?->format('Y-m-d'),
            'end_date' => $leaveRequest->end_date?->format('Y-m-d'),
            'total_days' => $leaveRequest->total_days,
            'remarks' => $leaveRequest->remarks,
            'status' => $leaveRequest->status,
            'created_at' => $leaveRequest->created_at?->toIso8601String(),
            'updated_at' => $leaveRequest->updated_at?->toIso8601String(),
            'files' => $files,
            'details' => $details,
            'signature_url' => $signatureUrl,
            'histories' => $histories,
            'approvals' => $approvals,
        ], 'Leave request details retrieved successfully.');
    }

    /**
     * Get eligible festivals and holidays for an employee, optionally filtered by month and year.
     *
     * @return array{festivals: Collection<int, mixed>, holidays: Collection<int, mixed>, limit_reached: bool}
     */
    protected function getFestivalsAndHolidaysForEmployee(Employee $employee, ?int $month = null, ?int $year = null): array
    {
        $profile = EmployeeProfile::where('employee_id', $employee->id)->first();
        if (! $profile) {
            return [
                'festivals' => collect(),
                'holidays' => collect(),
                'limit_reached' => false,
            ];
        }

        $countryIds = [];

        if (! empty($profile->home_country)) {
            $countryIds[] = (int) $profile->home_country;
        }

        if (! empty($profile->nationality)) {
            if (is_numeric($profile->nationality)) {
                $countryIds[] = (int) $profile->nationality;
            } else {
                $matchedCountryId = DB::connection('salescrm')
                    ->table('countries')
                    ->where('name', 'like', $profile->nationality)
                    ->value('id');
                if ($matchedCountryId) {
                    $countryIds[] = (int) $matchedCountryId;
                }
            }
        }

        $countryIds = array_values(array_unique($countryIds));

        $query = DB::table('festivals')
            ->leftJoin('festival_nationality', 'festivals.id', '=', 'festival_nationality.festival_id')
            ->whereNull('festivals.deleted_at')
            ->where('festivals.is_active', true);

        if (! empty($countryIds)) {
            $query->where(function ($q) use ($countryIds) {
                $q->whereIn('festival_nationality.country_id', $countryIds)
                    ->orWhereNull('festival_nationality.country_id');
            });
        } else {
            $query->whereNull('festival_nationality.country_id');
        }

        $employeeReligion = strtolower(trim($profile->religion ?? ''));
        if (! empty($employeeReligion)) {
            $query->where(function ($q) use ($employeeReligion) {
                $q->whereNull('festivals.religion')
                    ->orWhere('festivals.religion', '')
                    ->orWhereRaw('LOWER(festivals.religion) = ?', [$employeeReligion]);
            });
        } else {
            $query->where(function ($q) {
                $q->whereNull('festivals.religion')
                    ->orWhere('festivals.religion', '');
            });
        }

        if ($month && $year) {
            $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
            $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

            $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->where(function ($sub) use ($startOfMonth, $endOfMonth) {
                    $sub->whereNotNull('festivals.start_date')
                        ->where('festivals.start_date', '<=', $endOfMonth)
                        ->where(function ($inner) use ($startOfMonth) {
                            $inner->where('festivals.end_date', '>=', $startOfMonth)
                                ->orWhere(function ($nullEnd) use ($startOfMonth) {
                                    $nullEnd->whereNull('festivals.end_date')
                                        ->where('festivals.start_date', '>=', $startOfMonth);
                                });
                        });
                })->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
                    $sub->whereNull('festivals.start_date')
                        ->whereNotNull('festivals.end_date')
                        ->whereBetween('festivals.end_date', [$startOfMonth, $endOfMonth]);
                });
            });
        } elseif ($month) {
            $query->where(function ($q) use ($month) {
                $q->whereMonth('festivals.start_date', $month)
                    ->orWhereMonth('festivals.end_date', $month);
            });
        } elseif ($year) {
            $query->where(function ($q) use ($year) {
                $q->whereYear('festivals.start_date', $year)
                    ->orWhereYear('festivals.end_date', $year);
            });
        }

        $festivals = $query
            ->select('festivals.id', 'festivals.name', 'festivals.type', 'festivals.religion', 'festivals.shortcode', 'festivals.start_date', 'festivals.end_date')
            ->distinct()
            ->orderBy('festivals.start_date', 'asc')
            ->get();

        $festivalLeaveType = LeaveType::where('leave_name', 'like', '%Festival%')->first();
        $limitReached = false;

        if ($festivalLeaveType) {
            $checkYear = $year ?? (int) date('Y');
            $limitReached = LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $festivalLeaveType->id)
                ->whereYear('start_date', $checkYear)
                ->whereIn('status', ['applied', 'approved'])
                ->exists();
        }

        $grouped = $festivals->groupBy('type');

        return [
            'festivals' => $grouped->get('festival', collect())->values(),
            'holidays' => $grouped->get('holiday', collect())->values(),
            'limit_reached' => $limitReached,
        ];
    }

    /**
     * Parse month and year from request input.
     *
     * @return array{0: int|null, 1: int|null}
     */
    protected function parseMonthAndYear(?string $monthInput, ?string $yearInput = null): array
    {
        $month = null;
        $year = $yearInput ? (int) $yearInput : null;

        if (! empty($monthInput)) {
            $monthInput = trim((string) $monthInput);

            if (preg_match('/^(\d{4})[-\/](\d{1,2})$/', $monthInput, $matches)) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
            } elseif (preg_match('/^(\d{1,2})[-\/](\d{4})$/', $monthInput, $matches)) {
                $month = (int) $matches[1];
                $year = (int) $matches[2];
            } elseif (is_numeric($monthInput) && (int) $monthInput >= 1 && (int) $monthInput <= 12) {
                $month = (int) $monthInput;
            } else {
                try {
                    $parsed = Carbon::parse($monthInput);
                    $month = $parsed->month;
                    if (preg_match('/\b\d{4}\b/', $monthInput)) {
                        $year = $parsed->year;
                    }
                } catch (\Throwable $e) {
                    // Ignore invalid strings
                }
            }
        }

        return [$month, $year];
    }
}
