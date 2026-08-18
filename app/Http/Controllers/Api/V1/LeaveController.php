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
     * Get eligible festivals for the employee.
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

        $profile = EmployeeProfile::where('employee_id', $employee->id)->first();
        if (! $profile) {
            return $this->successResponse(['festivals' => [], 'holidays' => [], 'limit_reached' => false]);
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

        if (empty($countryIds)) {
            return $this->successResponse(['festivals' => [], 'holidays' => [], 'limit_reached' => false]);
        }

        $festivals = DB::table('festivals')
            ->join('festival_nationality', 'festivals.id', '=', 'festival_nationality.festival_id')
            ->whereIn('festival_nationality.country_id', $countryIds)
            ->whereNull('festivals.deleted_at')
            ->where('festivals.is_active', true)
            ->select('festivals.id', 'festivals.name', 'festivals.type', 'festivals.shortcode', 'festivals.start_date', 'festivals.end_date')
            ->distinct()
            ->get();

        $festivalLeaveType = LeaveType::where('leave_name', 'like', '%Festival%')->first();
        $limitReached = false;

        if ($festivalLeaveType) {
            $limitReached = LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $festivalLeaveType->id)
                ->whereYear('start_date', date('Y'))
                ->whereIn('status', ['applied', 'approved'])
                ->exists();
        }

        $grouped = $festivals->groupBy('type');

        return $this->successResponse([
            'festivals' => $grouped->get('festival', []),
            'holidays' => $grouped->get('holiday', []),
            'limit_reached' => $limitReached,
        ]);
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

        if ($request->filled('year')) {
            $query->whereYear('created_at', $request->year);
        }

        if ($request->filled('month')) {
            $query->whereMonth('created_at', $request->month);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date]);
        }

        $leaveRequests = $query->paginate(15);

        return $this->successPaginatedResponse($leaveRequests, 'leave_requests');
    }
}
