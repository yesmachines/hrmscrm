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
            <Head title="Festivals & Holidays" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Festivals & Holidays"
                        description="Manage festivals, holidays, and their dates"
                    />
                    <Button asChild>
                        <Link href={FestivalController.create.url()} prefetch>
                            <Plus className="size-4" />
                            Add festival or holiday
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
                                        No festivals or holidays found.{' '}
                                        <Link
                                            href={FestivalController.create.url()}
                                            className="underline hover:text-foreground"
                                        >
                                            Add one now
                                        </Link>
                                    </td>
                                </tr>
                            ) : (
                                festivals.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border/60 transition-colors hover:bg-muted/50"
                                    >
                                        <td className="px-4 py-3 font-medium text-foreground">
                                            {row.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize ${
                                                    row.type === 'festival'
                                                        ? 'bg-purple-100 text-purple-700'
                                                        : 'bg-blue-100 text-blue-700'
                                                }`}
                                            >
                                                {row.type}
                                            </span>
                                        </td>
                                        <td className="hidden px-4 py-3 text-muted-foreground sm:table-cell">
                                            {row.shortcode}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {row.type === 'festival' || row.start_date === row.end_date
                                                ? row.start_date
                                                : `${row.start_date} to ${row.end_date}`}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${
                                                    row.is_active
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : 'bg-zinc-100 text-zinc-600'
                                                }`}
                                            >
                                                {row.is_active ? 'Active' : 'Inactive'}
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
                                                deleteTitle="Delete festival or holiday?"
                                                deleteDescription={`Are you sure you want to delete ${row.name}?`}
                                                deleteConfirmLabel="Delete"
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {festivals.links.length > 3 && (
                    <div className="flex flex-wrap items-center justify-center gap-1.5 pt-2">
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
            title: 'Festivals & Holidays',
            href: FestivalController.index.url(),
        },
    ],
};
