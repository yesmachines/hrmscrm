import type { ReactNode } from 'react';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import LeavePolicyController from '@/actions/App/Http/Controllers/Leave/LeavePolicyController';
import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type LeavePolicy = {
    id: number;
    leave_type_id: number;
    organisation_id: number;
    full_pay_days: number | null;
    half_pay_days: number | null;
    no_pay_days: number | null;
    requires_document_after_days: number | null;
    requires_weekend_document: number;
    carry_forward: number;
    encashment: number;
    remarks: string | null;
    requires_attachment: number;
    probation_applicable: number;
    minimum_service_months: number | null;
    leave_type: { id: number; name: string; code: string } | null;
    organisation: { id: number; name: string } | null;
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

export default function LeavePoliciesShow({
    leavePolicy,
}: {
    leavePolicy: LeavePolicy;
}) {
    const title = leavePolicy.leave_type
        ? `${leavePolicy.leave_type.name} — ${leavePolicy.organisation?.name ?? 'Policy'}`
        : 'Leave policy';

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Leave policies',
                href: LeavePolicyController.index.url(),
            },
            {
                title,
                href: LeavePolicyController.show.url(leavePolicy.id),
            },
        ],
    });

    return (
        <>
            <Head title={title} />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={title}
                        description={leavePolicy.leave_type?.code}
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={LeavePolicyController.edit.url(
                                    leavePolicy.id,
                                )}
                                prefetch
                            >
                                Edit
                            </Link>
                        </Button>
                        <DeleteConfirmDialog
                            form={LeavePolicyController.destroy.form(
                                leavePolicy.id,
                            )}
                            title="Delete leave policy?"
                            description="This will permanently delete this leave policy. This cannot be undone."
                            confirmLabel="Delete leave policy"
                        />
                    </div>
                </div>

                <Section title="Details">
                    <Detail
                        label="Leave type"
                        value={
                            leavePolicy.leave_type
                                ? `${leavePolicy.leave_type.name} (${leavePolicy.leave_type.code})`
                                : null
                        }
                    />
                    <Detail
                        label="Organisation"
                        value={leavePolicy.organisation?.name}
                    />
                    <Detail
                        label="Full pay days"
                        value={leavePolicy.full_pay_days}
                    />
                    <Detail
                        label="Half pay days"
                        value={leavePolicy.half_pay_days}
                    />
                    <Detail
                        label="No pay days"
                        value={leavePolicy.no_pay_days}
                    />
                    <Detail
                        label="Requires document after days"
                        value={leavePolicy.requires_document_after_days}
                    />
                    <Detail
                        label="Minimum service months"
                        value={leavePolicy.minimum_service_months}
                    />
                    <Detail
                        label="Requires weekend document"
                        value={yesNo(leavePolicy.requires_weekend_document)}
                    />
                    <Detail
                        label="Carry forward"
                        value={yesNo(leavePolicy.carry_forward)}
                    />
                    <Detail
                        label="Encashment"
                        value={yesNo(leavePolicy.encashment)}
                    />
                    <Detail
                        label="Requires attachment"
                        value={yesNo(leavePolicy.requires_attachment)}
                    />
                    <Detail
                        label="Probation applicable"
                        value={yesNo(leavePolicy.probation_applicable)}
                    />
                    <Detail label="Remarks" value={leavePolicy.remarks} />
                </Section>
            </div>
        </>
    );
}
