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

type DivisionOption = {
    id: number;
    name: string;
    code: string;
    value: string;
};

type DesignationOption = {
    id: number;
    title: string;
    shortcode: string;
    department_id: number;
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
    divisions,
    designations,
    organisations,
    officeLocations,
    roles,
    countries,
}: {
    departments: DepartmentOption[];
    divisions: DivisionOption[];
    designations: DesignationOption[];
    organisations: OrganisationOption[];
    officeLocations: OfficeLocationOption[];
    roles: RoleOption[];
    countries: { id: number; name: string }[];
}) {
    return (
        <>
            <Head title="Add employee" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">Add Employee</h1>
                        <p className="text-sm text-muted-foreground">
                            Create a new employee account, work credentials, and profile details
                        </p>
                    </div>
                    <div className="flex items-center gap-2.5">
                        <Button variant="outline" asChild>
                            <Link href={EmployeeController.index.url()}>
                                Back to Employees
                            </Link>
                        </Button>
                    </div>
                </div>

                <Form
                    {...EmployeeController.store.form()}
                    encType="multipart/form-data"
                    forceFormData
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <EmployeeProfileFormFields
                                errors={errors}
                                departments={departments}
                                divisions={divisions}
                                designations={designations}
                                organisations={organisations}
                                officeLocations={officeLocations}
                                roles={roles}
                                countries={countries}
                            />

                            <div className="sticky bottom-4 z-10 flex items-center justify-end gap-3 rounded-xl border border-border/80 bg-background/95 p-4 shadow-lg backdrop-blur-md">
                                <Button variant="outline" asChild>
                                    <Link href={EmployeeController.index.url()}>
                                        Cancel
                                    </Link>
                                </Button>
                                <Button type="submit" disabled={processing} className="min-w-36">
                                    {processing && <Spinner />}
                                    Save employee
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
