import type { ReactNode } from 'react';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import OrganisationController from '@/actions/App/Http/Controllers/Organisation/OrganisationController';
import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type Organisation = {
    id: number;
    org_name: string;
    short_name: string;
    logo: string | null;
    status: number;
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

export default function OrganisationsShow({
    organisation,
}: {
    organisation: Organisation;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Organisations',
                href: OrganisationController.index.url(),
            },
            {
                title: organisation.org_name,
                href: OrganisationController.show.url(organisation.id),
            },
        ],
    });

    return (
        <>
            <Head title={organisation.org_name} />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={organisation.org_name}
                        description={organisation.short_name}
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={OrganisationController.edit.url(
                                    organisation.id,
                                )}
                                prefetch
                            >
                                Edit
                            </Link>
                        </Button>
                        <DeleteConfirmDialog
                            form={OrganisationController.destroy.form(
                                organisation.id,
                            )}
                            title="Delete organisation?"
                            description={`This will permanently delete ${organisation.org_name}. This cannot be undone.`}
                            confirmLabel="Delete organisation"
                        />
                    </div>
                </div>

                <Section title="Details">
                    <Detail label="Organisation name" value={organisation.org_name} />
                    <Detail label="Short name" value={organisation.short_name} />
                    <div className="space-y-1 sm:col-span-2">
                        <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Logo
                        </dt>
                        <dd className="text-sm text-foreground">
                            {organisation.logo ? (
                                <img
                                    src={organisation.logo}
                                    alt={`${organisation.org_name} logo`}
                                    className="mt-1 h-20 w-20 rounded-lg border border-border object-contain bg-muted/30"
                                />
                            ) : (
                                '—'
                            )}
                        </dd>
                    </div>
                    <Detail
                        label="Status"
                        value={organisation.status === 1 ? 'Active' : 'Inactive'}
                    />
                </Section>
            </div>
        </>
    );
}
