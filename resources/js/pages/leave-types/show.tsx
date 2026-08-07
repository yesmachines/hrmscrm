import type { ReactNode } from 'react';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import LeaveTypeController from '@/actions/App/Http/Controllers/Leave/LeaveTypeController';
import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type LeaveType = {
    id: number;
    leave_name: string;
    code: string;
    is_paid: number;
    requires_attachment: number;
    requires_approval: number;
    max_days: number | null;
    annual_limit: number | null;
    gender: string | null;
    allow_once: number;
    allow_balance: number;
    status: number;
    requires_handover: number;
};

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined;
}) {
    const display =
        value !== null && value !== undefined && value !== '' ? value : '—';

    return (
        <div className="space-y-1">
            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="text-sm text-foreground">{display}</dd>
        </div>
    );
}

function Section({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4 rounded-2xl border border-border bg-white p-5 shadow-sm">
            <h3 className="text-sm font-semibold tracking-tight">{title}</h3>
            <dl className="grid gap-4 sm:grid-cols-2">{children}</dl>
        </section>
    );
}

function yesNo(value: number): string {
    return value === 1 ? 'Yes' : 'No';
}

export default function LeaveTypesShow({ leaveType }: { leaveType: LeaveType }) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Leave types',
                href: LeaveTypeController.index.url(),
            },
            {
                title: leaveType.leave_name,
                href: LeaveTypeController.show.url(leaveType.id),
            },
        ],
    });

    return (
        <>
            <Head title={leaveType.leave_name} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={leaveType.leave_name}
                        description={leaveType.code}
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={LeaveTypeController.edit.url(
                                    leaveType.id,
                                )}
                                prefetch
                            >
                                Edit
                            </Link>
                        </Button>
                        <DeleteConfirmDialog
                            form={LeaveTypeController.destroy.form(
                                leaveType.id,
                            )}
                            title="Delete leave type?"
                            description={`This will permanently delete ${leaveType.leave_name}. This cannot be undone.`}
                            confirmLabel="Delete leave type"
                        />
                    </div>
                </div>

                <Section title="Details">
                    <Detail label="Leave name" value={leaveType.leave_name} />
                    <Detail label="Code" value={leaveType.code} />
                    <Detail
                        label="Status"
                        value={leaveType.status === 1 ? 'Active' : 'Inactive'}
                    />
                    <Detail label="Gender" value={leaveType.gender} />
                    <Detail label="Max days" value={leaveType.max_days} />
                    <Detail
                        label="Annual limit"
                        value={leaveType.annual_limit}
                    />
                    <Detail label="Is paid" value={yesNo(leaveType.is_paid)} />
                    <Detail
                        label="Requires attachment"
                        value={yesNo(leaveType.requires_attachment)}
                    />
                    <Detail
                        label="Requires approval"
                        value={yesNo(leaveType.requires_approval)}
                    />
                    <Detail
                        label="Allow once"
                        value={yesNo(leaveType.allow_once)}
                    />
                    <Detail
                        label="Allow balance"
                        value={yesNo(leaveType.allow_balance)}
                    />
                    <Detail
                        label="Requires handover"
                        value={yesNo(leaveType.requires_handover)}
                    />
                </Section>
            </div>
        </>
    );
}
