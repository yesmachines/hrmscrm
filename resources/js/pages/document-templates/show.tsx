import type { ReactNode } from 'react';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import DocumentTemplateController from '@/actions/App/Http/Controllers/Documents/DocumentTemplateController';
import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type Template = {
    id: number;
    document_type_id: number;
    template_name: string;
    template_code: string;
    status: number;
    document_type: { id: number; name: string; code: string } | null;
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

export default function DocumentTemplatesShow({
    template,
}: {
    template: Template;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Document templates',
                href: DocumentTemplateController.index.url(),
            },
            {
                title: template.template_name,
                href: DocumentTemplateController.show.url(template.id),
            },
        ],
    });

    return (
        <>
            <Head title={template.template_name} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={template.template_name}
                        description={template.template_code}
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={DocumentTemplateController.edit.url(
                                    template.id,
                                )}
                                prefetch
                            >
                                Edit
                            </Link>
                        </Button>
                        <DeleteConfirmDialog
                            form={DocumentTemplateController.destroy.form(
                                template.id,
                            )}
                            title="Delete document template?"
                            description={`This will permanently delete ${template.template_name}. This cannot be undone.`}
                            confirmLabel="Delete template"
                        />
                    </div>
                </div>

                <Section title="Details">
                    <Detail
                        label="Template name"
                        value={template.template_name}
                    />
                    <Detail
                        label="Template code"
                        value={template.template_code}
                    />
                    <Detail
                        label="Document type"
                        value={
                            template.document_type
                                ? `${template.document_type.name} (${template.document_type.code})`
                                : null
                        }
                    />
                    <Detail
                        label="Status"
                        value={template.status === 1 ? 'Active' : 'Inactive'}
                    />
                </Section>
            </div>
        </>
    );
}
