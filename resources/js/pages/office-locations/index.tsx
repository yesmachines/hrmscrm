import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import OfficeLocationController from '@/actions/App/Http/Controllers/OfficeLocationController';
import Heading from '@/components/heading';
import RowActionsMenu from '@/components/row-actions-menu';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type OfficeLocationRow = {
    id: number;
    office_name: string;
    city: string;
    address: string;
    organisation: { id: number; name: string; short_name: string } | null;
    country: { id: number; name: string; code: string } | null;
};

type PaginatedOfficeLocations = {
    data: OfficeLocationRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function OfficeLocationsIndex({
    officeLocations,
}: {
    officeLocations: PaginatedOfficeLocations;
}) {
    return (
        <>
            <Head title="Office locations" />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Office locations"
                        description="Manage office locations by organisation"
                    />
                    <Button asChild>
                        <Link
                            href={OfficeLocationController.create.url()}
                            prefetch
                        >
                            <Plus className="size-4" />
                            Add office location
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Office</th>
                                <th className="px-4 py-3 font-medium">
                                    Organisation
                                </th>
                                <th className="hidden px-4 py-3 font-medium md:table-cell">
                                    Country
                                </th>
                                <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                    City
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {officeLocations.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No office locations found.{' '}
                                        <Link
                                            href={OfficeLocationController.create.url()}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            Create one
                                        </Link>
                                    </td>
                                </tr>
                            ) : (
                                officeLocations.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {row.office_name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.organisation?.name ?? '—'}
                                        </td>
                                        <td className="hidden px-4 py-3 md:table-cell">
                                            {row.country?.name ?? '—'}
                                        </td>
                                        <td className="hidden px-4 py-3 sm:table-cell">
                                            {row.city}
                                        </td>
                                        <td className="px-4 py-3">
                                            <RowActionsMenu
                                                viewHref={OfficeLocationController.show.url(
                                                    row.id,
                                                )}
                                                editHref={OfficeLocationController.edit.url(
                                                    row.id,
                                                )}
                                                destroyForm={OfficeLocationController.destroy.form(
                                                    row.id,
                                                )}
                                                deleteTitle="Delete office location?"
                                                deleteDescription={`This will permanently delete ${row.office_name}. This cannot be undone.`}
                                                deleteConfirmLabel="Delete office location"
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {officeLocations.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {officeLocations.links.map((link, index) =>
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

OfficeLocationsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Office locations',
            href: OfficeLocationController.index.url(),
        },
    ],
};
