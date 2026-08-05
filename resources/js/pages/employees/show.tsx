import type { ReactNode } from 'react';
import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import EmployeeProfileController from '@/actions/App/Http/Controllers/EmployeeProfileController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';

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

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined;
}) {
    return (
        <div className="space-y-1">
            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="text-sm text-foreground">{value || '—'}</dd>
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
    const name = employee.employee?.name ?? 'Employee';

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Employees',
                href: EmployeeProfileController.index.url(),
            },
            {
                title: name,
                href: EmployeeProfileController.show.url(employee.id),
            },
        ],
    });

    return (
        <>
            <Head title={name} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={name}
                        description={employee.employee?.email}
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={EmployeeProfileController.edit.url(
                                    employee.id,
                                )}
                                prefetch
                            >
                                Edit
                            </Link>
                        </Button>
                        <Form
                            {...EmployeeProfileController.destroy.form(
                                employee.id,
                            )}
                            options={{
                                preserveScroll: true,
                            }}
                            onSubmit={(event) => {
                                if (
                                    !confirm(
                                        'Delete this employee profile? This cannot be undone.',
                                    )
                                ) {
                                    event.preventDefault();
                                }
                            }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Delete
                                </Button>
                            )}
                        </Form>
                    </div>
                </div>

                <Section title="Personal">
                    <Detail label="Gender" value={employee.gender} />
                    <Detail
                        label="Date of birth"
                        value={employee.dob_personal}
                    />
                    <Detail
                        label="Marital status"
                        value={employee.marital_status}
                    />
                    <Detail label="Nationality" value={employee.nationality} />
                    <Detail label="Religion" value={employee.religion} />
                    <Detail label="Blood group" value={employee.blood_group} />
                </Section>

                <Section title="Contact (UAE)">
                    <Detail
                        label="Personal email"
                        value={employee.personal_email}
                    />
                    <Detail
                        label="Personal mobile"
                        value={employee.personal_mobile}
                    />
                    <Detail label="Address" value={employee.address_uae} />
                    <Detail
                        label="Emergency contact"
                        value={employee.emergency_contact_name}
                    />
                    <Detail
                        label="Emergency relation"
                        value={employee.emergency_relation}
                    />
                    <Detail
                        label="Emergency mobile"
                        value={employee.emergency_mobile}
                    />
                </Section>

                <Section title="Home country">
                    <Detail
                        label="Country code"
                        value={employee.home_country}
                    />
                    <Detail label="Home mobile" value={employee.home_mobile} />
                    <Detail
                        label="Home address"
                        value={employee.address_home}
                    />
                    <Detail
                        label="Home emergency name"
                        value={employee.home_emergency_name}
                    />
                    <Detail
                        label="Home emergency relation"
                        value={employee.home_emergency_relation}
                    />
                    <Detail
                        label="Home emergency mobile"
                        value={employee.home_emergency_mobile}
                    />
                </Section>

                <Section title="Visa & education">
                    <Detail label="Visa type" value={employee.visa_type} />
                    <Detail label="Visa from" value={employee.visa_from} />
                    <Detail
                        label="Passport DOB"
                        value={employee.dob_passport}
                    />
                    <Detail
                        label="Total experience"
                        value={employee.total_experience}
                    />
                    <Detail
                        label="Highest education"
                        value={employee.highest_education}
                    />
                </Section>
            </div>
        </>
    );
}
