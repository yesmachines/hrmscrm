import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    Send,
    User as UserIcon,
    UserCheck,
    AlertCircle,
    CheckCircle2,
    Clock,
    FileUp,
    ShieldAlert,
    ChevronDown,
} from 'lucide-react';
import { useState, useId, useMemo, useEffect, useRef } from 'react';
import { FormattedDatePicker } from '@/components/formatted-date-picker';
import HandoverTasksInput from '@/components/handover-tasks-input';
import Heading from '@/components/heading';
import LeaveTemplatePreview from '@/components/leave-template-preview';
import LeaveTypeFields from '@/components/leave-type-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    hasHandoverTasks,
    normalizeHandoverDescription,
} from '@/lib/handover-tasks';
import {
    isLeaveFieldRequired,
    leaveValidationFormData,
} from '@/lib/leave-application';
import type { LeaveFieldDefinition } from '@/lib/leave-application';
import { countLeaveWeekdays } from '@/lib/leave-duration';
import { getLeavePolicyRequirements } from '@/lib/leave-policy-requirements';
import type { LeaveApplicationRequirements } from '@/lib/leave-policy-requirements';
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
    field_definitions: LeaveFieldDefinition[];
    application_requirements: LeaveApplicationRequirements;
    is_paid: boolean;
    requires_approval: boolean;
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
    requires_weekend_document: boolean;
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

interface Props {
    employees: EmployeeOption[];
    leaveTypes: LeaveTypeOption[];
    leaveBalances: LeaveBalanceItem[];
    activeLeaves?: ActiveLeaveItem[];
    festivals?: FestivalOption[];
    leaveTemplates?: {
        leave_type_id: number;
        organisation_id: number | null;
    }[];
    compassionateRelationships: Record<
        string,
        { label: string; group: string; max_days: number }
    >;
}

function dateOnly(value: string | null | undefined): string | null {
    const normalizedDate = value?.slice(0, 10);

    return normalizedDate && /^\d{4}-\d{2}-\d{2}$/.test(normalizedDate)
        ? normalizedDate
        : null;
}

export default function LeaveRequestsCreate({
    employees = [],
    leaveTypes = [],
    leaveBalances = [],
    activeLeaves = [],
    festivals = [],
    leaveTemplates = [],
}: Props) {
    const now = new Date();
    const today = [
        now.getFullYear(),
        String(now.getMonth() + 1).padStart(2, '0'),
        String(now.getDate()).padStart(2, '0'),
    ].join('-');
    const employeeSelectId = useId();
    const employeeListboxId = useId();
    const leaveTypeSelectId = useId();
    const requestTypeSelectId = useId();
    const startDateId = useId();
    const endDateId = useId();
    const totalDaysId = useId();
    const remarksId = useId();
    const certificateId = useId();
    const handoverPersonSelectId = useId();
    const handoverDescId = useId();

    const [employeeSearch, setEmployeeSearch] = useState('');
    const [employeeDropdownOpen, setEmployeeDropdownOpen] = useState(false);
    const [activeEmployeeIndex, setActiveEmployeeIndex] = useState(0);
    const [employeeSelectionError, setEmployeeSelectionError] = useState('');
    const [hasAttemptedSubmit, setHasAttemptedSubmit] = useState(false);
    const employeeInputRef = useRef<HTMLInputElement>(null);
    const certificateInputRef = useRef<HTMLInputElement>(null);
    const submissionStatus = useRef<'applied' | 'approved'>('applied');

    const {
        data,
        setData,
        post,
        transform,
        processing,
        errors,
        clearErrors,
        setError,
    } = useForm({
        employee_id: '',
        leave_type_id: '',
        request_type: 'fullday',
        start_date: today,
        end_date: today,
        total_days: '',
        status: 'applied',
        remarks: '',
        certificate: null as File | null,
        handover_person_id: '',
        handover_description: '',
        handover_other_name: '',
        handover_other_organisation: '',
        handover_other_designation: '',
        handover_other_contact_number: '',
        child_birth_date: '',
        due_date: '',
        festival_id: '',
        family_relationship: '',
        local_contact_no: '',
        traveling_outside_country: '',
        destination: '',
        travel_contact_no: '',
        ticket_status: '',
    });

    const filteredEmployees = useMemo(() => {
        if (!employeeSearch.trim()) {
            return employees;
        }

        const term = employeeSearch.trim().toLowerCase();

        return employees.filter((emp) => {
            const name = emp.user?.name?.toLowerCase() ?? '';
            const code = emp.employee_code?.toLowerCase() ?? '';
            const num = emp.emp_num?.toLowerCase() ?? '';
            const desig = emp.designation?.toLowerCase() ?? '';
            const dept = emp.department?.name?.toLowerCase() ?? '';

            return (
                name.includes(term) ||
                code.includes(term) ||
                num.includes(term) ||
                desig.includes(term) ||
                dept.includes(term)
            );
        });
    }, [employees, employeeSearch]);

    // Calculate days between start and end date
    useEffect(() => {
        const startDate = dateOnly(data.start_date);
        const endDate = dateOnly(data.end_date);

        if (!startDate || !endDate || endDate < startDate) {
            if (data.total_days !== '') {
                setData('total_days', '');
            }

            return;
        }

        const weekdays = countLeaveWeekdays(startDate, endDate);
        const totalDays =
            data.request_type === 'fullday' ? String(weekdays) : '0.5';

        if (data.total_days !== totalDays) {
            setData('total_days', totalDays);
        }
    }, [
        data.start_date,
        data.end_date,
        data.request_type,
        data.total_days,
        setData,
    ]);

    // Find current balance for selected employee and leave type
    const selectedBalance = useMemo(() => {
        if (!data.employee_id || !data.leave_type_id) {
            return null;
        }

        const empId = Number(data.employee_id);
        const typeId = Number(data.leave_type_id);

        return (
            leaveBalances.find(
                (b) => b.employee_id === empId && b.leave_type_id === typeId,
            ) ?? null
        );
    }, [data.employee_id, data.leave_type_id, leaveBalances]);

    const selectedEmployee = useMemo(() => {
        if (!data.employee_id) {
            return null;
        }

        return (
            employees.find((e) => String(e.id) === String(data.employee_id)) ??
            null
        );
    }, [data.employee_id, employees]);

    const clearPolicyDependentValues = () => {
        setHasAttemptedSubmit(false);
        setData('certificate', null);
        setData('handover_person_id', '');
        setData('handover_description', '');
        setData('handover_other_name', '');
        setData('handover_other_organisation', '');
        setData('handover_other_designation', '');
        setData('handover_other_contact_number', '');
        setData('child_birth_date', '');
        setData('due_date', '');
        setData('festival_id', '');
        setData('family_relationship', '');
        setData('local_contact_no', '');
        setData('traveling_outside_country', '');
        setData('destination', '');
        setData('travel_contact_no', '');
        setData('ticket_status', '');

        if (certificateInputRef.current) {
            certificateInputRef.current.value = '';
        }

        clearErrors(
            'certificate',
            'handover_person_id',
            'handover_description',
            'handover_other_name',
            'handover_other_organisation',
            'handover_other_designation',
            'handover_other_contact_number',
            'child_birth_date',
            'due_date',
            'local_contact_no',
            'traveling_outside_country',
            'destination',
            'travel_contact_no',
            'ticket_status',
        );
    };

    const selectEmployee = (employee: EmployeeOption) => {
        setData('employee_id', String(employee.id));
        setData('leave_type_id', '');
        clearPolicyDependentValues();
        setEmployeeSearch('');
        setEmployeeDropdownOpen(false);
        setActiveEmployeeIndex(0);
        setEmployeeSelectionError('');
        clearErrors(
            'employee_id',
            'leave_type_id',
            'start_date',
            'end_date',
            'total_days',
        );
    };

    const availableLeaveTypes = useMemo(() => {
        if (!selectedEmployee) {
            return [];
        }

        return leaveTypes.filter((type) =>
            type.eligible_employee_ids.includes(selectedEmployee.id),
        );
    }, [selectedEmployee, leaveTypes]);

    useEffect(() => {
        if (
            data.leave_type_id &&
            !availableLeaveTypes.some(
                (lt) => String(lt.id) === String(data.leave_type_id),
            )
        ) {
            setData('leave_type_id', '');
        }
    }, [availableLeaveTypes, data.leave_type_id, setData]);

    const selectedLeaveType = useMemo(() => {
        if (!data.leave_type_id) {
            return null;
        }

        return (
            availableLeaveTypes.find(
                (lt) => String(lt.id) === String(data.leave_type_id),
            ) ?? null
        );
    }, [data.leave_type_id, availableLeaveTypes]);
    const hasLeaveTemplate = Boolean(
        selectedEmployee &&
        selectedLeaveType &&
        leaveTemplates.some(
            (template) =>
                template.leave_type_id === selectedLeaveType.id &&
                (template.organisation_id === null ||
                    template.organisation_id ===
                        selectedEmployee.organisation_id),
        ),
    );
    const templatePreviewValues = useMemo(
        () => ({
            start_date: data.start_date,
            end_date: data.end_date,
            request_type: data.request_type,
            total_days: data.total_days,
            remarks: data.remarks,
            local_contact_no: data.local_contact_no,
            traveling_outside_country: data.traveling_outside_country,
            destination: data.destination,
            travel_contact_no: data.travel_contact_no,
            ticket_status: data.ticket_status,
            handover_person_id:
                data.handover_person_id && data.handover_person_id !== 'other'
                    ? data.handover_person_id
                    : null,
            handover_description: data.handover_description,
            handover_other_name: data.handover_other_name,
            handover_other_organisation: data.handover_other_organisation,
            handover_other_designation: data.handover_other_designation,
            handover_other_contact_number: data.handover_other_contact_number,
        }),
        [
            data.destination,
            data.end_date,
            data.handover_description,
            data.handover_person_id,
            data.handover_other_name,
            data.handover_other_organisation,
            data.handover_other_designation,
            data.handover_other_contact_number,
            data.local_contact_no,
            data.remarks,
            data.request_type,
            data.start_date,
            data.ticket_status,
            data.total_days,
            data.travel_contact_no,
            data.traveling_outside_country,
        ],
    );
    const detailFields = selectedLeaveType?.field_definitions ?? [];
    const eligibleFestivals = useMemo(() => {
        if (!selectedEmployee) {
            return [];
        }

        return festivals.filter((festival) =>
            festival.eligible_employee_ids.includes(selectedEmployee.id),
        );
    }, [festivals, selectedEmployee]);

    const selectedPolicy = useMemo(() => {
        if (!selectedLeaveType || selectedEmployee?.organisation_id == null) {
            return null;
        }

        return (
            selectedLeaveType.policies?.find(
                (policy) =>
                    Number(policy.organisation_id) ===
                    Number(selectedEmployee?.organisation_id),
            ) ?? null
        );
    }, [selectedEmployee, selectedLeaveType]);

    const { requiresAttachment, requiresHandover } = getLeavePolicyRequirements(
        selectedPolicy,
        Number(data.total_days || 0),
        data.start_date,
        data.end_date,
        selectedLeaveType?.application_requirements,
    );
    const eligibilityPayload = useMemo(() => {
        if (
            !selectedEmployee ||
            !selectedLeaveType ||
            !data.start_date ||
            !data.end_date
        ) {
            return null;
        }

        return {
            employee_id: selectedEmployee.id,
            leave_type_id: selectedLeaveType.id,
            request_type: data.request_type,
            start_date: data.start_date,
            end_date: data.end_date,
            total_days: data.total_days,
            remarks: data.remarks,
            child_birth_date: data.child_birth_date || null,
            due_date: data.due_date || null,
            festival_id: data.festival_id || null,
            family_relationship: data.family_relationship || null,
            local_contact_no: data.local_contact_no || null,
            traveling_outside_country: data.traveling_outside_country || null,
            destination: data.destination || null,
            travel_contact_no: data.travel_contact_no || null,
            ticket_status: data.ticket_status || null,
            handover_person_id:
                data.handover_person_id && data.handover_person_id !== 'other'
                    ? data.handover_person_id
                    : null,
            handover_description: data.handover_description || null,
            handover_other_name: data.handover_other_name || null,
            handover_other_organisation:
                data.handover_other_organisation || null,
            handover_other_designation: data.handover_other_designation || null,
            handover_other_contact_number:
                data.handover_other_contact_number || null,
            certificate: data.certificate,
        };
    }, [
        data.child_birth_date,
        data.certificate,
        data.due_date,
        data.festival_id,
        data.family_relationship,
        data.local_contact_no,
        data.traveling_outside_country,
        data.destination,
        data.travel_contact_no,
        data.ticket_status,
        data.end_date,
        data.handover_description,
        data.handover_person_id,
        data.handover_other_name,
        data.handover_other_organisation,
        data.handover_other_designation,
        data.handover_other_contact_number,
        data.request_type,
        data.remarks,
        data.start_date,
        data.total_days,
        selectedEmployee,
        selectedLeaveType,
    ]);
    useEffect(() => {
        if (!hasAttemptedSubmit || !eligibilityPayload) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch('/leave-requests/validate', {
                method: 'POST',
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content ?? '',
                },
                body: leaveValidationFormData(eligibilityPayload),
            })
                .then(async (response) => {
                    if (!response.ok && response.status !== 422) {
                        throw new Error(
                            'Unable to validate the leave request.',
                        );
                    }

                    const result = (await response.json()) as {
                        valid: boolean;
                        errors?: Record<string, string[]>;
                    };

                    if (controller.signal.aborted) {
                        return;
                    }

                    clearErrors();

                    if (!result.valid && result.errors) {
                        for (const [field, messages] of Object.entries(
                            result.errors,
                        )) {
                            if (messages[0]) {
                                setError(
                                    field as keyof typeof data,
                                    messages[0],
                                );
                            }
                        }
                    }
                })
                .catch((error: unknown) => {
                    if (error instanceof Error && error.name !== 'AbortError') {
                        console.error(error);
                    }
                });
        }, 300);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [clearErrors, eligibilityPayload, hasAttemptedSubmit, setError]);

    useEffect(() => {
        if (!requiresAttachment && data.certificate) {
            setData('certificate', null);

            if (certificateInputRef.current) {
                certificateInputRef.current.value = '';
            }

            clearErrors('certificate');
        }
    }, [clearErrors, data.certificate, requiresAttachment, setData]);

    useEffect(() => {
        if (!requiresHandover) {
            if (data.handover_person_id) {
                setData('handover_person_id', '');
            }

            if (data.handover_description) {
                setData('handover_description', '');
            }

            if (data.handover_other_name) {
                setData('handover_other_name', '');
            }

            if (data.handover_other_organisation) {
                setData('handover_other_organisation', '');
            }

            if (data.handover_other_designation) {
                setData('handover_other_designation', '');
            }

            if (data.handover_other_contact_number) {
                setData('handover_other_contact_number', '');
            }

            clearErrors(
                'handover_person_id',
                'handover_description',
                'handover_other_name',
                'handover_other_organisation',
                'handover_other_designation',
                'handover_other_contact_number',
            );
        }
    }, [
        clearErrors,
        data.handover_description,
        data.handover_person_id,
        data.handover_other_name,
        data.handover_other_organisation,
        data.handover_other_designation,
        data.handover_other_contact_number,
        requiresHandover,
        setData,
    ]);

    useEffect(() => {
        if (selectedLeaveType) {
            setData(
                'status',
                selectedLeaveType.requires_approval ? 'applied' : 'approved',
            );
        }
    }, [selectedLeaveType, setData]);

    const candidateHandoverEmployees = useMemo(() => {
        if (!data.employee_id) {
            return employees;
        }

        const currentEmpId = Number(data.employee_id);

        return employees.filter((emp) => emp.id !== currentEmpId);
    }, [data.employee_id, employees]);

    const isEmployeeOnLeave = (empId: number): boolean => {
        const requestStart = dateOnly(data.start_date);
        const requestEnd = dateOnly(data.end_date);

        if (!requestStart || !requestEnd || requestEnd < requestStart) {
            return false;
        }

        return activeLeaves.some((leave) => {
            if (
                leave.employee_id !== empId ||
                !['applied', 'approved'].includes(leave.status)
            ) {
                return false;
            }

            const leaveStart = dateOnly(leave.start_date);
            const leaveEnd = dateOnly(leave.end_date);

            return (
                leaveStart !== null &&
                leaveEnd !== null &&
                leaveStart <= requestEnd &&
                leaveEnd >= requestStart
            );
        });
    };

    const hasApplicantOverlap = selectedEmployee
        ? isEmployeeOnLeave(selectedEmployee.id)
        : false;
    const isOtherHandover = data.handover_person_id === 'other';
    const isSelectedHandoverOnLeave =
        Boolean(data.handover_person_id) &&
        data.handover_person_id !== 'other' &&
        isEmployeeOnLeave(Number(data.handover_person_id));

    useEffect(() => {
        if (
            data.handover_person_id &&
            data.handover_person_id !== 'other' &&
            String(data.employee_id) === String(data.handover_person_id)
        ) {
            setData('handover_person_id', '');
        }
    }, [data.employee_id, data.handover_person_id, setData]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setHasAttemptedSubmit(true);
        clearErrors();
        setEmployeeSelectionError('');

        if (!data.employee_id || !selectedEmployee) {
            const message = 'Please select an employee first.';
            setEmployeeSelectionError(message);
            setError('employee_id', message);
            setEmployeeDropdownOpen(true);
            employeeInputRef.current?.focus();

            return;
        }

        if (!data.leave_type_id || !selectedLeaveType) {
            setError('leave_type_id', 'Please select a leave type.');

            return;
        }

        for (const field of detailFields) {
            if (isLeaveFieldRequired(field, data) && !data[field.key]) {
                setError(field.key, `${field.label} is required.`);

                return;
            }
        }

        const startDate = dateOnly(data.start_date);
        const endDate = dateOnly(data.end_date);

        if (!startDate) {
            setError('start_date', 'Please select a start date.');

            return;
        }

        if (!endDate) {
            setError('end_date', 'Please select an end date.');

            return;
        }

        if (endDate < startDate) {
            setError(
                'end_date',
                'End date must be on or after the start date.',
            );

            return;
        }

        if (data.request_type !== 'fullday' && startDate !== endDate) {
            setError(
                'request_type',
                'Half-day leave must start and end on the same date.',
            );

            return;
        }

        if (
            data.request_type !== 'fullday' &&
            countLeaveWeekdays(startDate, startDate) === 0
        ) {
            setError('request_type', 'Half-day leave must be on a weekday.');

            return;
        }

        const totalDays =
            data.request_type === 'fullday' ? Number(data.total_days) : 0.5;

        if (!Number.isFinite(totalDays) || totalDays < 0.5) {
            setError('total_days', 'Leave duration must be at least 0.5 days.');

            return;
        }

        if (hasApplicantOverlap) {
            setError(
                'start_date',
                'This employee already has an applied or approved leave request overlapping these dates.',
            );

            return;
        }

        const policyRequirements = getLeavePolicyRequirements(
            selectedPolicy,
            totalDays,
            startDate,
            endDate,
            selectedLeaveType?.application_requirements,
        );

        if (policyRequirements.requiresHandover && !data.handover_person_id) {
            setError(
                'handover_person_id',
                'A handover colleague is required by this leave policy.',
            );

            return;
        }

        if (
            policyRequirements.requiresHandover &&
            data.handover_person_id === 'other'
        ) {
            const externalHandoverFields = [
                ['handover_other_name', data.handover_other_name],
                [
                    'handover_other_organisation',
                    data.handover_other_organisation,
                ],
                ['handover_other_designation', data.handover_other_designation],
                [
                    'handover_other_contact_number',
                    data.handover_other_contact_number,
                ],
            ] as const;
            const missingField = externalHandoverFields.find(
                ([, value]) => !value.trim(),
            );

            if (missingField) {
                setError(
                    missingField[0],
                    'This field is required for an external handover contact.',
                );

                return;
            }
        }

        if (
            data.handover_person_id &&
            Number(data.handover_person_id) === selectedEmployee.id
        ) {
            setError(
                'handover_person_id',
                'You cannot select the employee themselves as the handover colleague.',
            );

            return;
        }

        if (
            data.handover_person_id &&
            isEmployeeOnLeave(Number(data.handover_person_id))
        ) {
            setError(
                'handover_person_id',
                'The selected handover colleague is on leave during these dates.',
            );

            return;
        }

        if (
            policyRequirements.requiresHandover &&
            !hasHandoverTasks(data.handover_description)
        ) {
            setError(
                'handover_description',
                'Add at least one handover task as required by this leave policy.',
            );

            return;
        }

        if (policyRequirements.requiresAttachment && !data.certificate) {
            setError(
                'certificate',
                'A supporting document is required by this leave policy.',
            );

            return;
        }

        if (
            data.certificate &&
            (data.certificate.size > 10 * 1024 * 1024 ||
                !/\.(pdf|jpe?g|png|docx?)$/i.test(data.certificate.name))
        ) {
            setError(
                'certificate',
                'Choose a PDF, JPG, PNG, DOC or DOCX file no larger than 10 MB.',
            );

            return;
        }

        transform((formData) => ({
            ...formData,
            handover_person_id:
                formData.handover_person_id === 'other'
                    ? ''
                    : formData.handover_person_id,
            handover_description: normalizeHandoverDescription(
                formData.handover_description,
            ),
            status: submissionStatus.current,
        }));
        post('/leave-requests');
    };

    return (
        <>
            <Head title="Apply Leave for Employee" />

            <div className="mx-auto flex w-full max-w-full flex-1 flex-col gap-6 p-6 md:p-8 2xl:max-w-[1200px]">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href="/leave-requests">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <Heading
                        title="Apply Leave on Behalf of Employee"
                        description="Submit or directly record an approved leave for an employee as HR"
                    />
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Main Form */}
                    <div className="rounded-2xl border border-border bg-white p-6 shadow-sm lg:col-span-2">
                        <form
                            onSubmit={submit}
                            noValidate
                            className="space-y-6"
                        >
                            {/* Employee Selection */}
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <Label
                                        htmlFor={employeeSelectId}
                                        className="flex items-center gap-1.5 font-medium"
                                    >
                                        <UserIcon className="size-4 text-primary" />
                                        Select Employee{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <span className="text-xs text-muted-foreground">
                                        {filteredEmployees.length} of{' '}
                                        {employees.length} available
                                    </span>
                                </div>

                                <div className="relative">
                                    <Input
                                        ref={employeeInputRef}
                                        id={employeeSelectId}
                                        type="text"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-expanded={employeeDropdownOpen}
                                        aria-controls={employeeListboxId}
                                        aria-activedescendant={
                                            employeeDropdownOpen &&
                                            filteredEmployees[
                                                activeEmployeeIndex
                                            ]
                                                ? `${employeeListboxId}-option-${filteredEmployees[activeEmployeeIndex].id}`
                                                : undefined
                                        }
                                        aria-required="true"
                                        aria-invalid={Boolean(
                                            errors.employee_id ||
                                            employeeSelectionError,
                                        )}
                                        placeholder="Search and select an employee..."
                                        value={
                                            employeeDropdownOpen
                                                ? employeeSearch
                                                : selectedEmployee
                                                  ? `${selectedEmployee.user?.name ?? 'Unknown'} (${selectedEmployee.employee_code || selectedEmployee.emp_num})`
                                                  : ''
                                        }
                                        onFocus={() => {
                                            setEmployeeSearch('');
                                            setActiveEmployeeIndex(0);
                                            setEmployeeDropdownOpen(true);
                                        }}
                                        onBlur={() =>
                                            setEmployeeDropdownOpen(false)
                                        }
                                        onChange={(e) => {
                                            setEmployeeSearch(e.target.value);
                                            setData('employee_id', '');
                                            setData('leave_type_id', '');
                                            clearPolicyDependentValues();
                                            setActiveEmployeeIndex(0);
                                            setEmployeeSelectionError('');
                                            clearErrors(
                                                'employee_id',
                                                'leave_type_id',
                                                'start_date',
                                                'end_date',
                                                'total_days',
                                            );
                                            setEmployeeDropdownOpen(true);
                                        }}
                                        onKeyDown={(e) => {
                                            if (e.key === 'ArrowDown') {
                                                e.preventDefault();
                                                setEmployeeDropdownOpen(true);
                                                setActiveEmployeeIndex(
                                                    (index) =>
                                                        Math.min(
                                                            index + 1,
                                                            Math.max(
                                                                filteredEmployees.length -
                                                                    1,
                                                                0,
                                                            ),
                                                        ),
                                                );
                                            } else if (e.key === 'ArrowUp') {
                                                e.preventDefault();
                                                setActiveEmployeeIndex(
                                                    (index) =>
                                                        Math.max(index - 1, 0),
                                                );
                                            } else if (
                                                e.key === 'Enter' &&
                                                employeeDropdownOpen
                                            ) {
                                                e.preventDefault();
                                                const employee =
                                                    filteredEmployees[
                                                        activeEmployeeIndex
                                                    ];

                                                if (employee) {
                                                    selectEmployee(employee);
                                                }
                                            } else if (e.key === 'Escape') {
                                                setEmployeeDropdownOpen(false);
                                                setEmployeeSearch('');
                                            }
                                        }}
                                        className="pr-10"
                                    />
                                    <ChevronDown
                                        aria-hidden="true"
                                        className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    />

                                    {employeeDropdownOpen && (
                                        <div
                                            id={employeeListboxId}
                                            role="listbox"
                                            aria-label="Employees"
                                            className="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-input bg-popover p-1 text-popover-foreground shadow-md"
                                        >
                                            {filteredEmployees.length > 0 ? (
                                                filteredEmployees.map(
                                                    (emp, index) => (
                                                        <button
                                                            key={emp.id}
                                                            id={`${employeeListboxId}-option-${emp.id}`}
                                                            type="button"
                                                            role="option"
                                                            aria-selected={
                                                                String(
                                                                    emp.id,
                                                                ) ===
                                                                data.employee_id
                                                            }
                                                            tabIndex={-1}
                                                            onMouseDown={(e) =>
                                                                e.preventDefault()
                                                            }
                                                            onMouseEnter={() =>
                                                                setActiveEmployeeIndex(
                                                                    index,
                                                                )
                                                            }
                                                            onClick={() =>
                                                                selectEmployee(
                                                                    emp,
                                                                )
                                                            }
                                                            className={`w-full rounded-sm px-3 py-2 text-left text-sm ${
                                                                index ===
                                                                activeEmployeeIndex
                                                                    ? 'bg-accent text-accent-foreground'
                                                                    : 'hover:bg-accent hover:text-accent-foreground'
                                                            }`}
                                                        >
                                                            {emp.user?.name ??
                                                                'Unknown'}{' '}
                                                            (
                                                            {emp.employee_code ||
                                                                emp.emp_num}
                                                            )
                                                            {emp.designation
                                                                ? ` - ${emp.designation}`
                                                                : ''}
                                                            {emp.department
                                                                ?.name
                                                                ? ` [${emp.department.name}]`
                                                                : ''}
                                                        </button>
                                                    ),
                                                )
                                            ) : (
                                                <p className="px-3 py-2 text-sm text-muted-foreground">
                                                    No employees found.
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </div>
                                {(errors.employee_id ||
                                    employeeSelectionError) && (
                                    <p className="text-sm text-destructive">
                                        {errors.employee_id ||
                                            employeeSelectionError}
                                    </p>
                                )}
                            </div>

                            {/* Leave Type Selection */}
                            <div className="space-y-2">
                                <Label
                                    htmlFor={leaveTypeSelectId}
                                    className="flex items-center gap-1.5 font-medium"
                                >
                                    <Calendar className="size-4 text-primary" />
                                    Leave Type{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <select
                                    id={leaveTypeSelectId}
                                    value={data.leave_type_id}
                                    onChange={(e) => {
                                        clearPolicyDependentValues();
                                        setData(
                                            'leave_type_id',
                                            e.target.value,
                                        );
                                        clearErrors('leave_type_id');
                                    }}
                                    disabled={!selectedEmployee}
                                    className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    required
                                >
                                    <option value="">
                                        {selectedEmployee
                                            ? '-- Choose Leave Type --'
                                            : 'Select an employee first'}
                                    </option>
                                    {availableLeaveTypes.map((type) => {
                                        return (
                                            <option
                                                key={type.id}
                                                value={type.id}
                                            >
                                                {type.leave_name}
                                            </option>
                                        );
                                    })}
                                </select>
                                {!selectedEmployee && (
                                    <p className="text-xs text-muted-foreground">
                                        Select an employee first to load leave
                                        types and the policy for their
                                        organisation.
                                    </p>
                                )}
                                {errors.leave_type_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.leave_type_id}
                                    </p>
                                )}
                            </div>

                            <LeaveTypeFields
                                fields={detailFields}
                                values={data}
                                errors={errors}
                                festivals={eligibleFestivals}
                                onChange={(key, value) => {
                                    setData(key, value);
                                    clearErrors(key, 'total_days');

                                    if (
                                        key === 'traveling_outside_country' &&
                                        value === '0'
                                    ) {
                                        setData('destination', '');
                                        setData('travel_contact_no', '');
                                        clearErrors(
                                            'destination',
                                            'travel_contact_no',
                                        );
                                    }
                                }}
                                onFestivalDatesChange={(startDate, endDate) => {
                                    setData('start_date', startDate);
                                    setData(
                                        'end_date',
                                        data.request_type === 'fullday'
                                            ? endDate
                                            : startDate,
                                    );
                                    clearErrors('start_date', 'end_date');
                                }}
                            />

                            {selectedEmployee && selectedLeaveType && (
                                <LeaveTemplatePreview
                                    employeeId={selectedEmployee.id}
                                    leaveTypeId={selectedLeaveType.id}
                                    values={templatePreviewValues}
                                    templateAvailable={hasLeaveTemplate}
                                />
                            )}

                            <div className="space-y-2">
                                <Label htmlFor={requestTypeSelectId}>
                                    Leave duration type
                                </Label>
                                <select
                                    id={requestTypeSelectId}
                                    value={data.request_type}
                                    onChange={(e) => {
                                        const requestType = e.target.value;
                                        setData('request_type', requestType);

                                        if (
                                            requestType !== 'fullday' &&
                                            data.start_date
                                        ) {
                                            setData(
                                                'end_date',
                                                data.start_date,
                                            );
                                        }

                                        clearErrors(
                                            'request_type',
                                            'start_date',
                                            'end_date',
                                            'total_days',
                                        );
                                    }}
                                    className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                                    required
                                >
                                    <option value="fullday">Full day</option>
                                    <option value="firsthalf">
                                        First half
                                    </option>
                                    <option value="secondhalf">
                                        Second half
                                    </option>
                                </select>
                                {data.request_type !== 'fullday' && (
                                    <p className="text-xs text-muted-foreground">
                                        Half-day leave must cover one date only.
                                    </p>
                                )}
                                {errors.request_type && (
                                    <p className="text-sm text-destructive">
                                        {errors.request_type}
                                    </p>
                                )}
                            </div>

                            {/* Dates & Duration Grid */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor={startDateId}>
                                        Start Date{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <FormattedDatePicker
                                        id={startDateId}
                                        value={data.start_date}
                                        onChange={(value) => {
                                            setData('start_date', value);

                                            if (
                                                data.request_type !== 'fullday'
                                            ) {
                                                setData('end_date', value);
                                            }

                                            clearErrors(
                                                'start_date',
                                                'end_date',
                                                'total_days',
                                            );
                                        }}
                                        required
                                    />
                                    {errors.start_date && (
                                        <p className="text-sm text-destructive">
                                            {errors.start_date}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor={endDateId}>
                                        End Date{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <FormattedDatePicker
                                        id={endDateId}
                                        min={data.start_date}
                                        max={
                                            data.request_type === 'fullday'
                                                ? undefined
                                                : data.start_date
                                        }
                                        value={data.end_date}
                                        onChange={(value) => {
                                            setData('end_date', value);
                                            clearErrors(
                                                'start_date',
                                                'end_date',
                                                'total_days',
                                            );
                                        }}
                                        required
                                    />
                                    {errors.end_date && (
                                        <p className="text-sm text-destructive">
                                            {errors.end_date}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor={totalDaysId}>
                                        Total Days
                                    </Label>
                                    <Input
                                        id={totalDaysId}
                                        type="number"
                                        value={data.total_days}
                                        disabled
                                        className="cursor-default disabled:cursor-default disabled:opacity-100"
                                    />
                                    {errors.total_days && (
                                        <p className="text-sm text-destructive">
                                            {errors.total_days}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {hasApplicantOverlap && (
                                <div
                                    role="alert"
                                    className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
                                >
                                    <AlertCircle className="mt-0.5 size-4 shrink-0" />
                                    <span>
                                        This employee already has an applied or
                                        approved leave request overlapping these
                                        dates.
                                    </span>
                                </div>
                            )}

                            {/* Remarks / Reason */}
                            <div className="space-y-2">
                                <Label htmlFor={remarksId}>
                                    Reason / Remarks
                                    {selectedLeaveType?.application_requirements
                                        .remarks_required
                                        ? ' *'
                                        : ''}
                                </Label>
                                <Textarea
                                    id={remarksId}
                                    rows={3}
                                    placeholder="Provide reason for leave, special authorization notes, or handover notes..."
                                    value={data.remarks}
                                    required={
                                        selectedLeaveType
                                            ?.application_requirements
                                            .remarks_required
                                    }
                                    onChange={(e) =>
                                        setData('remarks', e.target.value)
                                    }
                                />
                                {errors.remarks && (
                                    <p className="text-sm text-destructive">
                                        {errors.remarks}
                                    </p>
                                )}
                            </div>

                            {/* Work Handover Section */}
                            {requiresHandover && (
                                <div className="space-y-4 rounded-xl border border-primary/20 bg-primary/5 p-5">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <UserCheck className="size-5 text-primary" />
                                            <h4 className="text-sm font-semibold text-foreground">
                                                Work Handover Details
                                                {requiresHandover && (
                                                    <span className="ml-1 text-destructive">
                                                        *
                                                    </span>
                                                )}
                                            </h4>
                                        </div>
                                        {requiresHandover && (
                                            <span className="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                                                Required by policy
                                            </span>
                                        )}
                                    </div>

                                    {/* Handover Colleague Dropdown */}
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={handoverPersonSelectId}
                                            className="flex items-center gap-1"
                                        >
                                            Handover Colleague
                                            {requiresHandover && (
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            )}
                                        </Label>
                                        <select
                                            id={handoverPersonSelectId}
                                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            value={data.handover_person_id}
                                            onChange={(e) => {
                                                const handoverPersonId =
                                                    e.target.value;
                                                setData(
                                                    'handover_person_id',
                                                    handoverPersonId,
                                                );

                                                if (
                                                    handoverPersonId !== 'other'
                                                ) {
                                                    setData(
                                                        'handover_other_name',
                                                        '',
                                                    );
                                                    setData(
                                                        'handover_other_organisation',
                                                        '',
                                                    );
                                                    setData(
                                                        'handover_other_designation',
                                                        '',
                                                    );
                                                    setData(
                                                        'handover_other_contact_number',
                                                        '',
                                                    );
                                                }

                                                clearErrors(
                                                    'handover_person_id',
                                                    'handover_other_name',
                                                    'handover_other_organisation',
                                                    'handover_other_designation',
                                                    'handover_other_contact_number',
                                                );
                                            }}
                                            required={requiresHandover}
                                        >
                                            <option value="">
                                                Select colleague to handover
                                                work to...
                                            </option>
                                            <option value="other">Other</option>
                                            {candidateHandoverEmployees.map(
                                                (emp) => {
                                                    const onLeave =
                                                        isEmployeeOnLeave(
                                                            emp.id,
                                                        );

                                                    return (
                                                        <option
                                                            key={emp.id}
                                                            value={emp.id}
                                                            disabled={onLeave}
                                                        >
                                                            {emp.user?.name ??
                                                                `Employee #${emp.id}`}
                                                            {emp.designation
                                                                ? ` (${emp.designation})`
                                                                : ''}
                                                            {onLeave
                                                                ? ' — [On Leave during these dates]'
                                                                : ''}
                                                        </option>
                                                    );
                                                },
                                            )}
                                        </select>

                                        {isOtherHandover && (
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label
                                                        htmlFor={`${handoverPersonSelectId}-other-name`}
                                                    >
                                                        Name
                                                        {isOtherHandover && (
                                                            <span className="ml-1 text-destructive">
                                                                *
                                                            </span>
                                                        )}
                                                    </Label>
                                                    <Input
                                                        id={`${handoverPersonSelectId}-other-name`}
                                                        value={
                                                            data.handover_other_name
                                                        }
                                                        required={
                                                            isOtherHandover &&
                                                            requiresHandover
                                                        }
                                                        onChange={(event) => {
                                                            setData(
                                                                'handover_other_name',
                                                                event.target
                                                                    .value,
                                                            );
                                                            clearErrors(
                                                                'handover_other_name',
                                                            );
                                                        }}
                                                    />
                                                    {errors.handover_other_name && (
                                                        <p className="text-sm text-destructive">
                                                            {
                                                                errors.handover_other_name
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="space-y-2">
                                                    <Label
                                                        htmlFor={`${handoverPersonSelectId}-other-organisation`}
                                                    >
                                                        Organisation
                                                        {isOtherHandover && (
                                                            <span className="ml-1 text-destructive">
                                                                *
                                                            </span>
                                                        )}
                                                    </Label>
                                                    <Input
                                                        id={`${handoverPersonSelectId}-other-organisation`}
                                                        value={
                                                            data.handover_other_organisation
                                                        }
                                                        required={
                                                            isOtherHandover &&
                                                            requiresHandover
                                                        }
                                                        onChange={(event) => {
                                                            setData(
                                                                'handover_other_organisation',
                                                                event.target
                                                                    .value,
                                                            );
                                                            clearErrors(
                                                                'handover_other_organisation',
                                                            );
                                                        }}
                                                    />
                                                    {errors.handover_other_organisation && (
                                                        <p className="text-sm text-destructive">
                                                            {
                                                                errors.handover_other_organisation
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="space-y-2">
                                                    <Label
                                                        htmlFor={`${handoverPersonSelectId}-other-designation`}
                                                    >
                                                        Designation
                                                        {isOtherHandover && (
                                                            <span className="ml-1 text-destructive">
                                                                *
                                                            </span>
                                                        )}
                                                    </Label>
                                                    <Input
                                                        id={`${handoverPersonSelectId}-other-designation`}
                                                        value={
                                                            data.handover_other_designation
                                                        }
                                                        required={
                                                            isOtherHandover &&
                                                            requiresHandover
                                                        }
                                                        onChange={(event) => {
                                                            setData(
                                                                'handover_other_designation',
                                                                event.target
                                                                    .value,
                                                            );
                                                            clearErrors(
                                                                'handover_other_designation',
                                                            );
                                                        }}
                                                    />
                                                    {errors.handover_other_designation && (
                                                        <p className="text-sm text-destructive">
                                                            {
                                                                errors.handover_other_designation
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="space-y-2">
                                                    <Label
                                                        htmlFor={`${handoverPersonSelectId}-other-contact-number`}
                                                    >
                                                        Contact number
                                                        {isOtherHandover && (
                                                            <span className="ml-1 text-destructive">
                                                                *
                                                            </span>
                                                        )}
                                                    </Label>
                                                    <Input
                                                        id={`${handoverPersonSelectId}-other-contact-number`}
                                                        type="tel"
                                                        value={
                                                            data.handover_other_contact_number
                                                        }
                                                        required={
                                                            isOtherHandover &&
                                                            requiresHandover
                                                        }
                                                        onChange={(event) => {
                                                            setData(
                                                                'handover_other_contact_number',
                                                                event.target
                                                                    .value,
                                                            );
                                                            clearErrors(
                                                                'handover_other_contact_number',
                                                            );
                                                        }}
                                                    />
                                                    {errors.handover_other_contact_number && (
                                                        <p className="text-sm text-destructive">
                                                            {
                                                                errors.handover_other_contact_number
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        )}

                                        {isSelectedHandoverOnLeave && (
                                            <div className="flex items-center gap-2 rounded-md bg-destructive/10 p-2.5 text-xs font-medium text-destructive">
                                                <AlertCircle className="size-4 shrink-0" />
                                                <span>
                                                    The selected handover
                                                    colleague is also on leave
                                                    during the requested dates.
                                                    Please choose another
                                                    employee.
                                                </span>
                                            </div>
                                        )}

                                        {errors.handover_person_id && (
                                            <p className="text-sm text-destructive">
                                                {errors.handover_person_id}
                                            </p>
                                        )}
                                    </div>

                                    {/* Handover Description */}
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={handoverDescId}
                                            className="flex items-center gap-1"
                                        >
                                            Handover Tasks & Responsibilities
                                            {requiresHandover && (
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            )}
                                        </Label>
                                        <HandoverTasksInput
                                            idPrefix={handoverDescId}
                                            value={data.handover_description}
                                            onChange={(value) => {
                                                setData(
                                                    'handover_description',
                                                    value,
                                                );
                                                clearErrors(
                                                    'handover_description',
                                                );
                                            }}
                                        />
                                        {errors.handover_description && (
                                            <p className="text-sm text-destructive">
                                                {errors.handover_description}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Document / Certificate Attachment */}
                            {requiresAttachment && (
                                <div className="space-y-2">
                                    <Label
                                        htmlFor={certificateId}
                                        className="flex items-center gap-1.5"
                                    >
                                        <FileUp className="size-4 text-muted-foreground" />
                                        Attachment / Certificate{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        ref={certificateInputRef}
                                        id={certificateId}
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                        required
                                        onChange={(e) => {
                                            const file =
                                                e.target.files?.[0] || null;
                                            setData('certificate', file);
                                            clearErrors('certificate');
                                        }}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Accepted formats: PDF, JPG, PNG, DOC,
                                        DOCX up to 10MB.
                                    </p>
                                    {errors.certificate && (
                                        <p className="text-sm text-destructive">
                                            {errors.certificate}
                                        </p>
                                    )}
                                </div>
                            )}

                            {/* Action Buttons */}
                            <div className="flex items-center justify-end gap-3 border-t border-border pt-4">
                                <Button variant="outline" type="button" asChild>
                                    <Link href="/leave-requests">Cancel</Link>
                                </Button>
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={
                                        processing || isSelectedHandoverOnLeave
                                    }
                                    onClick={() => {
                                        submissionStatus.current = 'applied';
                                    }}
                                >
                                    <Send className="size-4" />
                                    {processing
                                        ? 'Submitting...'
                                        : 'Apply Leave'}
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        processing || isSelectedHandoverOnLeave
                                    }
                                    onClick={() => {
                                        submissionStatus.current = 'approved';
                                    }}
                                >
                                    <CheckCircle2 className="size-4" />
                                    {processing
                                        ? 'Submitting...'
                                        : 'Apply & Approve Leave'}
                                </Button>
                            </div>
                        </form>
                    </div>

                    {/* Sidebar Overview / Balance Card */}
                    <div className="space-y-6">
                        {/* Employee Card */}
                        {selectedEmployee && (
                            <div className="rounded-2xl border border-border bg-white p-5 shadow-sm">
                                <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                                    <UserIcon className="size-4 text-primary" />
                                    Employee Summary
                                </h3>
                                <div className="space-y-2 text-sm">
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Name
                                        </p>
                                        <p className="font-medium text-foreground">
                                            {selectedEmployee.user?.name ??
                                                'Unknown'}
                                        </p>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2">
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Employee Code
                                            </p>
                                            <p className="font-medium text-foreground">
                                                {selectedEmployee.employee_code ||
                                                    selectedEmployee.emp_num}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Department
                                            </p>
                                            <p className="font-medium text-foreground">
                                                {selectedEmployee.department
                                                    ?.name || 'N/A'}
                                            </p>
                                        </div>
                                    </div>
                                    {selectedEmployee.designation && (
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Designation
                                            </p>
                                            <p className="font-medium text-foreground">
                                                {selectedEmployee.designation}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Leave Balance Card */}
                        <div className="rounded-2xl border border-border bg-white p-5 shadow-sm">
                            <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                                <Calendar className="size-4 text-primary" />
                                Leave Quota & Balance (
                                {new Date().getFullYear()})
                            </h3>

                            {selectedBalance ? (
                                <div className="space-y-4">
                                    <div className="grid grid-cols-3 gap-2 text-center">
                                        <div className="rounded-lg bg-muted/40 p-2.5">
                                            <p className="text-xs text-muted-foreground">
                                                Allocated
                                            </p>
                                            <p className="text-lg font-bold text-foreground">
                                                {selectedBalance.allocated}
                                            </p>
                                        </div>
                                        <div className="rounded-lg bg-muted/40 p-2.5">
                                            <p className="text-xs text-muted-foreground">
                                                Used
                                            </p>
                                            <p className="text-lg font-bold text-amber-600">
                                                {selectedBalance.used}
                                            </p>
                                        </div>
                                        <div className="rounded-lg bg-emerald-50 p-2.5">
                                            <p className="text-xs text-emerald-700">
                                                Remaining
                                            </p>
                                            <p className="text-lg font-bold text-emerald-700">
                                                {selectedBalance.balance}
                                            </p>
                                        </div>
                                    </div>

                                    {data.total_days &&
                                        Number(data.total_days) >
                                            selectedBalance.balance && (
                                            <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                                <AlertCircle className="mt-0.5 size-4 shrink-0 text-amber-600" />
                                                <span>
                                                    Requested duration (
                                                    <strong>
                                                        {data.total_days} days
                                                    </strong>
                                                    ) exceeds current remaining
                                                    quota (
                                                    <strong>
                                                        {
                                                            selectedBalance.balance
                                                        }{' '}
                                                        days
                                                    </strong>
                                                    ).
                                                </span>
                                            </div>
                                        )}
                                </div>
                            ) : data.employee_id && data.leave_type_id ? (
                                <div className="rounded-lg bg-muted/30 p-4 text-center">
                                    <Clock className="mx-auto mb-1 size-6 text-muted-foreground opacity-70" />
                                    <p className="text-xs text-muted-foreground">
                                        No explicit balance allocation recorded
                                        for this employee and leave type in{' '}
                                        {new Date().getFullYear()}.
                                    </p>
                                </div>
                            ) : (
                                <div className="rounded-lg bg-muted/30 p-4 text-center">
                                    <p className="text-xs text-muted-foreground">
                                        Select both an employee and a leave type
                                        to preview available quota balance.
                                    </p>
                                </div>
                            )}

                            {selectedLeaveType && (
                                <div className="mt-4 space-y-2 border-t border-border pt-4 text-xs">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">
                                            Type:
                                        </span>
                                        <span className="font-medium text-foreground">
                                            {selectedLeaveType.leave_name}
                                        </span>
                                    </div>
                                    {requiresAttachment && (
                                        <div className="mt-2 flex items-center gap-1.5 rounded bg-amber-50 p-2 text-amber-700">
                                            <ShieldAlert className="size-3.5 shrink-0" />
                                            <span>
                                                Policy requires an
                                                attachment/certificate for this
                                                leave type.
                                            </span>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        {selectedLeaveType && (
                            <div className="rounded-2xl border border-border bg-white p-5 shadow-sm">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Policy Annual Limit
                                </h3>
                                {selectedPolicy?.annual_limit !== null &&
                                selectedPolicy?.annual_limit !== undefined ? (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {selectedPolicy.annual_limit} days per
                                        year
                                    </p>
                                ) : (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        No annual limit configured for this
                                        organisation.
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Audit Note */}
                        <div className="flex items-start gap-2.5 rounded-xl border border-blue-100 bg-blue-50/70 p-4 text-xs text-blue-900">
                            <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-blue-600" />
                            <div>
                                <p className="mb-0.5 font-semibold">
                                    HR Action Audit
                                </p>
                                <p className="leading-relaxed text-blue-700">
                                    Applying this leave on behalf of the
                                    employee will automatically be logged in the
                                    system leave history with your HR identity
                                    for transparency.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

LeaveRequestsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave Requests', href: '/leave-requests' },
        { title: 'Apply Leave' },
    ],
};
