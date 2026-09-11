import { useState, useId, useMemo, useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    Send,
    User as UserIcon,
    AlertCircle,
    CheckCircle2,
    Clock,
    FileUp,
    ShieldAlert,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';

interface EmployeeOption {
    id: number;
    user_id: number;
    emp_num: string;
    employee_code: string | null;
    designation: string | null;
    organisation_id: number | null;
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
    leave_name: string;
    code: string;
    is_paid: boolean;
    requires_attachment: boolean;
    status?: number;
}

interface LeaveBalanceItem {
    id: number;
    employee_id: number;
    leave_type_id: number;
    allocated: number;
    used: number;
    balance: number;
}

interface Props {
    employees: EmployeeOption[];
    leaveTypes: LeaveTypeOption[];
    leaveBalances: LeaveBalanceItem[];
}

export default function LeaveRequestsCreate({ employees = [], leaveTypes = [], leaveBalances = [] }: Props) {
    const employeeSelectId = useId();
    const leaveTypeSelectId = useId();
    const startDateId = useId();
    const endDateId = useId();
    const totalDaysId = useId();
    const statusSelectId = useId();
    const remarksId = useId();
    const certificateId = useId();

    const [employeeSearch, setEmployeeSearch] = useState('');

    const { data, setData, post, processing, errors } = useForm({
        employee_id: '',
        leave_type_id: '',
        start_date: '',
        end_date: '',
        total_days: '',
        status: 'applied',
        remarks: '',
        certificate: null as File | null,
    });

    const filteredEmployees = useMemo(() => {
        if (!employeeSearch.trim()) {
            return employees;
        }
        const term = employeeSearch.toLowerCase();
        return employees.filter((emp) => {
            const name = emp.user?.name?.toLowerCase() ?? '';
            const code = emp.employee_code?.toLowerCase() ?? '';
            const num = emp.emp_num?.toLowerCase() ?? '';
            const desig = emp.designation?.toLowerCase() ?? '';
            const dept = emp.department?.name?.toLowerCase() ?? '';
            return name.includes(term) || code.includes(term) || num.includes(term) || desig.includes(term) || dept.includes(term);
        });
    }, [employees, employeeSearch]);

    // Calculate days between start and end date
    useEffect(() => {
        if (data.start_date && data.end_date) {
            const start = new Date(data.start_date);
            const end = new Date(data.end_date);
            if (!isNaN(start.getTime()) && !isNaN(end.getTime()) && end >= start) {
                const diffTime = Math.abs(end.getTime() - start.getTime());
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                setData('total_days', String(diffDays));
            }
        }
    }, [data.start_date, data.end_date]);

    // Find current balance for selected employee and leave type
    const selectedBalance = useMemo(() => {
        if (!data.employee_id || !data.leave_type_id) {
            return null;
        }
        const empId = Number(data.employee_id);
        const typeId = Number(data.leave_type_id);
        return leaveBalances.find((b) => b.employee_id === empId && b.leave_type_id === typeId) ?? null;
    }, [data.employee_id, data.leave_type_id, leaveBalances]);

    const selectedLeaveType = useMemo(() => {
        if (!data.leave_type_id) return null;
        return leaveTypes.find((lt) => String(lt.id) === String(data.leave_type_id)) ?? null;
    }, [data.leave_type_id, leaveTypes]);

    const selectedEmployee = useMemo(() => {
        if (!data.employee_id) return null;
        return employees.find((e) => String(e.id) === String(data.employee_id)) ?? null;
    }, [data.employee_id, employees]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/leave-requests');
    };

    return (
        <>
            <Head title="Apply Leave for Employee" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1200px] flex-1 flex-col gap-6 p-6 md:p-8">
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
                        <form onSubmit={submit} className="space-y-6">
                            {/* Employee Selection */}
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <Label htmlFor={employeeSelectId} className="flex items-center gap-1.5 font-medium">
                                        <UserIcon className="size-4 text-primary" />
                                        Select Employee <span className="text-destructive">*</span>
                                    </Label>
                                    {employees.length > 8 && (
                                        <span className="text-xs text-muted-foreground">
                                            {filteredEmployees.length} of {employees.length} available
                                        </span>
                                    )}
                                </div>

                                {employees.length > 8 && (
                                    <Input
                                        type="text"
                                        placeholder="Type to filter employees by name, code, designation..."
                                        value={employeeSearch}
                                        onChange={(e) => setEmployeeSearch(e.target.value)}
                                        className="mb-2 h-9 text-xs"
                                    />
                                )}

                                <select
                                    id={employeeSelectId}
                                    value={data.employee_id}
                                    onChange={(e) => setData('employee_id', e.target.value)}
                                    className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    required
                                >
                                    <option value="">-- Choose Employee --</option>
                                    {filteredEmployees.map((emp) => (
                                        <option key={emp.id} value={emp.id}>
                                            {emp.user?.name ?? 'Unknown'} ({emp.employee_code || emp.emp_num})
                                            {emp.designation ? ` - ${emp.designation}` : ''}
                                            {emp.department?.name ? ` [${emp.department.name}]` : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.employee_id && (
                                    <p className="text-sm text-destructive">{errors.employee_id}</p>
                                )}
                            </div>

                            {/* Leave Type Selection */}
                            <div className="space-y-2">
                                <Label htmlFor={leaveTypeSelectId} className="flex items-center gap-1.5 font-medium">
                                    <Calendar className="size-4 text-primary" />
                                    Leave Type <span className="text-destructive">*</span>
                                </Label>
                                <select
                                    id={leaveTypeSelectId}
                                    value={data.leave_type_id}
                                    onChange={(e) => setData('leave_type_id', e.target.value)}
                                    className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    required
                                >
                                    <option value="">-- Choose Leave Type --</option>
                                    {leaveTypes.map((type) => (
                                        <option key={type.id} value={type.id}>
                                            {type.leave_name} ({type.code}) - {type.is_paid ? 'Paid' : 'Unpaid'}
                                            {type.status === 0 ? ' [Inactive]' : ''}
                                            {type.requires_attachment ? ' [Attachment Required]' : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.leave_type_id && (
                                    <p className="text-sm text-destructive">{errors.leave_type_id}</p>
                                )}
                            </div>

                            {/* Dates & Duration Grid */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor={startDateId}>
                                        Start Date <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id={startDateId}
                                        type="date"
                                        value={data.start_date}
                                        onChange={(e) => setData('start_date', e.target.value)}
                                        required
                                    />
                                    {errors.start_date && (
                                        <p className="text-sm text-destructive">{errors.start_date}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor={endDateId}>
                                        End Date <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id={endDateId}
                                        type="date"
                                        min={data.start_date}
                                        value={data.end_date}
                                        onChange={(e) => setData('end_date', e.target.value)}
                                        required
                                    />
                                    {errors.end_date && (
                                        <p className="text-sm text-destructive">{errors.end_date}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor={totalDaysId}>Total Days</Label>
                                    <Input
                                        id={totalDaysId}
                                        type="number"
                                        step="0.5"
                                        min="0.5"
                                        placeholder="e.g. 1 or 0.5"
                                        value={data.total_days}
                                        onChange={(e) => setData('total_days', e.target.value)}
                                    />
                                    {errors.total_days && (
                                        <p className="text-sm text-destructive">{errors.total_days}</p>
                                    )}
                                </div>
                            </div>

                            {/* Status Option for HR */}
                            <div className="space-y-2">
                                <Label htmlFor={statusSelectId} className="flex items-center gap-1.5 font-medium">
                                    Application Status <span className="text-destructive">*</span>
                                </Label>
                                <select
                                    id={statusSelectId}
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="applied">Applied (Standard workflow - pending further action)</option>
                                    <option value="approved">Approved (Directly approve leave on submission)</option>
                                </select>
                                <p className="text-xs text-muted-foreground">
                                    {data.status === 'approved'
                                        ? 'Selecting "Approved" will immediately approve the request and deduct the employee\'s leave balance quota.'
                                        : 'Selecting "Applied" submits the request as pending for regular review.'}
                                </p>
                                {errors.status && (
                                    <p className="text-sm text-destructive">{errors.status}</p>
                                )}
                            </div>

                            {/* Remarks / Reason */}
                            <div className="space-y-2">
                                <Label htmlFor={remarksId}>Reason / Remarks</Label>
                                <Textarea
                                    id={remarksId}
                                    rows={3}
                                    placeholder="Provide reason for leave, special authorization notes, or handover notes..."
                                    value={data.remarks}
                                    onChange={(e) => setData('remarks', e.target.value)}
                                />
                                {errors.remarks && (
                                    <p className="text-sm text-destructive">{errors.remarks}</p>
                                )}
                            </div>

                            {/* Document / Certificate Attachment */}
                            <div className="space-y-2">
                                <Label htmlFor={certificateId} className="flex items-center gap-1.5">
                                    <FileUp className="size-4 text-muted-foreground" />
                                    Attachment / Certificate (Optional)
                                </Label>
                                <Input
                                    id={certificateId}
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                    onChange={(e) => {
                                        const file = e.target.files?.[0] || null;
                                        setData('certificate', file);
                                    }}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Accepted formats: PDF, JPG, PNG, DOC, DOCX up to 10MB.
                                </p>
                                {errors.certificate && (
                                    <p className="text-sm text-destructive">{errors.certificate}</p>
                                )}
                            </div>

                            {/* Action Buttons */}
                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-border">
                                <Button variant="outline" type="button" asChild>
                                    <Link href="/leave-requests">Cancel</Link>
                                </Button>
                                <Button type="submit" disabled={processing} className="min-w-[140px]">
                                    <Send className="mr-2 size-4" />
                                    {processing ? 'Submitting...' : 'Apply Leave'}
                                </Button>
                            </div>
                        </form>
                    </div>

                    {/* Sidebar Overview / Balance Card */}
                    <div className="space-y-6">
                        {/* Employee Card */}
                        {selectedEmployee && (
                            <div className="rounded-2xl border border-border bg-white p-5 shadow-sm">
                                <h3 className="text-sm font-semibold text-foreground flex items-center gap-2 mb-3">
                                    <UserIcon className="size-4 text-primary" />
                                    Employee Summary
                                </h3>
                                <div className="space-y-2 text-sm">
                                    <div>
                                        <p className="text-xs text-muted-foreground">Name</p>
                                        <p className="font-medium text-foreground">{selectedEmployee.user?.name ?? 'Unknown'}</p>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2">
                                        <div>
                                            <p className="text-xs text-muted-foreground">Employee Code</p>
                                            <p className="font-medium text-foreground">{selectedEmployee.employee_code || selectedEmployee.emp_num}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">Department</p>
                                            <p className="font-medium text-foreground">{selectedEmployee.department?.name || 'N/A'}</p>
                                        </div>
                                    </div>
                                    {selectedEmployee.designation && (
                                        <div>
                                            <p className="text-xs text-muted-foreground">Designation</p>
                                            <p className="font-medium text-foreground">{selectedEmployee.designation}</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Leave Balance Card */}
                        <div className="rounded-2xl border border-border bg-white p-5 shadow-sm">
                            <h3 className="text-sm font-semibold text-foreground flex items-center gap-2 mb-3">
                                <Calendar className="size-4 text-primary" />
                                Leave Quota & Balance ({new Date().getFullYear()})
                            </h3>

                            {selectedBalance ? (
                                <div className="space-y-4">
                                    <div className="grid grid-cols-3 gap-2 text-center">
                                        <div className="rounded-lg bg-muted/40 p-2.5">
                                            <p className="text-xs text-muted-foreground">Allocated</p>
                                            <p className="text-lg font-bold text-foreground">{selectedBalance.allocated}</p>
                                        </div>
                                        <div className="rounded-lg bg-muted/40 p-2.5">
                                            <p className="text-xs text-muted-foreground">Used</p>
                                            <p className="text-lg font-bold text-amber-600">{selectedBalance.used}</p>
                                        </div>
                                        <div className="rounded-lg bg-emerald-50 p-2.5">
                                            <p className="text-xs text-emerald-700">Remaining</p>
                                            <p className="text-lg font-bold text-emerald-700">{selectedBalance.balance}</p>
                                        </div>
                                    </div>

                                    {data.total_days && Number(data.total_days) > selectedBalance.balance && (
                                        <div className="rounded-lg bg-amber-50 border border-amber-200 p-3 flex items-start gap-2 text-amber-800 text-xs">
                                            <AlertCircle className="size-4 shrink-0 text-amber-600 mt-0.5" />
                                            <span>
                                                Requested duration (<strong>{data.total_days} days</strong>) exceeds current remaining quota (<strong>{selectedBalance.balance} days</strong>).
                                            </span>
                                        </div>
                                    )}
                                </div>
                            ) : data.employee_id && data.leave_type_id ? (
                                <div className="rounded-lg bg-muted/30 p-4 text-center">
                                    <Clock className="size-6 text-muted-foreground mx-auto mb-1 opacity-70" />
                                    <p className="text-xs text-muted-foreground">
                                        No explicit balance allocation recorded for this employee and leave type in {new Date().getFullYear()}.
                                    </p>
                                </div>
                            ) : (
                                <div className="rounded-lg bg-muted/30 p-4 text-center">
                                    <p className="text-xs text-muted-foreground">
                                        Select both an employee and a leave type to preview available quota balance.
                                    </p>
                                </div>
                            )}

                            {selectedLeaveType && (
                                <div className="mt-4 pt-4 border-t border-border space-y-2 text-xs">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Type:</span>
                                        <span className="font-medium text-foreground">{selectedLeaveType.leave_name}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Pay Status:</span>
                                        <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium ${
                                            selectedLeaveType.is_paid ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'
                                        }`}>
                                            {selectedLeaveType.is_paid ? 'Paid Leave' : 'Unpaid Leave'}
                                        </span>
                                    </div>
                                    {selectedLeaveType.requires_attachment && (
                                        <div className="flex items-center gap-1.5 text-amber-700 bg-amber-50 p-2 rounded mt-2">
                                            <ShieldAlert className="size-3.5 shrink-0" />
                                            <span>Policy requires an attachment/certificate for this leave type.</span>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Audit Note */}
                        <div className="rounded-xl border border-blue-100 bg-blue-50/70 p-4 text-xs text-blue-900 flex items-start gap-2.5">
                            <CheckCircle2 className="size-4 text-blue-600 shrink-0 mt-0.5" />
                            <div>
                                <p className="font-semibold mb-0.5">HR Action Audit</p>
                                <p className="text-blue-700 leading-relaxed">
                                    Applying this leave on behalf of the employee will automatically be logged in the system leave history with your HR identity for transparency.
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
