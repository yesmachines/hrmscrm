<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use App\Models\Festival;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestFile;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        // Fetch all leave types
        $leaveTypes = LeaveType::all();

        $typesData = $leaveTypes->map(function ($type) use ($employee) {
            // Get policy for this employee's organization
            $policy = LeavePolicy::where('leave_type_id', $type->id)
                ->where('organisation_id', $employee->organisation_id)
                ->first();

            // Get current year balance
            $balance = LeaveBalance::where('leave_type_id', $type->id)
                ->where('employee_id', $employee->id)
                ->where('year', date('Y'))
                ->first();

            return [
                'id' => $type->id,
                'leave_name' => $type->leave_name,
                'requires_attachment' => $type->requires_attachment,
                'balance' => [
                    'total' => $balance ? $balance->allocated : 0,
                    'used' => $balance ? $balance->used : 0,
                    'balance' => $balance ? $balance->balance : 0,
                ],
                'policy' => $policy ? [
                    'requires_document_after_days' => $policy->requires_document_after_days,
                    'requires_weekend_document' => $policy->requires_weekend_document,
                    'full_pay_days' => $policy->full_pay_days,
                    'half_pay_days' => $policy->half_pay_days,
                    'no_pay_days' => $policy->no_pay_days,
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

        return $this->successResponse($data);
    }

    /**
     * Submit a new leave request.
     */
    public function store(Request $request)
    {
        $rules = [
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'total_days' => 'nullable|numeric|min:0.5',
            'remarks' => 'nullable|string',
            'festival_id' => 'nullable|exists:festivals,id',
            'certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];

        $leaveType = null;
        $totalDays = $request->input('total_days') ?? (
            $request->filled('start_date') && $request->filled('end_date') ?
            Carbon::parse($request->end_date)->diffInDays(Carbon::parse($request->start_date)) + 1 : 0
        );

        if ($request->filled('leave_type_id')) {
            $leaveType = LeaveType::find($request->leave_type_id);

            if ($leaveType) {
                if ($leaveType->code === 'ANNUAL' || $leaveType->requires_handover) {
                    $rules['handover_person_id'] = 'required|integer';
                    $rules['handover_description'] = 'required|string';
                    $rules['emergency_contact_no'] = 'required|string';
                    $rules['traveling_outside_country'] = 'required|boolean';

                    if (filter_var($request->traveling_outside_country, FILTER_VALIDATE_BOOLEAN)) {
                        $rules['destination'] = 'required|string';
                        $rules['travel_contact_no'] = 'required|string';
                    } else {
                        $rules['declaration_signed'] = 'required|accepted';
                    }
                }

                if ($leaveType->code === 'MATERNITY') {
                    $rules['certificate'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:10240';
                    $rules['due_date'] = 'required|date';
                }

                if ($leaveType->code === 'PARENTAL') {
                    $rules['child_birth_date'] = 'required|date|before_or_equal:today';
                }

                if ($leaveType->code === 'SICK' && $request->filled('start_date') && $request->filled('end_date')) {
                    $startDate = Carbon::parse($request->start_date);
                    $endDate = Carbon::parse($request->end_date);

                    $hasWeekend = false;
                    $currentDate = $startDate->copy();
                    while ($currentDate->lte($endDate)) {
                        if ($currentDate->isWeekend()) {
                            $hasWeekend = true;
                            break;
                        }
                        $currentDate->addDay();
                    }

                    if ($totalDays > 2 || $hasWeekend) {
                        $rules['certificate'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:10240';
                    }
                }
            }
        }

        $validated = $request->validate($rules);

        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        if ($leaveType) {
            if ($leaveType->code === 'PARENTAL') {
                $childAgeMonths = Carbon::parse($validated['child_birth_date'])->diffInMonths(now());
                if ($childAgeMonths >= 6) {
                    return $this->errorResponse('Child must be under 6 months of age to qualify for Parental Leave.', 422);
                }
            }

            if ($leaveType->code === 'PILGRIMAGE') {
                $hasPilgrimage = LeaveRequest::where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->whereIn('status', ['applied', 'approved'])
                    ->exists();

                if ($hasPilgrimage) {
                    return $this->errorResponse('You have already utilized your Pilgrimage Leave during your tenure.', 422);
                }
            }
        }

        $totalDays = $validated['total_days'] ?? (
            Carbon::parse($validated['end_date'])->diffInDays(Carbon::parse($validated['start_date'])) + 1
        );

        $remarks = $validated['remarks'] ?? null;
        if ($leaveType && stripos($leaveType->leave_name, 'Festival') !== false) {
            if (empty($validated['festival_id'])) {
                return $this->errorResponse('Festival selection is required for this leave type.', 422);
            }

            $limitReached = LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->whereYear('start_date', date('Y'))
                ->whereIn('status', ['applied', 'approved'])
                ->exists();

            if ($limitReached) {
                return $this->errorResponse('You have already taken a Festival Leave this year.', 422);
            }

            $festival = Festival::find($validated['festival_id']);
            $remarks = 'Festival: '.$festival->name.($remarks ? "\n".$remarks : '');
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
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
            ]);
        }

        if ($leaveType && ($leaveType->code === 'ANNUAL' || $leaveType->requires_handover)) {
            $details = [
                ['field_name' => 'Handover Person ID', 'field_key' => 'handover_person_id', 'field_value' => $validated['handover_person_id']],
                ['field_name' => 'Handover Description', 'field_key' => 'handover_description', 'field_value' => $validated['handover_description']],
                ['field_name' => 'Emergency Contact No', 'field_key' => 'emergency_contact_no', 'field_value' => $validated['emergency_contact_no']],
                ['field_name' => 'Traveling Outside Country', 'field_key' => 'traveling_outside_country', 'field_value' => filter_var($validated['traveling_outside_country'], FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No'],
            ];

            if (filter_var($validated['traveling_outside_country'], FILTER_VALIDATE_BOOLEAN)) {
                $details[] = ['field_name' => 'Destination', 'field_key' => 'destination', 'field_value' => $validated['destination']];
                $details[] = ['field_name' => 'Travel Contact No', 'field_key' => 'travel_contact_no', 'field_value' => $validated['travel_contact_no']];
            } else {
                $details[] = ['field_name' => 'Declaration Signed', 'field_key' => 'declaration_signed', 'field_value' => 'Yes'];
            }

            foreach ($details as $detail) {
                $leaveRequest->details()->create($detail);
            }
        }

        if ($leaveType && $leaveType->code === 'MATERNITY' && isset($validated['due_date'])) {
            $leaveRequest->details()->create([
                'field_name' => 'Expected Due Date',
                'field_key' => 'due_date',
                'field_value' => $validated['due_date'],
            ]);
        }

        if ($leaveType && $leaveType->code === 'PARENTAL' && isset($validated['child_birth_date'])) {
            $leaveRequest->details()->create([
                'field_name' => 'Child Birth Date',
                'field_key' => 'child_birth_date',
                'field_value' => $validated['child_birth_date'],
            ]);
        }

        if ($leaveType && $leaveType->code === 'SICK') {
            $policy = LeavePolicy::where('leave_type_id', $leaveType->id)
                ->where('organisation_id', $employee->organisation_id)
                ->first();

            $balance = LeaveBalance::where('leave_type_id', $leaveType->id)
                ->where('employee_id', $employee->id)
                ->where('year', date('Y'))
                ->first();

            $usedDays = $balance ? $balance->used : 0;
            $fullPayDaysLimit = $policy ? ($policy->full_pay_days ?? 15) : 15;
            $halfPayDaysLimit = $policy ? ($policy->half_pay_days ?? 30) : 30;

            $daysToCalculate = $totalDays;
            $fullPay = 0;
            $halfPay = 0;
            $noPay = 0;

            // Calculate how many days go into Full Pay
            if ($usedDays < $fullPayDaysLimit) {
                $availableFull = $fullPayDaysLimit - $usedDays;
                if ($daysToCalculate <= $availableFull) {
                    $fullPay = $daysToCalculate;
                    $daysToCalculate = 0;
                } else {
                    $fullPay = $availableFull;
                    $daysToCalculate -= $availableFull;
                }
            }

            // Calculate how many days go into Half Pay
            $usedAfterFull = max(0, $usedDays - $fullPayDaysLimit);
            if ($daysToCalculate > 0 && $usedAfterFull < $halfPayDaysLimit) {
                $availableHalf = $halfPayDaysLimit - $usedAfterFull;
                if ($daysToCalculate <= $availableHalf) {
                    $halfPay = $daysToCalculate;
                    $daysToCalculate = 0;
                } else {
                    $halfPay = $availableHalf;
                    $daysToCalculate -= $availableHalf;
                }
            }

            // The rest is No Pay
            if ($daysToCalculate > 0) {
                $noPay = $daysToCalculate;
            }

            $payBreakdown = "Full Pay: {$fullPay} days, Half Pay: {$halfPay} days, No Pay: {$noPay} days";

            $leaveRequest->details()->create([
                'field_name' => 'Pay Tier Breakdown',
                'field_key' => 'pay_tier_breakdown',
                'field_value' => $payBreakdown,
            ]);
        }

        return $this->successResponse($leaveRequest->load(['files', 'details']), 'Leave request submitted successfully.', 201);
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
