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
    organisations,
    officeLocations,
    roles,
}: {
    employee: Employee;
    departments: DepartmentOption[];
    organisations: OrganisationOption[];
    officeLocations: OfficeLocationOption[];
    roles: RoleOption[];
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

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-8 p-6 md:p-10">
                <Heading
                    title="Edit employee"
                    description="Update employee account and profile"
                />

                <Form
                    {...EmployeeController.update.form(employee.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <EmployeeProfileFormFields
                                errors={errors}
                                departments={departments}
                                organisations={organisations}
                                officeLocations={officeLocations}
                                roles={roles}
                                defaults={employee}
                                isEdit
                            />

                            <div className="flex items-center justify-end gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Update employee
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={EmployeeController.show.url(
                                            employee.id,
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
