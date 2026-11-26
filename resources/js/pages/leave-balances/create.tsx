import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Leave/LeaveBalanceController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';

type EmployeeOption = {
    id: number;
    user: { name: string } | null;
};

type LeaveTypeOption = {
    id: number;
    leave_name: string;
};

export default function LeaveBalancesCreate({
    employees,
    leaveTypes,
}: {
    employees: EmployeeOption[];
    leaveTypes: LeaveTypeOption[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        employee_id: '',
        leave_type_id: '',
        year: new Date().getFullYear().toString(),
        allocated: '0',
        carried_forward: '0',
        used: '0',
        pending: '0',
        encashed: '0',
        earned: '0',
        remarks: '',
    });
    const { errors: pageErrors } = usePage().props;

    const calculatedBalance =
        Number(data.allocated || 0) +
        Number(data.carried_forward || 0) +
        Number(data.earned || 0) -
        Number(data.used || 0) -
        Number(data.pending || 0) -
        Number(data.encashed || 0);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(store.url());
    };

    return (
        <>
            <Head title="Add Leave Balance" />

            <div className="mx-auto flex w-full max-w-full flex-1 flex-col gap-6 p-6 md:p-8 2xl:max-w-[1200px]">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <Heading
                        title="Add Leave Balance"
                        description="Manually add a leave balance record for an employee"
                    />
                </div>

                <div className="rounded-2xl border border-border bg-white p-6 shadow-sm">
                    <form onSubmit={submit} className="space-y-6">
                        <p className="text-sm text-muted-foreground">
                            The available balance is calculated from the
                            components below. The opening amounts and your
                            remarks will be recorded in the balance transaction
                            history.
                        </p>
                        {(pageErrors.balance || pageErrors.total_days) && (
                            <p
                                className="text-sm text-destructive"
                                role="alert"
                            >
                                {pageErrors.balance ?? pageErrors.total_days}
                            </p>
                        )}
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="employee_id">Employee</Label>
                                <select
                                    id="employee_id"
                                    value={data.employee_id}
                                    onChange={(e) =>
                                        setData('employee_id', e.target.value)
                                    }
                                    className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">Select Employee</option>
                                    {employees.map((emp) => (
                                        <option key={emp.id} value={emp.id}>
                                            {emp.user?.name ?? 'Unknown'}
                                        </option>
                                    ))}
                                </select>
                                {errors.employee_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.employee_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="leave_type_id">
                                    Leave Type
                                </Label>
                                <select
                                    id="leave_type_id"
                                    value={data.leave_type_id}
                                    onChange={(e) =>
                                        setData('leave_type_id', e.target.value)
                                    }
                                    className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">Select Leave Type</option>
                                    {leaveTypes.map((type) => (
                                        <option key={type.id} value={type.id}>
                                            {type.leave_name}
                                        </option>
                                    ))}
                                </select>
                                {errors.leave_type_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.leave_type_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="year">Year</Label>
                                <Input
                                    id="year"
                                    type="number"
                                    value={data.year}
                                    onChange={(e) =>
                                        setData('year', e.target.value)
                                    }
                                />
                                {errors.year && (
                                    <p className="text-sm text-destructive">
                                        {errors.year}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="allocated">
                                    Allocated Days
                                </Label>
                                <Input
                                    id="allocated"
                                    type="number"
                                    step="0.5"
                                    value={data.allocated}
                                    onChange={(e) =>
                                        setData('allocated', e.target.value)
                                    }
                                />
                                {errors.allocated && (
                                    <p className="text-sm text-destructive">
                                        {errors.allocated}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="carried_forward">
                                    Carried Forward
                                </Label>
                                <Input
                                    id="carried_forward"
                                    type="number"
                                    step="0.5"
                                    value={data.carried_forward}
                                    onChange={(e) =>
                                        setData(
                                            'carried_forward',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.carried_forward && (
                                    <p className="text-sm text-destructive">
                                        {errors.carried_forward}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="used">Used Days</Label>
                                <Input
                                    id="used"
                                    type="number"
                                    step="0.5"
                                    value={data.used}
                                    onChange={(e) =>
                                        setData('used', e.target.value)
                                    }
                                />
                                {errors.used && (
                                    <p className="text-sm text-destructive">
                                        {errors.used}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="pending">
                                    Pending Approval
                                </Label>
                                <Input
                                    id="pending"
                                    type="number"
                                    step="0.5"
                                    value={data.pending}
                                    onChange={(e) =>
                                        setData('pending', e.target.value)
                                    }
                                />
                                {errors.pending && (
                                    <p className="text-sm text-destructive">
                                        {errors.pending}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="encashed">Encashed</Label>
                                <Input
                                    id="encashed"
                                    type="number"
                                    step="0.5"
                                    value={data.encashed}
                                    onChange={(e) =>
                                        setData('encashed', e.target.value)
                                    }
                                />
                                {errors.encashed && (
                                    <p className="text-sm text-destructive">
                                        {errors.encashed}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="earned">Earned</Label>
                                <Input
                                    id="earned"
                                    type="number"
                                    step="0.5"
                                    value={data.earned}
                                    onChange={(e) =>
                                        setData('earned', e.target.value)
                                    }
                                />
                                {errors.earned && (
                                    <p className="text-sm text-destructive">
                                        {errors.earned}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="calculated_balance">
                                    Calculated Available Balance
                                </Label>
                                <Input
                                    id="calculated_balance"
                                    type="number"
                                    value={calculatedBalance.toFixed(2)}
                                    readOnly
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="remarks">
                                Reason for opening balance
                            </Label>
                            <Textarea
                                id="remarks"
                                value={data.remarks}
                                onChange={(e) =>
                                    setData('remarks', e.target.value)
                                }
                                required
                            />
                            {errors.remarks && (
                                <p className="text-sm text-destructive">
                                    {errors.remarks}
                                </p>
                            )}
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing}>
                                <Save className="mr-2 size-4" />
                                Save Balance
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

LeaveBalancesCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave Balances', href: index.url() },
        { title: 'Add Balance' },
    ],
};
