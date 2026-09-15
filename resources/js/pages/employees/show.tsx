import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { UserCheck, Users, Trash2, PlusCircle, Building2 } from 'lucide-react';
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
    home_country_name?: string | null;
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

type ReportingRelation = {
    id: number;
    name: string | null;
    email: string | null;
    emp_num: string;
    designation: string;
    division: string;
    department_id: number | null;
    department: { id: number; name: string; code: string | null } | null;
};

type DepartmentOption = {
    id: number;
    name: string;
    code?: string | null;
};

type AvailableManager = {
    id: number;
    name: string;
    designation: string;
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
            <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{children}</dl>
        </section>
    );
}

export default function EmployeesShow({
    employee,
    reportingManagers = [],
    reportingSubordinates = [],
    availableManagers = [],
    departments = [],
}: {
    employee: Employee;
    reportingManagers?: ReportingRelation[];
    reportingSubordinates?: ReportingRelation[];
    availableManagers?: AvailableManager[];
    departments?: DepartmentOption[];
}) {
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
                    <div className="flex items-center gap-4">
                        {employee.image_url ? (
                            <img
                                src={employee.image_url}
                                alt={name}
                                className="h-14 w-14 rounded-full border border-border object-cover shadow-xs"
                            />
                        ) : (
                            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 text-lg font-bold text-primary">
                                {name.charAt(0).toUpperCase()}
                            </div>
                        )}
                        <Heading title={name} description={employee.email ?? undefined} />
                    </div>
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
                    <Detail label="Department" value={employee.division} />
                    <Detail label="Phone" value={employee.phone} />
                    <Detail
                        label="Employment status"
                        value={employee.employment_status}
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
                </Section>

                <Section title="Personal profile">
                    <Detail label="Gender" value={profile?.gender === 'M' ? 'Male' : profile?.gender === 'F' ? 'Female' : profile?.gender} />
                    <Detail label="Date of birth" value={profile?.dob_personal} />
                    <Detail
                        label="Marital status"
                        value={profile?.marital_status ? profile.marital_status.charAt(0).toUpperCase() + profile.marital_status.slice(1) : null}
                    />
                    <Detail label="Nationality" value={profile?.nationality} />
                    <Detail label="Religion" value={profile?.religion} />
                    <Detail label="Blood group" value={profile?.blood_group} />
                </Section>

                <Section title="Contact (UAE)">
                    <Detail
                        label="Personal email"
                        value={profile?.personal_email}
                    />
                    <Detail
                        label="Personal mobile"
                        value={profile?.personal_mobile}
                    />
                    <Detail label="Address (UAE)" value={profile?.address_uae} />
                    <Detail
                        label="Emergency contact"
                        value={profile?.emergency_contact_name}
                    />
                    <Detail
                        label="Emergency relation"
                        value={profile?.emergency_relation}
                    />
                    <Detail
                        label="Emergency mobile"
                        value={profile?.emergency_mobile}
                    />
                </Section>

                <Section title="Home country">
                    <Detail
                        label="Home country"
                        value={profile?.home_country_name ?? profile?.home_country}
                    />
                    <Detail
                        label="Home mobile"
                        value={profile?.home_mobile}
                    />
                    <Detail
                        label="Home address"
                        value={profile?.address_home}
                    />
                    <Detail
                        label="Home emergency name"
                        value={profile?.home_emergency_name}
                    />
                    <Detail
                        label="Home emergency relation"
                        value={profile?.home_emergency_relation}
                    />
                    <Detail
                        label="Home emergency mobile"
                        value={profile?.home_emergency_mobile}
                    />
                </Section>

                <Section title="Visa & education">
                    <Detail
                        label="Visa type"
                        value={profile?.visa_type ? (profile.visa_type === 'workpermit' ? 'Work permit' : profile.visa_type.charAt(0).toUpperCase() + profile.visa_type.slice(1)) : null}
                    />
                    <Detail
                        label="Visa from"
                        value={profile?.visa_from}
                    />
                    <Detail
                        label="Passport DOB"
                        value={profile?.dob_passport}
                    />
                    <Detail
                        label="Total experience"
                        value={profile?.total_experience !== null && profile?.total_experience !== undefined ? `${profile.total_experience} years` : null}
                    />
                    <Detail
                        label="Highest education"
                        value={profile?.highest_education}
                    />
                </Section>

                {/* Reporting Hierarchy & Departments Section */}
                <section className="space-y-6 rounded-2xl border border-border bg-white p-6 shadow-sm">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-border/70 pb-4">
                        <div>
                            <h3 className="text-base font-semibold tracking-tight text-foreground flex items-center gap-2">
                                <UserCheck className="h-5 w-5 text-primary" />
                                Reporting Hierarchy & Departments
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Manage reporting managers and their assigned departments for {name}
                            </p>
                        </div>
                    </div>

                    {/* Reporting Managers (Top Level) */}
                    <div className="space-y-3">
                        <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                            Reporting Managers (Top Level)
                        </h4>

                        {reportingManagers.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-border/80 p-4 text-center text-xs text-muted-foreground">
                                No reporting manager assigned yet. Use the form below to assign a manager and department.
                            </div>
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {reportingManagers.map((mgr) => (
                                    <div
                                        key={mgr.id}
                                        className="flex flex-col justify-between gap-3 rounded-xl border border-border/80 bg-muted/20 p-4 shadow-2xs"
                                    >
                                        <div className="space-y-1">
                                            <div className="flex items-start justify-between gap-2">
                                                <Link
                                                    href={`/employees/${mgr.id}`}
                                                    className="text-sm font-semibold text-foreground hover:underline"
                                                >
                                                    {mgr.name}
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        if (confirm(`Remove ${mgr.name} as reporting manager?`)) {
                                                            router.delete(
                                                                `/employees/${employee.id}/managers/${mgr.id}`,
                                                                { preserveScroll: true },
                                                            );
                                                        }
                                                    }}
                                                    className="text-muted-foreground hover:text-destructive p-1 rounded transition-colors"
                                                    title="Remove manager"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {mgr.designation} (Emp #{mgr.emp_num})
                                            </p>
                                        </div>

                                        <div className="pt-2 border-t border-border/60 flex items-center justify-between">
                                            <span className="text-xs text-muted-foreground font-medium">
                                                Department:
                                            </span>
                                            <span className="inline-flex items-center rounded-md bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                                {mgr.department?.name ?? 'Sales / Division'}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Direct Subordinates (Low Level) */}
                    <div className="space-y-3 pt-2">
                        <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                            <Users className="h-4 w-4" />
                            Direct Subordinates (Team Members) ({reportingSubordinates.length})
                        </h4>

                        {reportingSubordinates.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-border/80 p-4 text-center text-xs text-muted-foreground">
                                No subordinates reporting to this employee.
                            </div>
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {reportingSubordinates.map((sub) => (
                                    <div
                                        key={sub.id}
                                        className="flex flex-col justify-between gap-2 rounded-xl border border-border/80 bg-muted/20 p-4 shadow-2xs"
                                    >
                                        <div>
                                            <Link
                                                href={`/employees/${sub.id}`}
                                                className="text-sm font-semibold text-foreground hover:underline"
                                            >
                                                {sub.name}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {sub.designation} (Emp #{sub.emp_num})
                                            </p>
                                        </div>
                                        <div className="pt-2 border-t border-border/60 flex items-center justify-between">
                                            <span className="text-xs text-muted-foreground">Department</span>
                                            <span className="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-foreground">
                                                {sub.department?.name ?? 'Sales / Division'}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Assign Manager Form */}
                    {availableManagers.length > 0 && (
                        <div className="rounded-xl border border-primary/20 bg-primary/5 p-4 sm:p-5 mt-4 space-y-3">
                            <div className="flex items-center gap-2">
                                <PlusCircle className="h-4 w-4 text-primary" />
                                <h4 className="text-xs font-semibold text-foreground uppercase tracking-wider">
                                    Assign Reporting Manager & Department
                                </h4>
                            </div>

                            <form
                                onSubmit={(e: FormEvent<HTMLFormElement>) => {
                                    e.preventDefault();
                                    const fd = new FormData(e.currentTarget);
                                    router.post(
                                        `/employees/${employee.id}/managers`,
                                        {
                                            manager_id: Number(fd.get('manager_id')),
                                            department_id: fd.get('department_id') ? Number(fd.get('department_id')) : null,
                                        },
                                        { preserveScroll: true },
                                    );
                                }}
                                className="grid gap-3 sm:grid-cols-3 items-end"
                            >
                                <div className="space-y-1">
                                    <label className="text-xs font-medium text-foreground">
                                        Select Manager <span className="text-destructive">*</span>
                                    </label>
                                    <select
                                        name="manager_id"
                                        required
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs"
                                    >
                                        <option value="">Choose Manager...</option>
                                        {availableManagers.map((m) => (
                                            <option key={m.id} value={m.id}>
                                                {m.name} ({m.designation})
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="space-y-1">
                                    <label className="text-xs font-medium text-foreground">
                                        Select Department
                                    </label>
                                    <select
                                        name="department_id"
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs"
                                    >
                                        <option value="">Default / Sales</option>
                                        {departments.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {d.name} {d.code ? `(${d.code})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <Button type="submit" size="sm">
                                    Assign Manager
                                </Button>
                            </form>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
