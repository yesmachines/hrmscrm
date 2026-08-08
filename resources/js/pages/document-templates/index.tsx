import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DocumentTemplateController from '@/actions/App/Http/Controllers/Documents/DocumentTemplateController';
import Heading from '@/components/heading';
import RowActionsMenu from '@/components/row-actions-menu';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type TemplateRow = {
    id: number;
    document_type_id: number;
    template_name: string;
    template_code: string;
    status: number;
    document_type: { id: number; name: string; code: string } | null;
};

type PaginatedTemplates = {
    data: TemplateRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function DocumentTemplatesIndex({
    templates,
}: {
    templates: PaginatedTemplates;
}) {
    return (
        <>
            <Head title="Document templates" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Document templates"
                        description="Manage document template records"
                    />
                    <Button asChild>
                        <Link
                            href={DocumentTemplateController.create.url()}
                            prefetch
                        >
                            <Plus className="size-4" />
                            Add template
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Code</th>
                                <th className="hidden px-4 py-3 font-medium md:table-cell">
                                    Document type
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
                            {templates.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No document templates found.{' '}
                                        <Link
                                            href={DocumentTemplateController.create.url()}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            Create one
                                        </Link>
                                    </td>
                                </tr>
                            ) : (
                                templates.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {row.template_name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.template_code}
                                        </td>
                                        <td className="hidden px-4 py-3 md:table-cell">
                                            {row.document_type?.name ?? '—'}
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
                                                viewHref={DocumentTemplateController.show.url(
                                                    row.id,
                                                )}
                                                editHref={DocumentTemplateController.edit.url(
                                                    row.id,
                                                )}
                                                destroyForm={DocumentTemplateController.destroy.form(
                                                    row.id,
                                                )}
                                                deleteTitle="Delete document template?"
                                                deleteDescription={`This will permanently delete ${row.template_name}. This cannot be undone.`}
                                                deleteConfirmLabel="Delete template"
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {templates.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {templates.links.map((link, index) =>
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

DocumentTemplatesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Document templates',
            href: DocumentTemplateController.index.url(),
        },
    ],
};
