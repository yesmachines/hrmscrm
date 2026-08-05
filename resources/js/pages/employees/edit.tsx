import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import EmployeeProfileController from '@/actions/App/Http/Controllers/EmployeeProfileController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import EmployeeProfileFormFields from './form-fields';

type Employee = {
    id: number;
    gender: string | null;
    dob_personal: string | null;
    marital_status: string | null;
    nationality: string | null;
    religion: string | null;
    blood_group: string | null;
    personal_email: string | null;
    personal_mobile: string | null;
    address_uae: string | null;
    emergency_contact_name: string | null;
    emergency_relation: string | null;
    emergency_mobile: string | null;
    home_country: number | null;
    address_home: string | null;
    home_mobile: string | null;
    home_emergency_name: string | null;
    home_emergency_relation: string | null;
    home_emergency_mobile: string | null;
    visa_type: string | null;
    visa_from: string | null;
    dob_passport: string | null;
    total_experience: number | null;
    highest_education: string | null;
    employee: {
        id: number;
        name: string;
        email: string;
    } | null;
};

export default function EmployeesEdit({ employee }: { employee: Employee }) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Employees',
                href: EmployeeProfileController.index.url(),
            },
            {
                title: employee.employee?.name ?? 'Employee',
                href: EmployeeProfileController.show.url(employee.id),
            },
            {
                title: 'Edit',
                href: EmployeeProfileController.edit.url(employee.id),
            },
        ],
    });

    return (
        <>
            <Head title={`Edit ${employee.employee?.name ?? 'employee'}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Edit employee"
                    description="Update this employee profile"
                />

                <Form
                    {...EmployeeProfileController.update.form(employee.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <EmployeeProfileFormFields
                                errors={errors}
                                lockedEmployee={employee.employee}
                                defaults={employee}
                            />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Update employee
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={EmployeeProfileController.show.url(
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
