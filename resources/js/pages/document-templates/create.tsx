import { Form, Head, Link } from '@inertiajs/react';
import DocumentTemplateController from '@/actions/App/Http/Controllers/DocumentTemplateController';
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

export default function DocumentTemplatesCreate({
    documentTypes,
}: {
    documentTypes: Option[];
}) {
    return (
        <>
            <Head title="Add document template" />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Add document template"
                    description="Create a new document template"
                />

                <Form
                    {...DocumentTemplateController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <DocumentTemplateFormFields
                                errors={errors}
                                documentTypes={documentTypes}
                            />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Save template
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={DocumentTemplateController.index.url()}
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

DocumentTemplatesCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Document templates',
            href: DocumentTemplateController.index.url(),
        },
        { title: 'Add', href: DocumentTemplateController.create.url() },
    ],
};
