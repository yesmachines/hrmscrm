import type { ReactNode } from 'react';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import DesignationController from '@/actions/App/Http/Controllers/Designation/DesignationController';
import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type Designation = {
    id: number;
    department_id: number;
    title: string;
    shortcode: string;
    status: number;
    department?: { id: number; name: string; code: string } | null;
    created_at?: string;
    updated_at?: string;
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
            <dd className="text-sm font-medium text-foreground">{display}</dd>
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
        <section className="space-y-4 rounded-2xl border border-border/80 bg-card p-6 shadow-xs">
            <h3 className="text-sm font-semibold tracking-tight text-foreground">{title}</h3>
            <dl className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">{children}</dl>
        </section>
    );
}

export default function DesignationsShow({
    designation,
}: {
    designation: Designation;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Designations',
                href: DesignationController.index.url(),
            },
            {
                title: designation.title,
                href: DesignationController.show.url(designation.id),
            },
        ],
    });

    return (
        <>
            <Head title={designation.title} />

            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                {designation.title}
                            </h1>
                            <span className="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">
                                {designation.shortcode}
                            </span>
                            <span
                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ${
                                    designation.status === 1
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                        : 'bg-muted text-muted-foreground'
                                }`}
                            >
                                {designation.status === 1 ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {designation.department
                                ? `${designation.department.name} (${designation.department.code})`
                                : 'No department assigned'}
                        </p>
                    </div>

                    <div className="flex items-center gap-2.5">
                        <Button variant="outline" asChild>
                            <Link href={DesignationController.edit.url(designation.id)} prefetch>
                                Edit
                            </Link>
                        </Button>
                        <DeleteConfirmDialog
                            form={DesignationController.destroy.form(designation.id)}
                            title="Delete designation?"
                            description={`Are you sure you want to delete ${designation.title}? This cannot be undone.`}
                        />
                        <Button variant="outline" asChild>
                            <Link href={DesignationController.index.url()}>
                                Back to list
                            </Link>
                        </Button>
                    </div>
                </div>

                <Section title="Designation Details">
                    <Detail label="Designation title" value={designation.title} />
                    <Detail label="Short code" value={designation.shortcode} />
                    <Detail
                        label="Department"
                        value={
                            designation.department
                                ? `${designation.department.name} (${designation.department.code})`
                                : null
                        }
                    />
                    <Detail
                        label="Status"
                        value={designation.status === 1 ? 'Active' : 'Inactive'}
                    />
                    <Detail label="Created at" value={designation.created_at} />
                    <Detail label="Updated at" value={designation.updated_at} />
                </Section>
            </div>
        </>
    );
}
