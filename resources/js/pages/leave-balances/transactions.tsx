import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import {
    index,
    transactions,
} from '@/actions/App/Http/Controllers/Leave/LeaveBalanceController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type LeaveBalance = {
    id: number;
    year: number;
    employee: {
        user: { name: string } | null;
        emp_num?: string | null;
        employee_code: string | null;
    } | null;
    leave_type: {
        leave_name: string;
    } | null;
};

type BalanceTransaction = {
    id: number;
    transaction_type: string;
    quantity: number;
    balance_before: number;
    balance_after: number;
    remarks: string | null;
    created_at: string | null;
    created_by: { id: number; name: string } | null;
    leave_request: {
        id: number;
        start_date: string;
        end_date: string | null;
        status: string;
    } | null;
};

type PaginatedTransactions = {
    data: BalanceTransaction[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function LeaveBalanceTransactions({
    leaveBalance,
    transactions: paginatedTransactions,
}: {
    leaveBalance: LeaveBalance;
    transactions: PaginatedTransactions;
}) {
    const employeeIdentifier =
        leaveBalance.employee?.employee_code ?? leaveBalance.employee?.emp_num;

    return (
        <>
            <Head title="Leave Balance Transactions" />

            <div className="mx-auto flex w-full max-w-full flex-1 flex-col gap-6 p-6 md:p-8 2xl:max-w-[1600px]">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <Heading
                        title="Balance Transaction History"
                        description={`${leaveBalance.employee?.user?.name ?? 'Unknown'}${employeeIdentifier ? ` (${employeeIdentifier})` : ''} · ${leaveBalance.leave_type?.leave_name ?? 'Leave'} · ${leaveBalance.year}`}
                    />
                </div>

                <div className="flex flex-wrap gap-3">
                    <Button variant="outline" asChild>
                        <Link href={index.url()}>All Leave Balances</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={transactions.url(leaveBalance.id)}>
                            Refresh History
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm whitespace-nowrap">
                            <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Date
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Movement
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Quantity
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Available Before
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Available After
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Leave Request
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Recorded By
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Remarks
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {paginatedTransactions.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="px-4 py-10 text-center text-muted-foreground"
                                        >
                                            No balance transactions have been
                                            recorded.
                                        </td>
                                    </tr>
                                ) : (
                                    paginatedTransactions.data.map(
                                        (transaction) => (
                                            <tr
                                                key={transaction.id}
                                                className="border-b border-border last:border-0"
                                            >
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {transaction.created_at
                                                        ? new Date(
                                                              transaction.created_at,
                                                          ).toLocaleString()
                                                        : '—'}
                                                </td>
                                                <td className="px-4 py-3 font-medium text-foreground capitalize">
                                                    {transaction.transaction_type.replace(
                                                        /_/g,
                                                        ' ',
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-foreground">
                                                    {Number(
                                                        transaction.quantity,
                                                    ).toFixed(2)}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {Number(
                                                        transaction.balance_before,
                                                    ).toFixed(2)}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {Number(
                                                        transaction.balance_after,
                                                    ).toFixed(2)}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {transaction.leave_request
                                                        ? `#${transaction.leave_request.id} · ${transaction.leave_request.start_date} – ${transaction.leave_request.end_date ?? transaction.leave_request.start_date}`
                                                        : 'Balance adjustment'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {transaction.created_by
                                                        ?.name ??
                                                        'System / import'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {transaction.remarks ?? '—'}
                                                </td>
                                            </tr>
                                        ),
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {paginatedTransactions.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {paginatedTransactions.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
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
                                    key={index}
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

LeaveBalanceTransactions.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave Balances', href: index.url() },
        { title: 'Transaction History' },
    ],
};
