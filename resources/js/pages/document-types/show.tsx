import type { ReactNode } from 'react';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import DocumentTypeController from '@/actions/App/Http/Controllers/Documents/DocumentTypeController';
import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type DocumentType = {
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

function yesNo(value: number): string {
    return value === 1 ? 'Yes' : 'No';
}

function formatRecordSource(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return value.charAt(0).toUpperCase() + value.slice(1);
}

export default function DocumentTypesShow({
    documentType,
}: {
    documentType: DocumentType;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Document types',
                href: DocumentTypeController.index.url(),
            },
            {
                title: documentType.document_name,
                href: DocumentTypeController.show.url(documentType.id),
            },
        ],
    });

    return (
        <>
            <Head title={documentType.document_name} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={documentType.document_name}
                        description={documentType.document_code}
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={DocumentTypeController.edit.url(
                                    documentType.id,
                                )}
                                prefetch
                            >
                                Edit
                            </Link>
                        </Button>
                        <DeleteConfirmDialog
                            form={DocumentTypeController.destroy.form(
                                documentType.id,
                            )}
                            title="Delete document type?"
                            description={`This will permanently delete ${documentType.document_name}. This cannot be undone.`}
                            confirmLabel="Delete document type"
                        />
                    </div>
                </div>

                <Section title="Details">
                    <Detail
                        label="Document name"
                        value={documentType.document_name}
                    />
                    <Detail
                        label="Document code"
                        value={documentType.document_code}
                    />
                    <Detail
                        label="Category"
                        value={documentType.category?.name}
                    />
                    <Detail
                        label="Record source"
                        value={formatRecordSource(documentType.record_source)}
                    />
                    <Detail
                        label="Requires number"
                        value={yesNo(documentType.requires_number)}
                    />
                    <Detail
                        label="Requires expiry"
                        value={yesNo(documentType.requires_expiry)}
                    />
                    <Detail
                        label="Editable before approval"
                        value={yesNo(documentType.editable_before_approval)}
                    />
                    <Detail
                        label="Requires HR approval"
                        value={yesNo(documentType.requires_hr_approval)}
                    />
                    <Detail
                        label="Requires reminder"
                        value={yesNo(documentType.requires_reminder)}
                    />
                    <Detail
                        label="Requires attachments"
                        value={yesNo(documentType.requires_attachments)}
                    />
                </Section>
            </div>
        </>
    );
}
