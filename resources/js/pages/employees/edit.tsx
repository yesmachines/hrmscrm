import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
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

type Employee = {
    id: number;
    name: string | null;
    email: string | null;
    roles: string | null;
    emp_num: string;
    employee_code: string | null;
    phone: string | null;
    designation: string;
    designation_id: number | null;
    employment_status: string | null;
    organisation_id: number | null;
    office_location_id: number | null;
    joining_date: string | null;
    resignation_date: string | null;
    division: string;
    image_url: string | null;
    status: number;
    has_report: boolean;
    department_id: number | null;
    profile: Record<string, unknown> | null;
};

export default function EmployeesEdit({
    employee,
    departments,
    divisions,
    organisations,
    officeLocations,
    roles,
    countries,
}: {
    employee: Employee;
    departments: DepartmentOption[];
    divisions: DivisionOption[];
    organisations: OrganisationOption[];
    officeLocations: OfficeLocationOption[];
    roles: RoleOption[];
    countries: { id: number; name: string }[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Employees', href: EmployeeController.index.url() },
            {
                title: employee.name ?? 'Employee',
                href: EmployeeController.show.url(employee.id),
            },
            {
                title: 'Edit',
                href: EmployeeController.edit.url(employee.id),
            },
        ],
    });

    return (
        <>
            <Head title={`Edit ${employee.name ?? 'employee'}`} />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">
                            Edit Employee: {employee.name ?? 'Employee'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Update employee account, employment terms, and profile details
                        </p>
                    </div>
                    <div className="flex items-center gap-2.5">
                        <Button variant="outline" asChild>
                            <Link href={EmployeeController.show.url(employee.id)}>
                                View Details
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={EmployeeController.index.url()}>
                                Back to Employees
                            </Link>
                        </Button>
                    </div>
                </div>

                <Form
                    {...EmployeeController.update.form(employee.id)}
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
                                organisations={organisations}
                                officeLocations={officeLocations}
                                roles={roles}
                                countries={countries}
                                defaults={employee}
                                isEdit
                            />

                            <div className="sticky bottom-4 z-10 flex items-center justify-end gap-3 rounded-xl border border-border/80 bg-background/95 p-4 shadow-lg backdrop-blur-md">
                                <Button variant="outline" asChild>
                                    <Link
                                        href={EmployeeController.show.url(
                                            employee.id,
                                        )}
                                    >
                                        Cancel
                                    </Link>
                                </Button>
                                <Button type="submit" disabled={processing} className="min-w-36">
                                    {processing && <Spinner />}
                                    Update employee
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
