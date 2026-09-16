<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestFile;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Services\LeaveValidationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveController extends Controller
{
    /**
     * Get the metadata for the leave application screen.
     */
    public function meta(Request $request)
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

        // Fetch all active leave types
        $leaveTypes = LeaveType::where('status', 1)->get();

        $typesData = $leaveTypes->map(function ($type) use ($employee) {
            // There is only one policy for a single leave type
            $policy = $type->policy ?? LeavePolicy::where('leave_type_id', $type->id)->first();

            // Get current year balance
            $balance = LeaveBalance::where('leave_type_id', $type->id)
                ->where('employee_id', $employee->id)
                ->where('year', date('Y'))
                ->first();

            // Combined attachment condition
            $requiresAttachment = (bool) ($type->requires_attachment || ($policy && $policy->requires_attachment));

            return [
                'id' => $type->id,
                'leave_name' => $type->leave_name,
                'code' => $type->code,
                'is_paid' => (bool) $type->is_paid,
                'requires_attachment' => $requiresAttachment,
                'requires_approval' => (bool) $type->requires_approval,
                'requires_handover' => (bool) $type->requires_handover,
                'max_days' => $type->max_days,
                'annual_limit' => $type->annual_limit,
                'gender' => $type->gender,
                'allow_once' => (bool) $type->allow_once,
                'allow_balance' => (bool) $type->allow_balance,
                'balance' => [
                    'total' => $balance ? (float) $balance->allocated : 0,
                    'used' => $balance ? (float) $balance->used : 0,
                    'balance' => $balance ? (float) $balance->balance : 0,
                    'pending' => $balance ? (float) $balance->pending : 0,
                ],
                'policy' => $policy ? [
                    'id' => $policy->id,
                    'full_pay_days' => $policy->full_pay_days,
                    'half_pay_days' => $policy->half_pay_days,
                    'no_pay_days' => $policy->no_pay_days,
                    'requires_document_after_days' => $policy->requires_document_after_days,
                    'requires_weekend_document' => (bool) $policy->requires_weekend_document,
                    'requires_attachment' => (bool) $policy->requires_attachment,
                    'carry_forward' => (bool) $policy->carry_forward,
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

        // Fetch leaves applied by this employee for this month/year
        $leavesQuery = LeaveRequest::with('leaveType:id,leave_name,code,is_paid')
            ->where('employee_id', $employee->id);

        if ($month && $year) {
            $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth()->toDateTimeString();
            $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth()->toDateTimeString();

            $leavesQuery->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->where(function ($sub) use ($startOfMonth, $endOfMonth) {
                    $sub->where('start_date', '<=', $endOfMonth)
                        ->where(function ($inner) use ($startOfMonth) {
                            $inner->where('end_date', '>=', $startOfMonth)
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
        $data['applied_leaves'] = $appliedLeaves;

        return $this->successResponse($data);
    }

    /**
     * Submit a new leave request.
     */
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
        $totalDays = $validation['total_days'];
        $remarks = $validation['remarks'];
        $payBreakdown = $validation['pay_breakdown'];

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => $endDate->toDateTimeString(),
            'total_days' => $totalDays,
            'remarks' => $remarks,
            'status' => 'applied',
            'created_by' => $user->id,
        ]);

        $leaveRequest->histories()->create([
            'action_type' => 'applied',
            'remarks' => 'Leave application submitted via API.',
            'done_by' => $user->id,
            'action_on' => now(),
        ]);

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

        // Store handover details if required
        if ($leaveType->requires_handover) {
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

        // Store Pay Tier Breakdown calculated from Policy
        $payBreakdownText = "Full Pay: {$payBreakdown['full_pay']} days, Half Pay: {$payBreakdown['half_pay']} days, No Pay: {$payBreakdown['no_pay']} days";
        $leaveRequest->details()->create([
            'field_name' => 'Pay Tier Breakdown',
            'field_key' => 'pay_tier_breakdown',
            'field_value' => $payBreakdownText,
        ]);

        // Update pending balance if balance tracking enabled
        if ($leaveType->allow_balance) {
            $currentYear = $startDate->year;
            $balance = LeaveBalance::where('leave_type_id', $leaveType->id)
                ->where('employee_id', $employee->id)
                ->where('year', $currentYear)
                ->first();

            if ($balance) {
                $balance->pending = (float) $balance->pending + $totalDays;
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
     * Get the leave history (requests) for the authenticated employee.
     */
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
            'leaveType:id,leave_name,code,is_paid,requires_attachment,requires_approval,requires_handover',
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
            return [
                'id' => $detail->id,
                'field_name' => $detail->field_name,
                'field_key' => $detail->field_key,
                'field_value' => $detail->field_value,
            ];
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

        return $this->successResponse([
            'id' => $leaveRequest->id,
            'employee_id' => $leaveRequest->employee_id,
            'leave_type_id' => $leaveRequest->leave_type_id,
            'leave_type' => $leaveRequest->leaveType,
            'start_date' => $leaveRequest->start_date?->format('Y-m-d'),
            'end_date' => $leaveRequest->end_date?->format('Y-m-d'),
            'total_days' => $leaveRequest->total_days,
            'remarks' => $leaveRequest->remarks,
            'status' => $leaveRequest->status,
            'created_at' => $leaveRequest->created_at?->toIso8601String(),
            'updated_at' => $leaveRequest->updated_at?->toIso8601String(),
            'files' => $files,
            'details' => $details,
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
            ->select('festivals.id', 'festivals.name', 'festivals.type', 'festivals.shortcode', 'festivals.start_date', 'festivals.end_date')
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
