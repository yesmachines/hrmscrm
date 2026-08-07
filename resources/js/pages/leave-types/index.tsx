import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import LeaveTypeController from '@/actions/App/Http/Controllers/LeaveTypeController';
import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type LeaveTypeRow = {
    id: number;
    leave_name: string;
    code: string;
    is_paid: number;
    requires_approval: number;
    status: number;
};

type PaginatedLeaveTypes = {
    data: LeaveTypeRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function LeaveTypesIndex({
    leaveTypes,
}: {
    leaveTypes: PaginatedLeaveTypes;
}) {
    return (
        <>
            <Head title="Leave types" />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Leave types"
                        description="Manage leave type definitions"
                    />
                    <Button asChild>
                        <Link href={LeaveTypeController.create.url()} prefetch>
                            <Plus className="size-4" />
                            Add leave type
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Code</th>
                                <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                    Paid
                                </th>
                                <th className="hidden px-4 py-3 font-medium md:table-cell">
                                    Approval
                                </th>
                                <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {leaveTypes.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No leave types found.{' '}
                                        <Link
                                            href={LeaveTypeController.create.url()}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            Create one
                                        </Link>
                                    </td>
                                </tr>
                            ) : (
                                leaveTypes.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {row.leave_name}
                                        </td>
                                        <td className="px-4 py-3">{row.code}</td>
                                        <td className="hidden px-4 py-3 sm:table-cell">
                                            {row.is_paid === 1 ? 'Yes' : 'No'}
                                        </td>
                                        <td className="hidden px-4 py-3 md:table-cell">
                                            {row.requires_approval === 1
                                                ? 'Required'
                                                : 'Not required'}
                                        </td>
                                        <td className="hidden px-4 py-3 sm:table-cell">
                                            <span
                                                className={
                                                    row.status === 1
                                                        ? 'text-primary'
                                                        : 'text-muted-foreground'
                                                }
                                            >
                                                {row.status === 1
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={LeaveTypeController.show.url(
                                                            row.id,
                                                        )}
                                                        prefetch
                                                    >
                                                        View
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={LeaveTypeController.edit.url(
                                                            row.id,
                                                        )}
                                                        prefetch
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                                <DeleteConfirmDialog
                                                    form={LeaveTypeController.destroy.form(
                                                        row.id,
                                                    )}
                                                    title="Delete leave type?"
                                                    description={`This will permanently delete ${row.leave_name}. This cannot be undone.`}
                                                    confirmLabel="Delete leave type"
                                                    trigger={
                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                            type="button"
                                                        >
                                                            Delete
                                                        </Button>
                                                    }
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {leaveTypes.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {leaveTypes.links.map((link, index) =>
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

LeaveTypesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Leave types',
            href: LeaveTypeController.index.url(),
        },
    ],
};
