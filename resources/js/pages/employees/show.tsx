import type { ReactNode } from 'react';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type Profile = {
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
    employment_status: string | null;
    organisation_id: number | null;
    office_location_id: number | null;
    joining_date: string | null;
    resignation_date: string | null;
    division: string;
    image_url: string | null;
    status: number;
    has_report: boolean;
    department: { id: number; name: string } | null;
    organisation: { id: number; name: string; short_name: string } | null;
    office_location: { id: number; name: string; city: string } | null;
    profile: Profile | null;
};

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined | boolean;
}) {
    let display: string | number = '—';

    if (typeof value === 'boolean') {
        display = value ? 'Yes' : 'No';
    } else if (value !== null && value !== undefined && value !== '') {
        display = value;
    }

    return (
        <div className="space-y-1">
            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="text-sm text-foreground">{display}</dd>
        </div>
    );
}

function Section({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4 rounded-2xl border border-border bg-white p-5 shadow-sm">
            <h3 className="text-sm font-semibold tracking-tight">{title}</h3>
            <dl className="grid gap-4 sm:grid-cols-2">{children}</dl>
        </section>
    );
}

export default function EmployeesShow({ employee }: { employee: Employee }) {
    const name = employee.name ?? 'Employee';
    const profile = employee.profile;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Employees', href: EmployeeController.index.url() },
            {
                title: name,
                href: EmployeeController.show.url(employee.id),
            },
        ],
    });

    return (
        <>
            <Head title={name} />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title={name} description={employee.email ?? undefined} />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={EmployeeController.edit.url(employee.id)}
                                prefetch
                            >
                                Edit
                            </Link>
                        </Button>
                        <DeleteConfirmDialog
                            form={EmployeeController.destroy.form(employee.id)}
                            title="Delete employee?"
                            description={`This will permanently delete ${name}. This cannot be undone.`}
                            confirmLabel="Delete employee"
                        />
                    </div>
                </div>

                <Section title="Employee">
                    <Detail label="Employee number" value={employee.emp_num} />
                    <Detail
                        label="Employee code"
                        value={employee.employee_code}
                    />
                    <Detail label="ACL / Role" value={employee.roles} />
                    <Detail label="Designation" value={employee.designation} />
                    <Detail label="Division" value={employee.division} />
                    <Detail label="Phone" value={employee.phone} />
                    <Detail
                        label="Employment status"
                        value={employee.employment_status}
                    />
                    <Detail
                        label="Department"
                        value={employee.department?.name}
                    />
                    <Detail
                        label="Organisation"
                        value={employee.organisation?.name}
                    />
                    <Detail
                        label="Office location"
                        value={employee.office_location?.name}
                    />
                    <Detail label="Joining date" value={employee.joining_date} />
                    <Detail
                        label="Resignation date"
                        value={employee.resignation_date}
                    />
                    <Detail
                        label="Status"
                        value={employee.status === 1 ? 'Active' : 'Inactive'}
                    />
                    <Detail label="Has report" value={employee.has_report} />
                </Section>

                <Section title="Personal profile">
                    <Detail label="Gender" value={profile?.gender} />
                    <Detail label="Date of birth" value={profile?.dob_personal} />
                    <Detail
                        label="Marital status"
                        value={profile?.marital_status}
                    />
                    <Detail label="Nationality" value={profile?.nationality} />
                    <Detail label="Religion" value={profile?.religion} />
                    <Detail label="Blood group" value={profile?.blood_group} />
                    <Detail
                        label="Personal email"
                        value={profile?.personal_email}
                    />
                    <Detail
                        label="Personal mobile"
                        value={profile?.personal_mobile}
                    />
                    <Detail label="Address UAE" value={profile?.address_uae} />
                    <Detail
                        label="Visa type"
                        value={profile?.visa_type}
                    />
                    <Detail
                        label="Highest education"
                        value={profile?.highest_education}
                    />
                    <Detail
                        label="Total experience"
                        value={profile?.total_experience}
                    />
                </Section>
            </div>
        </>
    );
}
