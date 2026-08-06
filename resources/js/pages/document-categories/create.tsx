import { Form, Head, Link } from '@inertiajs/react';
import DocumentCategoryController from '@/actions/App/Http/Controllers/DocumentCategoryController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import DocumentCategoryFormFields from './form-fields';

type Option = {
    id: number;
    name: string;
};

export default function DocumentCategoriesCreate({
    parents,
}: {
    parents: Option[];
}) {
    return (
        <>
            <Head title="Add document category" />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Add document category"
                    description="Create a new document category"
                />

                <Form
                    {...DocumentCategoryController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <DocumentCategoryFormFields
                                errors={errors}
                                parents={parents}
                            />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Save category
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={DocumentCategoryController.index.url()}
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

DocumentCategoriesCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Document categories',
            href: DocumentCategoryController.index.url(),
        },
        { title: 'Add', href: DocumentCategoryController.create.url() },
    ],
};
