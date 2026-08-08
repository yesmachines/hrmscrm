import { Form, Head, Link } from '@inertiajs/react';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import EmployeeProfileFormFields from './form-fields';

type DepartmentOption = {
    id: number;
    name: string;
};

type OrganisationOption = {
    id: number;
    name: string;
    short_name: string;
};

type OfficeLocationOption = {
    id: number;
    name: string;
    organisation_id: number;
};

type RoleOption = {
    name: string;
};

export default function EmployeesCreate({
    departments,
    organisations,
    officeLocations,
    roles,
}: {
    departments: DepartmentOption[];
    organisations: OrganisationOption[];
    officeLocations: OfficeLocationOption[];
    roles: RoleOption[];
}) {
    return (
        <>
            <Head title="Add employee" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-8 p-6 md:p-10">
                <Heading
                    title="Add employee"
                    description="Create a new employee account and profile"
                />

                <Form {...EmployeeController.store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <EmployeeProfileFormFields
                                errors={errors}
                                departments={departments}
                                organisations={organisations}
                                officeLocations={officeLocations}
                                roles={roles}
                            />

                            <div className="flex items-center justify-end gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Save employee1
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={EmployeeController.index.url()}>
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

EmployeesCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employees', href: EmployeeController.index.url() },
        { title: 'Add', href: EmployeeController.create.url() },
    ],
};
