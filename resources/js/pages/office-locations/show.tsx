import type { ReactNode } from 'react';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import OfficeLocationController from '@/actions/App/Http/Controllers/Organisation/OfficeLocationController';
import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type OfficeLocation = {
    id: number;
    office_name: string;
    city: string;
    address: string;
    organisation: { id: number; name: string; short_name: string } | null;
    country: { id: number; name: string; code: string } | null;
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

export default function OfficeLocationsShow({
    officeLocation,
}: {
    officeLocation: OfficeLocation;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Office locations',
                href: OfficeLocationController.index.url(),
            },
            {
                title: officeLocation.office_name,
                href: OfficeLocationController.show.url(officeLocation.id),
            },
        ],
    });

    return (
        <>
            <Head title={officeLocation.office_name} />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={officeLocation.office_name}
                        description={
                            officeLocation.organisation?.name ?? undefined
                        }
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={OfficeLocationController.edit.url(
                                    officeLocation.id,
                                )}
                                prefetch
                            >
                                Edit
                            </Link>
                        </Button>
                        <DeleteConfirmDialog
                            form={OfficeLocationController.destroy.form(
                                officeLocation.id,
                            )}
                            title="Delete office location?"
                            description={`This will permanently delete ${officeLocation.office_name}. This cannot be undone.`}
                            confirmLabel="Delete office location"
                        />
                    </div>
                </div>

                <Section title="Details">
                    <Detail
                        label="Office name"
                        value={officeLocation.office_name}
                    />
                    <Detail
                        label="Organisation"
                        value={officeLocation.organisation?.name}
                    />
                    <Detail
                        label="Country"
                        value={officeLocation.country?.name}
                    />
                    <Detail label="City" value={officeLocation.city} />
                    <div className="sm:col-span-2">
                        <Detail label="Address" value={officeLocation.address} />
                    </div>
                </Section>
            </div>
        </>
    );
}
