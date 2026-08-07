import { Form, Head, Link } from '@inertiajs/react';
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

export default function DocumentTypesCreate({
    categories,
}: {
    categories: Option[];
}) {
    return (
        <>
            <Head title="Add document type" />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Add document type"
                    description="Create a new document type"
                />

                <Form
                    {...DocumentTypeController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <DocumentTypeFormFields
                                errors={errors}
                                categories={categories}
                            />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Save document type
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={DocumentTypeController.index.url()}
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

DocumentTypesCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Document types',
            href: DocumentTypeController.index.url(),
        },
        { title: 'Add', href: DocumentTypeController.create.url() },
    ],
};
