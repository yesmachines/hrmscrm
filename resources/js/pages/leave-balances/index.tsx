import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Trash2, Plus } from 'lucide-react';
import { index, create, edit, destroy } from '@/actions/App/Http/Controllers/Leave/LeaveBalanceController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type LeaveBalanceRow = {
    id: number;
    year: number;
    allocated: number;
    carried_forward: number;
    used: number;
    balance: number;
    pending: number;
    encashed: number;
    earned: number;
    employee: {
        id: number;
        user: { name: string } | null;
        emp_num?: string | null;
        employee_code: string | null;
    } | null;
    leave_type: {
        id: number;
        leave_name: string;
    } | null;
};

type PaginatedBalances = {
    data: LeaveBalanceRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function LeaveBalancesIndex({ balances }: { balances: PaginatedBalances }) {
    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this leave balance?')) {
            router.delete(destroy.url(id));
        }
    };

    return (
        <>
            <Head title="Leave Balances" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Leave Balances"
                        description="Manage employee leave allocations and balances"
                    />
                    <Button asChild>
                        <Link href={create.url()}>
                            <Plus className="mr-2 size-4" />
                            Add Balance
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm overflow-x-auto">
                    <table className="w-full text-left text-sm whitespace-nowrap">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Employee</th>
                                <th className="px-4 py-3 font-medium">Leave Type</th>
                                <th className="px-4 py-3 font-medium">Year</th>
                                <th className="px-4 py-3 font-medium">Allocated</th>
                                <th className="px-4 py-3 font-medium">C/F</th>
                                <th className="px-4 py-3 font-medium">Used</th>
                                <th className="px-4 py-3 font-medium">Pending</th>
                                <th className="px-4 py-3 font-medium">Balance</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {balances.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No leave balances found.
                                    </td>
                                </tr>
                            ) : (
                                balances.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="font-medium text-foreground">
                                                {row.employee?.user?.name ?? 'Unknown'}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {row.employee?.employee_code ||
                                                    row.employee?.emp_num}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-foreground font-medium">
                                            {row.leave_type?.leave_name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {row.year}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {row.allocated}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {row.carried_forward}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {row.used}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {row.pending}
                                        </td>
                                        <td className="px-4 py-3 font-semibold text-foreground">
                                            {row.balance}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={edit.url(row.id)}>
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button variant="ghost" size="icon" className="text-destructive hover:bg-destructive/10 hover:text-destructive" onClick={() => handleDelete(row.id)}>
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {balances.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {balances.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={`rounded-md border px-3 py-1.5 text-sm ${
                                        link.active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-white text-foreground hover:bg-muted'
                                    }`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : (
                                <span
                                    key={i}
                                    className="rounded-md border border-border px-3 py-1.5 text-sm text-muted-foreground opacity-50"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

LeaveBalancesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave Balances', href: index.url() },
    ],
};
