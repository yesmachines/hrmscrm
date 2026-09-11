import { Head, Link, router } from '@inertiajs/react';
import { Check, Plus, X } from 'lucide-react';
import { index, update, show } from '@/actions/App/Http/Controllers/Leave/LeaveRequestController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type LeaveRequestRow = {
    id: number;
    start_date: string;
    end_date: string;
    total_days: number;
    status: string;
    created_at: string;
    leave_type: {
        id: number;
        leave_name: string;
        is_paid: boolean;
    };
    employee: {
        id: number;
        user: { name: string } | null;
        employee_code: string | null;
        image_url: string | null;
    } | null;
};

type PaginatedRequests = {
    data: LeaveRequestRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function LeaveRequestsIndex({ requests }: { requests: PaginatedRequests }) {
    const handleStatusUpdate = (id: number, status: string) => {
        router.put(update.url(id), { status }, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Leave Requests" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Leave Requests"
                        description="Track and manage employee leave requests"
                    />
                    <Button asChild>
                        <Link href="/leave-requests/create">
                            <Plus className="mr-2 size-4" />
                            Apply Leave
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Employee</th>
                                <th className="px-4 py-3 font-medium">Leave Type</th>
                                <th className="px-4 py-3 font-medium">Duration</th>
                                <th className="px-4 py-3 font-medium">Total Days</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {requests.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No leave requests submitted yet.
                                    </td>
                                </tr>
                            ) : (
                                requests.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3">
                                            <div>
                                                <div className="font-medium">
                                                    {row.employee?.user?.name ?? 'Unknown'}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {row.employee?.employee_code}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 font-medium text-foreground">
                                            {row.leave_type?.leave_name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {new Date(row.start_date).toLocaleDateString()} - {new Date(row.end_date).toLocaleDateString()}
                                        </td>
                                        <td className="px-4 py-3 font-medium text-foreground">
                                            {row.total_days} {row.total_days === 1 ? 'Day' : 'Days'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                row.status === 'approved' ? 'bg-green-100 text-green-700' :
                                                row.status === 'rejected' ? 'bg-red-100 text-red-700' :
                                                row.status === 'cancelled' ? 'bg-gray-100 text-gray-700' :
                                                'bg-blue-100 text-blue-700'
                                            }`}>
                                                {row.status.charAt(0).toUpperCase() + row.status.slice(1)}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button variant="ghost" size="sm" asChild>
                                                    <Link href={show.url(row.id)}>
                                                        View
                                                    </Link>
                                                </Button>
                                                {row.status === 'applied' && (
                                                    <>
                                                        <Button variant="outline" size="sm" onClick={() => handleStatusUpdate(row.id, 'rejected')}>
                                                            <X className="mr-1 size-3" />
                                                            Reject
                                                        </Button>
                                                        <Button variant="default" size="sm" onClick={() => handleStatusUpdate(row.id, 'approved')}>
                                                            <Check className="mr-1 size-3" />
                                                            Approve
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {requests.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {requests.links.map((link, i) =>
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

LeaveRequestsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave Requests', href: index.url() },
    ],
};
