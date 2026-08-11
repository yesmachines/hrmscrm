import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { index, create } from '@/actions/App/Http/Controllers/Leave/LeaveHistoryController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type LeaveHistoryRow = {
    id: number;
    action_type: string;
    remarks: string | null;
    action_on: string | null;
    leave_request: {
        id: number;
        start_date: string;
        end_date: string;
    } | null;
    done_by: {
        id: number;
        name: string;
    } | null;
};

type PaginatedHistories = {
    data: LeaveHistoryRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function LeaveHistoriesIndex({ histories }: { histories: PaginatedHistories }) {
    return (
        <>
            <Head title="Leave Histories" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1400px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Leave Histories"
                        description="Audit log for all leave request actions"
                    />
                    <Button asChild>
                        <Link href={create.url()}>
                            <Plus className="mr-2 size-4" />
                            Add Record
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm overflow-x-auto">
                    <table className="w-full text-left text-sm whitespace-nowrap">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Leave Request ID</th>
                                <th className="px-4 py-3 font-medium">Action Type</th>
                                <th className="px-4 py-3 font-medium">Remarks</th>
                                <th className="px-4 py-3 font-medium">Done By</th>
                                <th className="px-4 py-3 font-medium">Action On</th>
                            </tr>
                        </thead>
                        <tbody>
                            {histories.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No leave histories found.
                                    </td>
                                </tr>
                            ) : (
                                histories.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium text-foreground">
                                            #{row.leave_request?.id} 
                                            {row.leave_request && (
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    ({new Date(row.leave_request.start_date).toLocaleDateString()} - {new Date(row.leave_request.end_date).toLocaleDateString()})
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize
                                                ${row.action_type === 'approved' ? 'bg-green-100 text-green-800' : 
                                                  row.action_type === 'rejected' ? 'bg-red-100 text-red-800' : 
                                                  row.action_type === 'cancelled' ? 'bg-gray-100 text-gray-800' :
                                                  'bg-blue-100 text-blue-800'}`}
                                            >
                                                {row.action_type}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground max-w-[200px] truncate">
                                            {row.remarks || '-'}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {row.done_by?.name || '-'}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {row.action_on ? new Date(row.action_on).toLocaleString() : '-'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {histories.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {histories.links.map((link, i) =>
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

LeaveHistoriesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave Histories', href: index.url() },
    ],
};
