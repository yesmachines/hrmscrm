import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import {
    index,
    update,
} from '@/actions/App/Http/Controllers/Leave/LeaveBalanceController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';

type LeaveBalanceRecord = {
    id: number;
    year: number;
    allocated: number;
    carried_forward: number;
    used: number;
    pending: number;
    encashed: number;
    earned: number;
    employee: {
        user: { name: string } | null;
        emp_num?: string | null;
        employee_code: string | null;
    } | null;
    leave_type: {
        leave_name: string;
    } | null;
};

export default function LeaveBalancesEdit({
    leave_balance,
}: {
    leave_balance: LeaveBalanceRecord;
}) {
    const { data, setData, put, processing, errors } = useForm({
        allocated: leave_balance.allocated.toString(),
        carried_forward: leave_balance.carried_forward.toString(),
        used: leave_balance.used.toString(),
        pending: leave_balance.pending.toString(),
        encashed: leave_balance.encashed.toString(),
        earned: leave_balance.earned.toString(),
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
    const employeeIdentifier =
        leave_balance.employee?.employee_code ??
        leave_balance.employee?.emp_num;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(update.url(leave_balance.id));
    };

    return (
        <>
            <Head title="Edit Leave Balance" />

            <div className="mx-auto flex w-full max-w-full flex-1 flex-col gap-6 p-6 md:p-8 2xl:max-w-[1200px]">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <Heading
                        title="Edit Leave Balance"
                        description="Manually adjust the leave balance record"
                    />
                </div>

                <div className="rounded-2xl border border-border bg-white p-6 shadow-sm">
                    <form onSubmit={submit} className="space-y-6">
                        <p className="text-sm text-muted-foreground">
                            Balance identity cannot be changed. Component
                            adjustments are recorded in the transaction history
                            with your reason.
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
                                <Label>Employee</Label>
                                <p className="pt-2 text-sm text-foreground">
                                    {leave_balance.employee?.user?.name ??
                                        'Unknown'}
                                    {employeeIdentifier
                                        ? ` (${employeeIdentifier})`
                                        : ''}
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label>Leave Type</Label>
                                <p className="pt-2 text-sm text-foreground">
                                    {leave_balance.leave_type?.leave_name ??
                                        'Unknown'}
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label>Year</Label>
                                <p className="pt-2 text-sm text-foreground">
                                    {leave_balance.year}
                                </p>
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
                                Reason for adjustment
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
                                Update Balance
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

LeaveBalancesEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave Balances', href: index.url() },
        { title: 'Edit Balance' },
    ],
};
