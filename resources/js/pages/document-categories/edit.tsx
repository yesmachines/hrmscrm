import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
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

type Category = {
    id: number;
    category_name: string;
    short_code: string;
    status: number;
    parent_id: number | null;
    parent: { id: number; name: string } | null;
};

export default function DocumentCategoriesEdit({
    category,
    parents,
}: {
    category: Category;
    parents: Option[];
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
            {
                title: 'Edit',
                href: DocumentCategoryController.edit.url(category.id),
            },
        ],
    });

    return (
        <>
            <Head title={`Edit ${category.category_name}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Edit document category"
                    description="Update category details"
                />

                <Form
                    {...DocumentCategoryController.update.form(category.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <DocumentCategoryFormFields
                                errors={errors}
                                defaults={category}
                                parents={parents}
                            />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Update category
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={DocumentCategoryController.show.url(
                                            category.id,
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
