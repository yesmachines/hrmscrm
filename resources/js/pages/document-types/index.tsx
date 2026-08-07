import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DocumentTypeController from '@/actions/App/Http/Controllers/Documents/DocumentTypeController';
import Heading from '@/components/heading';
import RowActionsMenu from '@/components/row-actions-menu';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type DocumentTypeRow = {
    id: number;
    category_id: number;
    document_name: string;
    document_code: string;
    requires_number: number;
    requires_expiry: number;
    editable_before_approval: number;
    requires_hr_approval: number;
    requires_reminder: number;
    record_source: string | null;
    requires_attachments: number;
    category: { id: number; name: string } | null;
};

type PaginatedDocumentTypes = {
    data: DocumentTypeRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

function formatRecordSource(value: string | null): string {
    if (!value) {
        return '—';
    }

    return value.charAt(0).toUpperCase() + value.slice(1);
}

export default function DocumentTypesIndex({
    documentTypes,
}: {
    documentTypes: PaginatedDocumentTypes;
}) {
    return (
        <>
            <Head title="Document types" />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Document types"
                        description="Manage document type definitions"
                    />
                    <Button asChild>
                        <Link
                            href={DocumentTypeController.create.url()}
                            prefetch
                        >
                            <Plus className="size-4" />
                            Add document type
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
                                    Category
                                </th>
                                <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                    Record source
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {documentTypes.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No document types found.{' '}
                                        <Link
                                            href={DocumentTypeController.create.url()}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            Create one
                                        </Link>
                                    </td>
                                </tr>
                            ) : (
                                documentTypes.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {row.document_name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.document_code}
                                        </td>
                                        <td className="hidden px-4 py-3 md:table-cell">
                                            {row.category?.name ?? '—'}
                                        </td>
                                        <td className="hidden px-4 py-3 sm:table-cell">
                                            {formatRecordSource(
                                                row.record_source,
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <RowActionsMenu
                                                viewHref={DocumentTypeController.show.url(
                                                    row.id,
                                                )}
                                                editHref={DocumentTypeController.edit.url(
                                                    row.id,
                                                )}
                                                destroyForm={DocumentTypeController.destroy.form(
                                                    row.id,
                                                )}
                                                deleteTitle="Delete document type?"
                                                deleteDescription={`This will permanently delete ${row.document_name}. This cannot be undone.`}
                                                deleteConfirmLabel="Delete document type"
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {documentTypes.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {documentTypes.links.map((link, index) =>
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

DocumentTypesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Document types',
            href: DocumentTypeController.index.url(),
        },
    ],
};
