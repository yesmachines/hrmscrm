import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import FestivalController from '@/actions/App/Http/Controllers/Leave/FestivalController';
import Heading from '@/components/heading';
import RowActionsMenu from '@/components/row-actions-menu';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type FestivalRow = {
    id: number;
    name: string;
    is_active: number;
    type: string;
    shortcode: string;
    start_date: string | null;
    end_date: string | null;
};

type PaginatedFestivals = {
    data: FestivalRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function FestivalsIndex({
    festivals,
}: {
    festivals: PaginatedFestivals;
}) {
    return (
        <>
            <Head title="Festivals" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Festivals"
                        description="Manage festivals and their dates"
                    />
                    <Button asChild>
                        <Link href={FestivalController.create.url()} prefetch>
                            <Plus className="size-4" />
                            Add festival
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Type</th>
                                <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                    Shortcode
                                </th>
                                <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                    Dates
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
                            {festivals.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No festivals found.{' '}
                                        <Link
                                            href={FestivalController.create.url()}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            Create one
                                        </Link>
                                    </td>
                                </tr>
                            ) : (
                                festivals.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {row.name}
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {row.type}
                                        </td>
                                        <td className="hidden px-4 py-3 sm:table-cell font-mono text-xs">
                                            {row.shortcode}
                                        </td>
                                        <td className="hidden px-4 py-3 sm:table-cell">
                                            {row.type === 'festival' || row.start_date === row.end_date
                                                ? (row.start_date ?? 'Not set')
                                                : (row.start_date ? `${row.start_date} to ${row.end_date}` : 'Not set')}
                                        </td>
                                        <td className="hidden px-4 py-3 sm:table-cell">
                                            <span
                                                className={
                                                    row.is_active === 1
                                                        ? 'text-primary'
                                                        : 'text-muted-foreground'
                                                }
                                            >
                                                {row.is_active === 1
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <RowActionsMenu
                                                editHref={FestivalController.edit.url(
                                                    row.id,
                                                )}
                                                destroyForm={FestivalController.destroy.form(
                                                    row.id,
                                                )}
                                                deleteTitle="Delete festival?"
                                                deleteDescription={`This will permanently delete ${row.name}. This cannot be undone.`}
                                                deleteConfirmLabel="Delete festival"
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {festivals.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {festivals.links.map((link, index) =>
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

FestivalsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Festivals',
            href: FestivalController.index.url(),
        },
    ],
};
