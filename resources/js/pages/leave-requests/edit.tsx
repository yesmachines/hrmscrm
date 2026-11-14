import { Head, Link, useForm, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    Save,
    User as UserIcon,
    AlertCircle,
    Clock,
    FileUp,
    Ban,
    FileText,
} from 'lucide-react';
import { useState, useId, useMemo, useEffect } from 'react';
import { FormattedDatePicker } from '@/components/formatted-date-picker';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { countLeaveWeekdays } from '@/lib/leave-duration';
import { getLeavePolicyRequirements } from '@/lib/leave-policy-requirements';
import { dashboard } from '@/routes';

interface EmployeeOption {
    id: number;
    user_id: number;
    emp_num: string;
    employee_code: string | null;
    designation: string | null;
    organisation_id: number | null;
    country_ids: number[];
    gender?: string | null;
    religion?: string | null;
    marital_status?: string | null;
    profile?: {
        id?: number;
        gender?: string | null;
        religion?: string | null;
        marital_status?: string | null;
        nationality?: string | null;
        home_country?: number | null;
    } | null;
    department?: {
        id: number;
        name: string;
    } | null;
    user?: {
        id: number;
        name: string;
    } | null;
}

interface LeaveTypeOption {
    id: number;
    eligible_employee_ids: number[];
    leave_name: string;
    code: string;
    is_paid: boolean;
    policy?: LeavePolicyOption | null;
    policies?: LeavePolicyOption[];
    status?: number;
    gender?: string | null;
    religion?: string | null;
}

interface LeavePolicyOption {
    organisation_id: number;
    annual_limit: number | null;
    requires_document_after_days: number | null;
    requires_attachment: boolean;
    requires_handover: boolean;
    requires_weekend_document?: boolean;
}

interface LeaveBalanceItem {
    id: number;
    employee_id: number;
    leave_type_id: number;
    allocated: number;
    used: number;
    balance: number;
}

interface ActiveLeaveItem {
    id: number;
    employee_id: number;
    start_date: string;
    end_date: string;
    status: string;
}

interface FestivalOption {
    id: number;
    name: string;
    religion: string | null;
    start_date: string | null;
    end_date: string | null;
    country_ids: number[];
    eligible_employee_ids: number[];
}

interface LeaveRequestItem {
    id: number;
    employee_id: number;
    leave_type_id: number;
    request_type: string;
    start_date: string;
    end_date: string;
    total_days: number;
    status: string;
    remarks: string | null;
    employee?: EmployeeOption;
    leave_type?: LeaveTypeOption;
    handover_person_id?: string | null;
    handover_description?: string | null;
    files?: { id: number; file_path: string }[];
    child_birth_date?: string | null;
    due_date?: string | null;
    festival_id?: string | null;
    family_relationship?: string | null;
}

interface Props {
    leaveRequest: LeaveRequestItem;
    employees: EmployeeOption[];
    leaveTypes: LeaveTypeOption[];
    leaveBalances: LeaveBalanceItem[];
    activeLeaves?: ActiveLeaveItem[];
    festivals?: FestivalOption[];
    compassionateRelationships: Record<string, { label: string; group: string; max_days: number }>;
}

export default function LeaveRequestsEdit({
    leaveRequest,
    employees = [],
    leaveTypes = [],
    leaveBalances = [],
    activeLeaves = [],
    festivals = [],
    compassionateRelationships,
}: Props) {
    const employeeSelectId = useId();
    const leaveTypeSelectId = useId();
    const requestTypeSelectId = useId();
    const dueDateId = useId();
    const startDateId = useId();
    const endDateId = useId();
    const childBirthDateId = useId();
    const totalDaysId = useId();
    const statusSelectId = useId();
    const remarksId = useId();
    const certificateId = useId();
    const handoverPersonSelectId = useId();
    const handoverDescId = useId();
    const hasExistingAttachment = Boolean(leaveRequest.files?.length);

    const [isCancelling, setIsCancelling] = useState(false);

    const { data, setData, post, processing, errors, clearErrors, setError } = useForm({
        _method: 'PUT',
        employee_id: String(leaveRequest.employee_id),
        leave_type_id: String(leaveRequest.leave_type_id),
        request_type: leaveRequest.request_type ?? 'fullday',
        start_date: leaveRequest.start_date,
        end_date: leaveRequest.end_date,
        total_days: String(leaveRequest.total_days),
        status: leaveRequest.status,
        remarks: leaveRequest.remarks ?? '',
        certificate: null as File | null,
        handover_person_id: leaveRequest.handover_person_id ?? '',
        handover_description: leaveRequest.handover_description ?? '',
        child_birth_date: leaveRequest.child_birth_date ?? '',
        due_date: leaveRequest.due_date ?? '',
        festival_id: leaveRequest.festival_id ?? '',
        family_relationship: leaveRequest.family_relationship ?? '',
    });

    // Calculate days between start and end date when dates change
    useEffect(() => {
        if (data.start_date && data.end_date) {
            const start = new Date(data.start_date);
            const end = new Date(data.end_date);

            if (!isNaN(start.getTime()) && !isNaN(end.getTime()) && end >= start) {
                const weekdays = countLeaveWeekdays(data.start_date, data.end_date);
                setData('total_days', data.request_type === 'fullday' ? String(weekdays) : '0.5');
            }
        }

    }, [data.start_date, data.end_date, data.request_type, setData]);

    const selectedEmployee = useMemo(() => {
        if (!data.employee_id) {
            return null;
        }

        return employees.find((e) => String(e.id) === String(data.employee_id)) ?? leaveRequest.employee ?? null;
    }, [data.employee_id, employees, leaveRequest.employee]);

    // Find current balance for selected employee and leave type
    const selectedBalance = useMemo(() => {
        if (!data.employee_id || !data.leave_type_id) {
            return null;
        }

        const empId = Number(data.employee_id);
        const typeId = Number(data.leave_type_id);

        return leaveBalances.find((b) => b.employee_id === empId && b.leave_type_id === typeId) ?? null;
    }, [data.employee_id, data.leave_type_id, leaveBalances]);

    const availableLeaveTypes = useMemo(() => {
        if (!selectedEmployee) {
            return leaveTypes;
        }

        return leaveTypes.filter((type) => type.eligible_employee_ids.includes(selectedEmployee.id));
    }, [selectedEmployee, leaveTypes]);

    const selectedLeaveType = useMemo(() => {
        if (!data.leave_type_id) {
            return null;
        }

        return availableLeaveTypes.find((lt) => String(lt.id) === String(data.leave_type_id)) ?? leaveRequest.leave_type ?? null;
    }, [data.leave_type_id, availableLeaveTypes, leaveRequest.leave_type]);

    const selectedPolicy = useMemo(() => {
        if (!selectedLeaveType || selectedEmployee?.organisation_id == null) {
            return null;
        }

        const organisationPolicy = selectedLeaveType.policies?.find(
            (policy) => Number(policy.organisation_id) === Number(selectedEmployee?.organisation_id),
        );

        if (organisationPolicy) {
            return organisationPolicy;
        }

        const isOriginalEmployee = selectedEmployee.id === leaveRequest.employee_id;

        return isOriginalEmployee && selectedLeaveType.id === leaveRequest.leave_type_id
            ? selectedLeaveType.policy ?? null
            : null;
    }, [leaveRequest.employee_id, leaveRequest.leave_type_id, selectedEmployee, selectedLeaveType]);

    const isParentalLeave = selectedLeaveType?.code === 'PARENTAL';
    const isMaternityLeave = selectedLeaveType?.code === 'MATERNITY';
    const isCompassionateLeave = selectedLeaveType?.code === 'COMPASSIONATE';
    const requiresChildBirthDate = ['PARENTAL', 'PATERNITY'].includes(selectedLeaveType?.code ?? '');
    const isFestivalLeave = selectedLeaveType?.code === 'FESTIVAL'
        || selectedLeaveType?.leave_name.toLowerCase().includes('festival') === true;
    const eligibleFestivals = useMemo(() => {
        if (!selectedEmployee) {
            return [];
        }

        return festivals.filter((festival) => festival.eligible_employee_ids.includes(selectedEmployee.id));
    }, [festivals, selectedEmployee]);
    const isActiveRequest = ['applied', 'approved'].includes(data.status);

    const { requiresAttachment, requiresHandover } = getLeavePolicyRequirements(
        selectedPolicy,
        Number(data.total_days || 0),
        data.start_date,
        data.end_date,
    );
    const eligibilityPayload = useMemo(() => ({
        employee_id: Number(data.employee_id),
        leave_type_id: Number(data.leave_type_id),
        request_type: data.request_type,
        start_date: data.start_date,
        end_date: data.end_date,
        total_days: data.total_days,
        due_date: data.due_date || null,
        child_birth_date: data.child_birth_date || null,
        handover_person_id: data.handover_person_id || null,
        handover_description: data.handover_description || null,
        festival_id: data.festival_id || null,
        family_relationship: data.family_relationship || null,
        ignore_leave_request_id: leaveRequest.id,
    }), [
        data.child_birth_date,
        data.festival_id,
        data.family_relationship,
        data.due_date,
        data.employee_id,
        data.end_date,
        data.handover_description,
        data.handover_person_id,
        data.leave_type_id,
        data.request_type,
        data.start_date,
        data.total_days,
        leaveRequest.id,
    ]);
    useEffect(() => {
        if (!eligibilityPayload.start_date || !eligibilityPayload.end_date) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch('/leave-requests/validate', {
                method: 'POST',
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(eligibilityPayload),
            }).then(async (response) => {
                if (!response.ok) {
                    throw new Error('Unable to validate the leave request.');
                }

                const result = await response.json() as { valid: boolean; errors?: Record<string, string[]> };

                if (controller.signal.aborted) {
                    return;
                }

                clearErrors();

                if (!result.valid && result.errors) {
                    for (const [field, messages] of Object.entries(result.errors)) {
                        if (messages[0]) {
                            setError(field as keyof typeof data, messages[0]);
                        }
                    }
                }
            }).catch((error: unknown) => {
                if (error instanceof Error && error.name !== 'AbortError') {
                    console.error(error);
                }
            });
        }, 300);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [clearErrors, eligibilityPayload, setError]);

    const candidateHandoverEmployees = useMemo(() => {
        if (!data.employee_id) {
            return employees;
        }

        const currentEmpId = Number(data.employee_id);

        return employees.filter((emp) => emp.id !== currentEmpId);
    }, [data.employee_id, employees]);

    const handoverAvailabilityMap = useMemo(() => {
        if (!data.start_date || !data.end_date) {
            return new Map<number, boolean>();
        }

        const reqStart = new Date(data.start_date);
        const reqEnd = new Date(data.end_date);
        const map = new Map<number, boolean>();

        for (const candidate of candidateHandoverEmployees) {
            const hasConflict = activeLeaves.some((leave) => {
                if (leave.employee_id !== candidate.id) {
                    return false;
                }

                const lStart = new Date(leave.start_date);
                const lEnd = new Date(leave.end_date);

                return reqStart <= lEnd && reqEnd >= lStart;
            });

            map.set(candidate.id, hasConflict);
        }

        return map;
    }, [data.start_date, data.end_date, candidateHandoverEmployees, activeLeaves]);

    const isHandoverPersonConflicted = useMemo(() => {
        if (!data.handover_person_id) {
            return false;
        }

        return handoverAvailabilityMap.get(Number(data.handover_person_id)) === true;
    }, [data.handover_person_id, handoverAvailabilityMap]);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        clearErrors();

        if (data.request_type !== 'fullday' && data.start_date !== data.end_date) {
            setError('request_type', 'Half-day leave must start and end on the same date.');

            return;
        }

        if (data.request_type !== 'fullday' && countLeaveWeekdays(data.start_date, data.start_date) === 0) {
            setError('request_type', 'Half-day leave must be on a weekday.');

            return;
        }

        if (data.request_type === 'fullday' && countLeaveWeekdays(data.start_date, data.end_date) === 0) {
            setError('total_days', 'Selected dates do not include any weekdays.');

            return;
        }

        if (requiresChildBirthDate && isActiveRequest && !data.child_birth_date) {
            setError('child_birth_date', "The child's date of birth is required for this leave type.");

            return;
        }

        if (isMaternityLeave && isActiveRequest && !data.due_date) {
            setError('due_date', 'An expected due date is required for Maternity Leave.');

            return;
        }

        if (isFestivalLeave && isActiveRequest && !data.festival_id) {
            setError('festival_id', 'Please select a festival.');

            return;
        }

        if (requiresHandover && !data.handover_person_id) {
            setError('handover_person_id', 'A handover colleague is required by this leave policy.');

            return;
        }

        if (requiresHandover && !data.handover_description.trim()) {
            setError('handover_description', 'A handover description is required by this leave policy.');

            return;
        }

        if (requiresHandover && isHandoverPersonConflicted) {
            setError('handover_person_id', 'The selected handover colleague is on leave during these dates.');

            return;
        }

        if (requiresAttachment && !hasExistingAttachment && !data.certificate) {
            setError('certificate', 'A supporting document is required by this leave policy.');

            return;
        }

        if (data.certificate && (data.certificate.size > 10 * 1024 * 1024 || !/\.(pdf|jpe?g|png|docx?)$/i.test(data.certificate.name))) {
            setError('certificate', 'Choose a PDF, JPG, PNG, DOC or DOCX file no larger than 10 MB.');

            return;
        }

        post(`/leave-requests/${leaveRequest.id}`);
    }

    function handleQuickCancel() {
        if (confirm('Are you sure you want to cancel this leave request? If approved, any deducted balance will be refunded.')) {
            setIsCancelling(true);
            router.post(`/leave-requests/${leaveRequest.id}/cancel`, {}, {
                onFinish: () => setIsCancelling(false),
            });
        }
    }

    const wasApproved = leaveRequest.status === 'approved';
    const daysDiff = Number(data.total_days || 0) - Number(leaveRequest.total_days || 0);

    return (
        <>
            <Head title={`Edit Leave Request #${leaveRequest.id}`} />

            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" asChild>
                            <Link href="/leave-requests">
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <Heading
                                title={`Edit Leave Request #${leaveRequest.id}`}
                                description={`Employee: ${leaveRequest.employee?.user?.name ?? 'Employee'} (${leaveRequest.employee?.employee_code ?? ''})`}
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {leaveRequest.status !== 'cancelled' && (
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={handleQuickCancel}
                                disabled={isCancelling}
                            >
                                <Ban className="mr-2 size-4" />
                                Cancel Leave
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <Link href="/leave-requests">Back to list</Link>
                        </Button>
                    </div>
                </div>

                {/* Balance Impact Alert */}
                {wasApproved && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900 shadow-xs">
                        <div className="flex items-start gap-3">
                            <AlertCircle className="size-5 shrink-0 text-amber-600 mt-0.5" />
                            <div>
                                <h4 className="font-semibold text-sm">Approved Leave Balance Impact</h4>
                                <p className="text-xs text-amber-800 mt-0.5">
                                    This leave is currently <strong>Approved</strong> with <strong>{leaveRequest.total_days} days</strong> deducted.
                                    {data.status === 'cancelled' ? (
                                        <span className="block mt-1 text-emerald-800 font-medium">
                                            ✓ Changing status to Cancelled will immediately refund {leaveRequest.total_days} days back to the employee's balance.
                                        </span>
                                    ) : daysDiff !== 0 ? (
                                        <span className="block mt-1 font-medium">
                                            {daysDiff > 0 ? (
                                                `✓ Increasing duration will deduct an additional ${daysDiff} day(s) from balance.`
                                            ) : (
                                                `✓ Reducing duration will refund ${Math.abs(daysDiff)} day(s) back to balance.`
                                            )}
                                        </span>
                                    ) : (
                                        <span className="block mt-1 text-amber-700">
                                            Any changes to the date range, days, or status will automatically recalculate and adjust the employee's quota balance.
                                        </span>
                                    )}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        {/* Main Fields */}
                        <div className="space-y-6 lg:col-span-2">
                            {/* Employee and Status Card */}
                            <div className="rounded-xl border border-border bg-white p-6 shadow-xs">
                                <h3 className="mb-4 text-base font-semibold text-foreground flex items-center gap-2">
                                    <UserIcon className="size-4 text-primary" />
                                    Employee & Request Status
                                </h3>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label htmlFor={employeeSelectId}>Employee</Label>
                                        <select
                                            id={employeeSelectId}
                                            value={data.employee_id}
                                            onChange={(e) => setData('employee_id', e.target.value)}
                                            className="mt-1.5 h-10 w-full rounded-lg border border-input bg-white px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                            required
                                        >
                                            {employees.map((emp) => (
                                                <option key={emp.id} value={emp.id}>
                                                    {emp.user?.name ?? 'Unknown'} ({emp.employee_code ?? emp.emp_num})
                                                </option>
                                            ))}
                                        </select>
                                        {errors.employee_id && (
                                            <p className="mt-1 text-xs text-destructive">{errors.employee_id}</p>
                                        )}
                                    </div>

                                    {isMaternityLeave && (
                                        <div>
                                            <Label htmlFor={dueDateId}>
                                                Expected Due Date
                                                {isActiveRequest && <span className="text-destructive"> *</span>}
                                            </Label>
                                            <FormattedDatePicker
                                                id={dueDateId}
                                                value={data.due_date}
                                                onChange={(value) => {
                                                    setData('due_date', value);
                                                    clearErrors('due_date');
                                                }}
                                                className="mt-1.5"
                                                required={isActiveRequest}
                                            />
                                            {errors.due_date && <p className="mt-1 text-xs text-destructive">{errors.due_date}</p>}
                                        </div>
                                    )}

                                    {requiresChildBirthDate && (
                                        <div>
                                            <Label htmlFor={childBirthDateId}>
                                                Child&apos;s Date of Birth
                                                {isActiveRequest && <span className="text-destructive"> *</span>}
                                            </Label>
                                            <FormattedDatePicker
                                                id={childBirthDateId}
                                                value={data.child_birth_date}
                                                onChange={(value) => {
                                                    setData('child_birth_date', value);
                                                    clearErrors('child_birth_date');
                                                }}
                                                className="mt-1.5"
                                                required={isActiveRequest}
                                            />
                                            {isParentalLeave && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    The leave start date must be within six months of the child&apos;s birth.
                                                </p>
                                            )}
                                            {errors.child_birth_date && (
                                                <p className="mt-1 text-xs text-destructive">{errors.child_birth_date}</p>
                                            )}
                                        </div>
                                    )}

                                    <div>
                                        <Label htmlFor={statusSelectId}>Request Status</Label>
                                        <select
                                            id={statusSelectId}
                                            value={data.status}
                                            onChange={(e) => setData('status', e.target.value)}
                                            className="mt-1.5 h-10 w-full rounded-lg border border-input bg-white px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                            required
                                        >
                                            <option value="applied">Applied (Pending Review)</option>
                                            <option value="approved">Approved (Deducts Balance)</option>
                                            <option value="cancelled">Cancelled (Refunds Balance)</option>
                                            <option value="rejected">Rejected (No Balance Deduction)</option>
                                        </select>
                                        {errors.status && (
                                            <p className="mt-1 text-xs text-destructive">{errors.status}</p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Dates and Type Card */}
                            <div className="rounded-xl border border-border bg-white p-6 shadow-xs">
                                <h3 className="mb-4 text-base font-semibold text-foreground flex items-center gap-2">
                                    <Calendar className="size-4 text-primary" />
                                    Leave Type & Duration
                                </h3>

                                <div className="space-y-4">
                                    <div>
                                        <Label htmlFor={leaveTypeSelectId}>Leave Type</Label>
                                        <Input
                                            id={leaveTypeSelectId}
                                            type="text"
                                            value={selectedLeaveType?.leave_name ?? 'Leave type unavailable'}
                                            disabled
                                            className="mt-1.5 bg-muted text-muted-foreground"
                                        />
                                        {errors.leave_type_id && (
                                            <p className="mt-1 text-xs text-destructive">{errors.leave_type_id}</p>
                                        )}
                                    </div>

                                    <div>
                                        <Label htmlFor={requestTypeSelectId}>Leave duration type</Label>
                                        <select
                                            id={requestTypeSelectId}
                                            value={data.request_type}
                                            onChange={(e) => setData('request_type', e.target.value)}
                                            className="mt-1.5 h-10 w-full rounded-lg border border-input bg-white px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                            required
                                        >
                                            <option value="fullday">Full day</option>
                                            <option value="firsthalf">First half</option>
                                            <option value="secondhalf">Second half</option>
                                        </select>
                                        {data.request_type !== 'fullday' && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Half-day leave must cover one date only.
                                            </p>
                                        )}
                                        {errors.request_type && (
                                            <p className="mt-1 text-xs text-destructive">{errors.request_type}</p>
                                        )}
                                    </div>

                                    {isCompassionateLeave && (
                                        <div>
                                            <Label htmlFor="family_relationship">Family Relationship</Label>
                                            <select
                                                id="family_relationship"
                                                value={data.family_relationship}
                                                onChange={(event) => {
                                                    setData('family_relationship', event.target.value);
                                                    clearErrors('family_relationship', 'total_days');
                                                }}
                                                className="mt-1.5 h-10 w-full rounded-lg border border-input bg-white px-3 text-sm shadow-xs"
                                                required={isActiveRequest}
                                            >
                                                <option value="">-- Choose Relationship --</option>
                                                {Object.entries(compassionateRelationships).map(([key, option]) => (
                                                    <option key={key} value={key}>{option.label} - maximum {option.max_days} days</option>
                                                ))}
                                            </select>
                                            <p className="mt-1 text-xs text-muted-foreground">Limits apply per request using the leave days calculated from your dates.</p>
                                            {errors.family_relationship && <p className="mt-1 text-xs text-destructive">{errors.family_relationship}</p>}
                                        </div>
                                    )}

                                    {isFestivalLeave && (
                                        <div>
                                            <Label htmlFor="festival_id">Festival</Label>
                                            <select
                                                id="festival_id"
                                                value={data.festival_id}
                                                onChange={(event) => {
                                                    const festival = eligibleFestivals.find((item) => String(item.id) === event.target.value);
                                                    setData('festival_id', event.target.value);

                                                    if (festival?.start_date) {
                                                        setData('start_date', festival.start_date);
                                                        setData('end_date', festival.end_date || festival.start_date);
                                                    }

                                                    clearErrors('festival_id', 'start_date', 'end_date');
                                                }}
                                                className="mt-1.5 h-10 w-full rounded-lg border border-input bg-white px-3 text-sm shadow-xs"
                                                required={isActiveRequest}
                                            >
                                                <option value="">-- Choose Festival --</option>
                                                {eligibleFestivals.map((festival) => (
                                                    <option key={festival.id} value={festival.id}>{festival.name}</option>
                                                ))}
                                            </select>
                                            {errors.festival_id && <p className="mt-1 text-xs text-destructive">{errors.festival_id}</p>}
                                        </div>
                                    )}

                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <div>
                                            <Label htmlFor={startDateId}>Start Date</Label>
                                            <FormattedDatePicker
                                                id={startDateId}
                                                value={data.start_date}
                                                onChange={(value) => setData('start_date', value)}
                                                className="mt-1.5"
                                                required
                                            />
                                            {errors.start_date && (
                                                <p className="mt-1 text-xs text-destructive">{errors.start_date}</p>
                                            )}
                                        </div>

                                        <div>
                                            <Label htmlFor={endDateId}>End Date</Label>
                                            <FormattedDatePicker
                                                id={endDateId}
                                                value={data.end_date}
                                                min={data.start_date}
                                                max={data.request_type === 'fullday' ? undefined : data.start_date}
                                                onChange={(value) => setData('end_date', value)}
                                                className="mt-1.5"
                                                required
                                            />
                                            {errors.end_date && (
                                                <p className="mt-1 text-xs text-destructive">{errors.end_date}</p>
                                            )}
                                        </div>

                                        <div>
                                            <Label htmlFor={totalDaysId}>Total Days</Label>
                                            <Input
                                                id={totalDaysId}
                                                type="number"
                                                step="0.5"
                                                min="0"
                                                value={data.total_days}
                                                readOnly={data.request_type === 'fullday'}
                                                disabled={data.request_type !== 'fullday'}
                                                onChange={(e) => setData('total_days', e.target.value)}
                                                className="mt-1.5 font-semibold text-primary"
                                                required
                                            />
                                            {errors.total_days && (
                                                <p className="mt-1 text-xs text-destructive">{errors.total_days}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div>
                                        <Label htmlFor={remarksId}>HR Remarks / Reason</Label>
                                        <Textarea
                                            id={remarksId}
                                            value={data.remarks}
                                            onChange={(e) => setData('remarks', e.target.value)}
                                            placeholder="Provide reason for editing or remarks..."
                                            className="mt-1.5 min-h-[80px]"
                                        />
                                        {errors.remarks && (
                                            <p className="mt-1 text-xs text-destructive">{errors.remarks}</p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Handover Section */}
                            {requiresHandover && (
                                <div className="rounded-xl border border-border bg-white p-6 shadow-xs">
                                    <h3 className="mb-2 text-base font-semibold text-foreground flex items-center gap-2">
                                        <Clock className="size-4 text-primary" />
                                        Handover / Delegation Details
                                    </h3>
                                    <p className="mb-4 text-xs text-muted-foreground">
                                        Specify a colleague to take over duties during this leave period.
                                    </p>

                                    <div className="space-y-4">
                                        <div>
                                            <Label htmlFor={handoverPersonSelectId}>
                                                Handover Person <span className="ml-1 text-destructive">*</span>
                                            </Label>
                                            <select
                                                id={handoverPersonSelectId}
                                                value={data.handover_person_id}
                                                onChange={(e) => {
                                                    setData('handover_person_id', e.target.value);
                                                    clearErrors('handover_person_id');
                                                }}
                                                required
                                                className="mt-1.5 h-10 w-full rounded-lg border border-input bg-white px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                            >
                                                <option value="">-- Select a handover person --</option>
                                                {candidateHandoverEmployees.map((emp) => {
                                                    const onLeave = handoverAvailabilityMap.get(emp.id) === true;

                                                    return (
                                                        <option
                                                            key={emp.id}
                                                            value={emp.id}
                                                            disabled={onLeave}
                                                        >
                                                            {emp.user?.name ?? 'Unknown'} ({emp.designation ?? 'Staff'}) {onLeave ? '— (On leave on these dates)' : ''}
                                                        </option>
                                                    );
                                                })}
                                            </select>
                                            {isHandoverPersonConflicted && (
                                                <p className="mt-1 text-xs text-destructive font-medium flex items-center gap-1">
                                                    <AlertCircle className="size-3.5" />
                                                    This colleague is also on leave during the requested dates. Please select someone else.
                                                </p>
                                            )}
                                            {errors.handover_person_id && (
                                                <p className="mt-1 text-xs text-destructive">{errors.handover_person_id}</p>
                                            )}
                                        </div>

                                        {data.handover_person_id && (
                                            <div>
                                                <Label htmlFor={handoverDescId}>
                                                    Handover Tasks & Instructions <span className="ml-1 text-destructive">*</span>
                                                </Label>
                                                <Textarea
                                                    id={handoverDescId}
                                                    value={data.handover_description}
                                                    onChange={(e) => {
                                                        setData('handover_description', e.target.value);
                                                        clearErrors('handover_description');
                                                    }}
                                                    required
                                                    placeholder="Key duties, urgent contacts, pending tickets..."
                                                    className="mt-1.5 min-h-[80px]"
                                                />
                                                {errors.handover_description && (
                                                    <p className="mt-1 text-xs text-destructive">{errors.handover_description}</p>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Sidebar: Balances & Attachments */}
                        <div className="space-y-6">
                            {/* Current Leave Balance Widget */}
                            <div className="rounded-xl border border-border bg-white p-6 shadow-xs">
                                <h4 className="mb-3 text-sm font-semibold text-foreground flex items-center gap-2">
                                    <Clock className="size-4 text-muted-foreground" />
                                    Quota Balance Overview
                                </h4>

                                {selectedBalance ? (
                                    <div className="space-y-3 rounded-lg bg-muted/40 p-4">
                                        <div className="flex justify-between text-xs">
                                            <span className="text-muted-foreground">Allocated</span>
                                            <span className="font-medium text-foreground">{selectedBalance.allocated} days</span>
                                        </div>
                                        <div className="flex justify-between text-xs">
                                            <span className="text-muted-foreground">Used</span>
                                            <span className="font-medium text-foreground">{selectedBalance.used} days</span>
                                        </div>
                                        <div className="border-t border-border pt-2 flex justify-between text-sm font-bold">
                                            <span>Current Balance</span>
                                            <span className="text-primary">{selectedBalance.balance} days</span>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-xs text-muted-foreground">
                                        No balance record found for this employee and leave type.
                                    </p>
                                )}
                            </div>

                            {selectedLeaveType && (
                                <div className="rounded-xl border border-border bg-white p-6 shadow-xs">
                                    <h4 className="text-sm font-semibold text-foreground">
                                        Policy Annual Limit
                                    </h4>
                                    {selectedPolicy?.annual_limit !== null && selectedPolicy?.annual_limit !== undefined ? (
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {selectedPolicy.annual_limit} days per year
                                        </p>
                                    ) : (
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            No annual limit configured for this organisation.
                                        </p>
                                    )}
                                </div>
                            )}

                            {/* Attachments / Certificate */}
                            {(requiresAttachment || hasExistingAttachment) && (
                                <div className="rounded-xl border border-border bg-white p-6 shadow-xs">
                                    <h4 className="mb-3 text-sm font-semibold text-foreground flex items-center gap-2">
                                        <FileUp className="size-4 text-muted-foreground" />
                                        Supporting Document
                                    </h4>

                                    {leaveRequest.files && leaveRequest.files.length > 0 && (
                                        <div className="mb-4 space-y-2">
                                            <p className="text-xs text-muted-foreground font-medium">Existing Attachment:</p>
                                            {leaveRequest.files.map((f) => (
                                                <a
                                                    key={f.id}
                                                    href={`/storage/${f.file_path}`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="flex items-center gap-2 rounded-md border border-border p-2 text-xs font-medium text-primary hover:underline"
                                                >
                                                    <FileText className="size-4" />
                                                    View Uploaded Document
                                                </a>
                                            ))}
                                        </div>
                                    )}

                                    {requiresAttachment && (
                                        <div>
                                            <Label htmlFor={certificateId} className="text-xs text-muted-foreground">
                                                Upload Replacement or New Document
                                                {!hasExistingAttachment && (
                                                    <span className="ml-1 text-destructive">*</span>
                                                )}
                                            </Label>
                                            <Input
                                                id={certificateId}
                                                type="file"
                                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                                required={requiresAttachment && !hasExistingAttachment}
                                                onChange={(e) => {
                                                    setData('certificate', e.target.files?.[0] ?? null);
                                                    clearErrors('certificate');
                                                }}
                                                className="mt-1.5 text-xs"
                                            />
                                            {errors.certificate && (
                                                <p className="mt-1 text-xs text-destructive">{errors.certificate}</p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Save Actions */}
                            <div className="flex flex-col gap-2">
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing || isHandoverPersonConflicted}
                                >
                                    <Save className="mr-2 size-4" />
                                    {processing ? 'Saving...' : 'Save & Update Leave'}
                                </Button>
                                <Button variant="outline" asChild className="w-full">
                                    <Link href="/leave-requests">Cancel & Discard</Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}

LeaveRequestsEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave Requests', href: '/leave-requests' },
        { title: 'Edit Leave Request', href: '#' },
    ],
};
