import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import DesignationController from '@/actions/App/Http/Controllers/Designation/DesignationController';
import RowActionsMenu from '@/components/row-actions-menu';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

type DesignationRow = {
    id: number;
    department_id: number;
    title: string;
    shortcode: string;
    status: number;
    department?: { id: number; name: string; code: string } | null;
};

type PaginatedDesignations = {
    data: DesignationRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function DesignationsIndex({
    designations,
}: {
    designations: PaginatedDesignations;
}) {
    const searchParams = new URLSearchParams(
        typeof window !== 'undefined' ? window.location.search : '',
    );
    const [search, setSearch] = useState(searchParams.get('search') || '');

    return (
        <>
            <Head title="Designations" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">
                            Designations
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage job titles and designation codes across departments
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.get(
                                    DesignationController.index.url(),
                                    { search },
                                    { preserveState: true },
                                );
                            }}
                            className="relative w-full sm:w-64"
                        >
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Search designations..."
                                className="bg-background pl-9"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </form>

                        <Button asChild>
                            <Link href={DesignationController.create.url()} prefetch>
                                <Plus className="size-4" />
                                Add designation
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border/70 bg-muted/40 text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-3.5 font-medium">Designation Title</th>
                                    <th className="px-5 py-3.5 font-medium">Shortcode</th>
                                    <th className="px-5 py-3.5 font-medium">Department</th>
                                    <th className="px-5 py-3.5 font-medium">Status</th>
                                    <th className="px-5 py-3.5 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/60">
                                {designations.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-5 py-12 text-center text-muted-foreground"
                                        >
                                            No designations found.{' '}
                                            <Link
                                                href={DesignationController.create.url()}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                Create one now
                                            </Link>
                                        </td>
                                    </tr>
                                ) : (
                                    designations.data.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="transition-colors hover:bg-muted/30"
                                        >
                                            <td className="px-5 py-3.5 font-medium text-foreground">
                                                <Link
                                                    href={DesignationController.show.url(row.id)}
                                                    className="hover:underline"
                                                >
                                                    {row.title}
                                                </Link>
                                            </td>
                                            <td className="px-5 py-3.5 text-muted-foreground">
                                                <span className="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">
                                                    {row.shortcode}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3.5 text-foreground">
                                                {row.department
                                                    ? `${row.department.name} (${row.department.code})`
                                                    : '—'}
                                            </td>
                                            <td className="px-5 py-3.5">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                                        row.status === 1
                                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                                            : 'bg-muted text-muted-foreground'
                                                    }`}
                                                >
                                                    {row.status === 1 ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3.5 text-right">
                                                <RowActionsMenu
                                                    viewHref={DesignationController.show.url(row.id)}
                                                    editHref={DesignationController.edit.url(row.id)}
                                                    destroyForm={DesignationController.destroy.form(row.id)}
                                                    deleteTitle="Delete designation?"
                                                    deleteDescription={`Are you sure you want to delete ${row.title}?`}
                                                />
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {designations.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-border/70 px-5 py-3.5">
                            <div className="flex gap-1">
                                {designations.links.map((link, idx) =>
                                    link.url ? (
                                        <Link
                                            key={idx}
                                            href={link.url}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                            className={`rounded-md px-3 py-1.5 text-xs font-medium ${
                                                link.active
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'text-muted-foreground hover:bg-muted'
                                            }`}
                                        />
                                    ) : (
                                        <span
                                            key={idx}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                            className="rounded-md px-3 py-1.5 text-xs text-muted-foreground/50"
                                        />
                                    ),
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

DesignationsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Designations', href: DesignationController.index.url() },
    ],
};
