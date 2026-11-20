import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/date-format';
import { dashboard } from '@/routes';

type Schedule = {
    id: number;
    working_days: number;
    requested_date: string;
    status: number;
    effective_from: string | null;
    effective_to: string | null;
    employee: {
        employee_code: string | null;
        user: { name: string } | null;
        department: { name: string } | null;
    } | null;
    balance_summary: {
        allocated: number;
        used: number;
        balance: number;
        pending: number;
    };
};

type PaginatedSchedules = {
    data: Schedule[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function WorkFromHomeSchedulesIndex({
    schedules,
}: {
    schedules: PaginatedSchedules;
}) {
    const updateStatus = (schedule: Schedule) => {
        router.patch(
            `/wfh-schedules/${schedule.id}/status`,
            { status: schedule.status === 1 ? 0 : 1 },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="WFH schedules" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="WFH schedules"
                        description="Manage employee work-from-home allowances and effective periods"
                    />
                    <Button asChild>
                        <Link href="/wfh-schedules/create">
                            <Plus className="mr-2 size-4" />
                            Add schedule
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Employee</th>
                                <th className="px-4 py-3 font-medium">Working days</th>
                                <th className="px-4 py-3 font-medium">Requested date</th>
                                <th className="px-4 py-3 font-medium">Effective period</th>
                                <th className="px-4 py-3 font-medium">Used</th>
                                <th className="px-4 py-3 font-medium">Balance</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {schedules.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No WFH schedules have been created.
                                    </td>
                                </tr>
                            ) : (
                                schedules.data.map((schedule) => (
                                    <tr
                                        key={schedule.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {schedule.employee?.user?.name ?? 'Unknown'}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {schedule.employee?.employee_code ?? ''}
                                                {schedule.employee?.department?.name
                                                    ? ` · ${schedule.employee.department.name}`
                                                    : ''}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">{schedule.working_days}</td>
                                        <td className="px-4 py-3">
                                            {formatDate(schedule.requested_date)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {schedule.effective_from && schedule.effective_to
                                                ? `${formatDate(schedule.effective_from)} - ${formatDate(schedule.effective_to)}`
                                                : 'No date restriction'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {schedule.balance_summary.used}
                                        </td>
                                        <td className="px-4 py-3">
                                            {schedule.balance_summary.balance}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                    schedule.status === 1
                                                        ? 'bg-green-100 text-green-700'
                                                        : 'bg-gray-100 text-gray-700'
                                                }`}
                                            >
                                                {schedule.status === 1 ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant={schedule.status === 1 ? 'outline' : 'default'}
                                                size="sm"
                                                onClick={() => updateStatus(schedule)}
                                            >
                                                {schedule.status === 1 ? 'Deactivate' : 'Activate'}
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {schedules.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {schedules.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={`rounded-md border px-3 py-1.5 text-sm ${
                                        link.active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-white text-foreground hover:bg-muted'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={index}
                                    className="rounded-md border border-border px-3 py-1.5 text-sm text-muted-foreground opacity-50"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ),
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

WorkFromHomeSchedulesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'WFH schedules', href: '/wfh-schedules' },
    ],
};
