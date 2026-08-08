import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
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

type OfficeLocation = {
    id: number;
    organisation_id: number;
    office_name: string;
    country_id: number;
    city: string;
    address: string;
};

export default function OfficeLocationsEdit({
    officeLocation,
    organisations,
    countries,
}: {
    officeLocation: OfficeLocation;
    organisations: Option[];
    countries: Option[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Office locations',
                href: OfficeLocationController.index.url(),
            },
            {
                title: officeLocation.office_name,
                href: OfficeLocationController.show.url(officeLocation.id),
            },
            {
                title: 'Edit',
                href: OfficeLocationController.edit.url(officeLocation.id),
            },
        ],
    });

    return (
        <>
            <Head title={`Edit ${officeLocation.office_name}`} />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Edit office location"
                    description="Update office location details"
                />

                <Form
                    {...OfficeLocationController.update.form(officeLocation.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <OfficeLocationFormFields
                                errors={errors}
                                defaults={officeLocation}
                                organisations={organisations}
                                countries={countries}
                            />

                            <div className="flex items-center justify-end gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Update office location
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={OfficeLocationController.show.url(
                                            officeLocation.id,
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
