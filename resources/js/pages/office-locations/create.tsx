import { Form, Head, Link } from '@inertiajs/react';
import OfficeLocationController from '@/actions/App/Http/Controllers/Organisation/OfficeLocationController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import OfficeLocationFormFields from './form-fields';

type Option = {
    id: number;
    name: string;
    short_name?: string;
    code?: string;
};

export default function OfficeLocationsCreate({
    organisations,
    countries,
}: {
    organisations: Option[];
    countries: Option[];
}) {
    return (
        <>
            <Head title="Add office location" />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Add office location"
                    description="Create a new office location"
                />

                <Form
                    {...OfficeLocationController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <OfficeLocationFormFields
                                errors={errors}
                                organisations={organisations}
                                countries={countries}
                            />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Save office location
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={OfficeLocationController.index.url()}
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

OfficeLocationsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Office locations',
            href: OfficeLocationController.index.url(),
        },
        { title: 'Add', href: OfficeLocationController.create.url() },
    ],
};
