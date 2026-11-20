import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';

type Employee = {
    id: number;
    emp_num: string;
    employee_code: string | null;
    user: { name: string } | null;
    department: { name: string } | null;
};

type ScheduleForm = {
    employee_id: string;
    working_days: string;
    requested_date: string;
    effective_from: string;
    effective_to: string;
};

export default function WorkFromHomeSchedulesCreate({
    employees,
}: {
    employees: Employee[];
}) {
    const { data, setData, post, processing, errors } = useForm<ScheduleForm>({
        employee_id: '',
        working_days: '1',
        requested_date: '',
        effective_from: '',
        effective_to: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/wfh-schedules');
    };

    return (
        <>
            <Head title="Add WFH schedule" />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Add WFH schedule"
                    description="Set the employee's working-day allowance and effective period"
                />

                <form onSubmit={submit} className="space-y-6">
                    <div className="space-y-2">
                        <Label htmlFor="employee_id">Employee</Label>
                        <select
                            id="employee_id"
                            value={data.employee_id}
                            onChange={(event) => setData('employee_id', event.target.value)}
                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            required
                        >
                            <option value="">Select an employee</option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.user?.name ?? employee.employee_code ?? employee.emp_num}
                                    {employee.department?.name
                                        ? ` - ${employee.department.name}`
                                        : ''}
                                </option>
                            ))}
                        </select>
                        {errors.employee_id && (
                            <p className="text-sm text-destructive">{errors.employee_id}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="working_days">Working days</Label>
                        <Input
                            id="working_days"
                            type="number"
                            min="0"
                            step="1"
                            value={data.working_days}
                            onChange={(event) => setData('working_days', event.target.value)}
                            required
                        />
                        {errors.working_days && (
                            <p className="text-sm text-destructive">{errors.working_days}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="requested_date">Requested date</Label>
                        <Input
                            id="requested_date"
                            type="date"
                            value={data.requested_date}
                            onChange={(event) => setData('requested_date', event.target.value)}
                            required
                        />
                        {errors.requested_date && (
                            <p className="text-sm text-destructive">{errors.requested_date}</p>
                        )}
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="effective_from">Effective from</Label>
                            <Input
                                id="effective_from"
                                type="date"
                                value={data.effective_from}
                                onChange={(event) => setData('effective_from', event.target.value)}
                            />
                            {errors.effective_from && (
                                <p className="text-sm text-destructive">{errors.effective_from}</p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="effective_to">Effective to</Label>
                            <Input
                                id="effective_to"
                                type="date"
                                value={data.effective_to}
                                onChange={(event) => setData('effective_to', event.target.value)}
                            />
                            {errors.effective_to && (
                                <p className="text-sm text-destructive">{errors.effective_to}</p>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Save schedule
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/wfh-schedules">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

WorkFromHomeSchedulesCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'WFH schedules', href: '/wfh-schedules' },
        { title: 'Add', href: '/wfh-schedules/create' },
    ],
};
