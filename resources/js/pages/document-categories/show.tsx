import type { ReactNode } from 'react';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import DocumentCategoryController from '@/actions/App/Http/Controllers/Documents/DocumentCategoryController';
import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type Category = {
    id: number;
    category_name: string;
    short_code: string;
    status: number;
    parent_id: number | null;
    parent: { id: number; name: string } | null;
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

export default function DocumentCategoriesShow({
    category,
}: {
    category: Category;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Document categories',
                href: DocumentCategoryController.index.url(),
            },
            {
                title: category.category_name,
                href: DocumentCategoryController.show.url(category.id),
            },
        ],
    });

    return (
        <>
            <Head title={category.category_name} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={category.category_name}
                        description={category.short_code}
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={DocumentCategoryController.edit.url(
                                    category.id,
                                )}
                                prefetch
                            >
                                Edit
                            </Link>
                        </Button>
                        <DeleteConfirmDialog
                            form={DocumentCategoryController.destroy.form(
                                category.id,
                            )}
                            title="Delete document category?"
                            description={`This will permanently delete ${category.category_name}. This cannot be undone.`}
                            confirmLabel="Delete category"
                        />
                    </div>
                </div>

                <Section title="Details">
                    <Detail
                        label="Category name"
                        value={category.category_name}
                    />
                    <Detail label="Short code" value={category.short_code} />
                    <Detail label="Parent" value={category.parent?.name} />
                    <Detail
                        label="Status"
                        value={category.status === 1 ? 'Active' : 'Inactive'}
                    />
                </Section>
            </div>
        </>
    );
}
