import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import DocumentTypeController from '@/actions/App/Http/Controllers/Documents/DocumentTypeController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import DocumentTypeFormFields from './form-fields';

type Option = {
    id: number;
    name: string;
};

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

export default function DocumentTypesEdit({
    documentType,
    categories,
}: {
    documentType: DocumentType;
    categories: Option[];
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
            {
                title: 'Edit',
                href: DocumentTypeController.edit.url(documentType.id),
            },
        ],
    });

    return (
        <>
            <Head title={`Edit ${documentType.document_name}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Edit document type"
                    description="Update document type details"
                />

                <Form
                    {...DocumentTypeController.update.form(documentType.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <DocumentTypeFormFields
                                errors={errors}
                                defaults={documentType}
                                categories={categories}
                            />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Update document type
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={DocumentTypeController.show.url(
                                            documentType.id,
                                        )}
                                    >
                                        Cancel
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
