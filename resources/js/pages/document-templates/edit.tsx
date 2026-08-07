import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import DocumentTemplateController from '@/actions/App/Http/Controllers/Documents/DocumentTemplateController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import DocumentTemplateFormFields from './form-fields';

type Option = {
    id: number;
    name: string;
    code?: string;
};

type Template = {
    id: number;
    document_type_id: number;
    template_name: string;
    template_code: string;
    status: number;
    document_type: { id: number; name: string; code: string } | null;
};

export default function DocumentTemplatesEdit({
    template,
    documentTypes,
}: {
    template: Template;
    documentTypes: Option[];
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
            {
                title: 'Edit',
                href: DocumentTemplateController.edit.url(template.id),
            },
        ],
    });

    return (
        <>
            <Head title={`Edit ${template.template_name}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Edit document template"
                    description="Update template details"
                />

                <Form
                    {...DocumentTemplateController.update.form(template.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <DocumentTemplateFormFields
                                errors={errors}
                                defaults={template}
                                documentTypes={documentTypes}
                            />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Update template
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={DocumentTemplateController.show.url(
                                            template.id,
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
