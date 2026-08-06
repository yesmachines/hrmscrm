import { Form, Head, Link } from '@inertiajs/react';
import OrganisationController from '@/actions/App/Http/Controllers/OrganisationController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import OrganisationFormFields from './form-fields';

export default function OrganisationsCreate() {
    return (
        <>
            <Head title="Add organisation" />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Add organisation"
                    description="Create a new organisation record"
                />

                <Form
                    {...OrganisationController.store.form()}
                    encType="multipart/form-data"
                    forceFormData
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <OrganisationFormFields errors={errors} />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Save organisation
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={OrganisationController.index.url()}
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

OrganisationsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Organisations', href: OrganisationController.index.url() },
        { title: 'Add', href: OrganisationController.create.url() },
    ],
};
