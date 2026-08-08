import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import OrganisationController from '@/actions/App/Http/Controllers/Organisation/OrganisationController';
import Heading from '@/components/heading';
import RowActionsMenu from '@/components/row-actions-menu';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type OrganisationRow = {
    id: number;
    org_name: string;
    short_name: string;
    logo: string | null;
    status: number;
};

type PaginatedOrganisations = {
    data: OrganisationRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function OrganisationsIndex({
    organisations,
}: {
    organisations: PaginatedOrganisations;
}) {
    return (
        <>
            <Head title="Organisations" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Organisations"
                        description="Manage companies and organisation records"
                    />
                    <Button asChild>
                        <Link
                            href={OrganisationController.create.url()}
                            prefetch
                        >
                            <Plus className="size-4" />
                            Add organisation
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Logo</th>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">
                                    Short name
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
                            {organisations.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No organisations found.{' '}
                                        <Link
                                            href={OrganisationController.create.url()}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            Create one
                                        </Link>
                                    </td>
                                </tr>
                            ) : (
                                organisations.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3">
                                            {row.logo ? (
                                                <img
                                                    src={row.logo}
                                                    alt=""
                                                    className="h-10 w-10 rounded-md border border-border object-contain bg-muted/30"
                                                />
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 font-medium">
                                            {row.org_name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.short_name}
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
                                            <RowActionsMenu
                                                viewHref={OrganisationController.show.url(
                                                    row.id,
                                                )}
                                                editHref={OrganisationController.edit.url(
                                                    row.id,
                                                )}
                                                destroyForm={OrganisationController.destroy.form(
                                                    row.id,
                                                )}
                                                deleteTitle="Delete organisation?"
                                                deleteDescription={`This will permanently delete ${row.org_name}. This cannot be undone.`}
                                                deleteConfirmLabel="Delete organisation"
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {organisations.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {organisations.links.map((link, index) =>
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

OrganisationsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organisations', href: OrganisationController.index.url() },
    ],
};
