import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DocumentCategoryController from '@/actions/App/Http/Controllers/DocumentCategoryController';
import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type CategoryRow = {
    id: number;
    category_name: string;
    short_code: string;
    status: number;
    parent_id: number | null;
    parent: { id: number; name: string } | null;
};

type PaginatedCategories = {
    data: CategoryRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function DocumentCategoriesIndex({
    categories,
}: {
    categories: PaginatedCategories;
}) {
    return (
        <>
            <Head title="Document categories" />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Document categories"
                        description="Manage document category hierarchy"
                    />
                    <Button asChild>
                        <Link
                            href={DocumentCategoryController.create.url()}
                            prefetch
                        >
                            <Plus className="size-4" />
                            Add category
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">
                                    Short code
                                </th>
                                <th className="hidden px-4 py-3 font-medium md:table-cell">
                                    Parent
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
                            {categories.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No document categories found.{' '}
                                        <Link
                                            href={DocumentCategoryController.create.url()}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            Create one
                                        </Link>
                                    </td>
                                </tr>
                            ) : (
                                categories.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {row.category_name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.short_code}
                                        </td>
                                        <td className="hidden px-4 py-3 md:table-cell">
                                            {row.parent?.name ?? '—'}
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
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={DocumentCategoryController.show.url(
                                                            row.id,
                                                        )}
                                                        prefetch
                                                    >
                                                        View
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={DocumentCategoryController.edit.url(
                                                            row.id,
                                                        )}
                                                        prefetch
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                                <DeleteConfirmDialog
                                                    form={DocumentCategoryController.destroy.form(
                                                        row.id,
                                                    )}
                                                    title="Delete document category?"
                                                    description={`This will permanently delete ${row.category_name}. This cannot be undone.`}
                                                    confirmLabel="Delete category"
                                                    trigger={
                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                            type="button"
                                                        >
                                                            Delete
                                                        </Button>
                                                    }
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {categories.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {categories.links.map((link, index) =>
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

DocumentCategoriesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Document categories',
            href: DocumentCategoryController.index.url(),
        },
    ],
};
