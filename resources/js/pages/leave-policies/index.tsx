import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import LeavePolicyController from '@/actions/App/Http/Controllers/LeavePolicyController';
import Heading from '@/components/heading';
import RowActionsMenu from '@/components/row-actions-menu';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type LeavePolicyRow = {
    id: number;
    allocation_days: number | null;
    carry_forward: number;
    encashment: number;
    leave_type: { id: number; name: string; code: string } | null;
    organisation: { id: number; name: string } | null;
};

type PaginatedLeavePolicies = {
    data: LeavePolicyRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function LeavePoliciesIndex({
    leavePolicies,
}: {
    leavePolicies: PaginatedLeavePolicies;
}) {
    return (
        <>
            <Head title="Leave policies" />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Leave policies"
                        description="Manage organisation leave policies"
                    />
                    <Button asChild>
                        <Link
                            href={LeavePolicyController.create.url()}
                            prefetch
                        >
                            <Plus className="size-4" />
                            Add leave policy
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Leave type
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Organisation
                                </th>
                                <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                    Allocation
                                </th>
                                <th className="hidden px-4 py-3 font-medium md:table-cell">
                                    Carry forward
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {leavePolicies.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No leave policies found.{' '}
                                        <Link
                                            href={LeavePolicyController.create.url()}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            Create one
                                        </Link>
                                    </td>
                                </tr>
                            ) : (
                                leavePolicies.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {row.leave_type
                                                ? `${row.leave_type.name} (${row.leave_type.code})`
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.organisation?.name ?? '—'}
                                        </td>
                                        <td className="hidden px-4 py-3 sm:table-cell">
                                            {row.allocation_days ?? '—'}
                                        </td>
                                        <td className="hidden px-4 py-3 md:table-cell">
                                            {row.carry_forward === 1
                                                ? 'Yes'
                                                : 'No'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <RowActionsMenu
                                                viewHref={LeavePolicyController.show.url(
                                                    row.id,
                                                )}
                                                editHref={LeavePolicyController.edit.url(
                                                    row.id,
                                                )}
                                                destroyForm={LeavePolicyController.destroy.form(
                                                    row.id,
                                                )}
                                                deleteTitle="Delete leave policy?"
                                                deleteDescription="This will permanently delete this leave policy. This cannot be undone."
                                                deleteConfirmLabel="Delete leave policy"
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {leavePolicies.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {leavePolicies.links.map((link, index) =>
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

LeavePoliciesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Leave policies',
            href: LeavePolicyController.index.url(),
        },
    ],
};
