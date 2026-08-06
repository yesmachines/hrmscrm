import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import OrganisationController from '@/actions/App/Http/Controllers/OrganisationController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import OrganisationFormFields from './form-fields';

type Organisation = {
    id: number;
    org_name: string;
    short_name: string;
    logo: string | null;
    status: number;
};

export default function OrganisationsEdit({
    organisation,
}: {
    organisation: Organisation;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Organisations',
                href: OrganisationController.index.url(),
            },
            {
                title: organisation.org_name,
                href: OrganisationController.show.url(organisation.id),
            },
            {
                title: 'Edit',
                href: OrganisationController.edit.url(organisation.id),
            },
        ],
    });

    return (
        <>
            <Head title={`Edit ${organisation.org_name}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Edit organisation"
                    description="Update organisation details"
                />

                <Form
                    {...OrganisationController.update.form(organisation.id)}
                    encType="multipart/form-data"
                    forceFormData
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <OrganisationFormFields
                                errors={errors}
                                defaults={organisation}
                            />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Update organisation
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={OrganisationController.show.url(
                                            organisation.id,
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
