import { type ReactNode, useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Errors = Record<string, string>;

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

type EmployeeFormValues = {
    name?: string | null;
    email?: string | null;
    roles?: string | null;
    emp_num?: string | null;
    employee_code?: string | null;
    phone?: string | null;
    designation?: string | null;
    designation_id?: number | string | null;
    employment_status?: string | null;
    organisation_id?: number | string | null;
    office_location_id?: number | string | null;
    joining_date?: string | null;
    resignation_date?: string | null;
    division?: string | null;
    image_url?: string | null;
    status?: number | string | null;
    has_report?: boolean | number | null;
    department_id?: number | string | null;
    gender?: string | null;
    dob_personal?: string | null;
    marital_status?: string | null;
    nationality?: string | null;
    religion?: string | null;
    blood_group?: string | null;
    personal_email?: string | null;
    personal_mobile?: string | null;
    address_uae?: string | null;
    emergency_contact_name?: string | null;
    emergency_relation?: string | null;
    emergency_mobile?: string | null;
    home_country?: number | string | null;
    address_home?: string | null;
    home_mobile?: string | null;
    home_emergency_name?: string | null;
    home_emergency_relation?: string | null;
    home_emergency_mobile?: string | null;
    visa_type?: string | null;
    visa_from?: string | null;
    dob_passport?: string | null;
    total_experience?: number | string | null;
    highest_education?: string | null;
};

type Props = {
    errors: Errors;
    defaults?: EmployeeFormValues;
    departments?: DepartmentOption[];
    organisations?: OrganisationOption[];
    officeLocations?: OfficeLocationOption[];
    roles?: RoleOption[];
    isEdit?: boolean;
};

const fieldClass =
    'h-10 w-full rounded-lg border border-input bg-white px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

function Field({
    label,
    name,
    error,
    children,
}: {
    label: string;
    name: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section className="flex flex-col gap-5 rounded-[1.25rem] border border-border/50 bg-card/80 backdrop-blur-sm p-6 shadow-sm md:p-8">
            <div>
                <h3 className="text-base font-semibold tracking-tight text-foreground">
                    {title}
                </h3>
                {description && (
                    <p className="mt-1 text-sm text-muted-foreground leading-relaxed">
                        {description}
                    </p>
                )}
            </div>
            <div className="grid gap-5 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">{children}</div>
        </section>
    );
}

export default function EmployeeProfileFormFields({
    errors,
    defaults = {},
    departments = [],
    organisations = [],
    officeLocations = [],
    roles = [],
    isEdit = false,
}: Props) {
    const profile = (defaults as EmployeeFormValues & { profile?: EmployeeFormValues })
        .profile;

    const profileDefaults = {
        ...defaults,
        ...(profile ?? {}),
    };

    const [joiningDate, setJoiningDate] = useState(defaults.joining_date ?? '');

    return (
        <div className="space-y-5">
            <Section title="Account">
                <Field label="Full name" name="name" error={errors.name}>
                    <Input
                        id="name"
                        name="name"
                        required
                        defaultValue={defaults.name ?? ''}
                    />
                </Field>
                <Field label="Work email" name="email" error={errors.email}>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        defaultValue={defaults.email ?? ''}
                    />
                </Field>
                <Field
                    label={isEdit ? 'Password (optional)' : 'Password'}
                    name="password"
                    error={errors.password}
                >
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required={!isEdit}
                        autoComplete="new-password"
                    />
                </Field>
                <Field
                    label="ACL / Role"
                    name="roles"
                    error={errors.roles}
                >
                    <select
                        id="roles"
                        name="roles"
                        required={!isEdit}
                        defaultValue={defaults.roles ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select</option>
                        {roles.map((role) => (
                            <option key={role.name} value={role.name}>
                                {role.name}
                            </option>
                        ))}
                    </select>
                </Field>
            </Section>

            <Section title="Employee">
                <Field label="Employee number" name="emp_num" error={errors.emp_num}>
                    <Input
                        id="emp_num"
                        name="emp_num"
                        required
                        defaultValue={defaults.emp_num ?? ''}
                    />
                </Field>
                <Field
                    label="Employee code"
                    name="employee_code"
                    error={errors.employee_code}
                >
                    <Input
                        id="employee_code"
                        name="employee_code"
                        defaultValue={defaults.employee_code ?? ''}
                    />
                </Field>
                <Field
                    label="Designation"
                    name="designation"
                    error={errors.designation}
                >
                    <Input
                        id="designation"
                        name="designation"
                        required
                        defaultValue={defaults.designation ?? ''}
                    />
                </Field>
                <Field label="Division" name="division" error={errors.division}>
                    <Input
                        id="division"
                        name="division"
                        required
                        defaultValue={defaults.division ?? ''}
                    />
                </Field>
                <Field label="Phone" name="phone" error={errors.phone}>
                    <Input
                        id="phone"
                        name="phone"
                        defaultValue={defaults.phone ?? ''}
                    />
                </Field>
                <Field
                    label="Employment status"
                    name="employment_status"
                    error={errors.employment_status}
                >
                    <select
                        id="employment_status"
                        name="employment_status"
                        defaultValue={defaults.employment_status ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select</option>
                        <option value="fulltime">Full time</option>
                        <option value="parttime">Part time</option>
                        <option value="contract">Contract</option>
                        <option value="intern">Intern</option>
                    </select>
                </Field>
                <Field
                    label="Department"
                    name="department_id"
                    error={errors.department_id}
                >
                    <select
                        id="department_id"
                        name="department_id"
                        defaultValue={defaults.department_id ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select</option>
                        {departments.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.name}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field
                    label="Organisation"
                    name="organisation_id"
                    error={errors.organisation_id}
                >
                    <select
                        id="organisation_id"
                        name="organisation_id"
                        defaultValue={defaults.organisation_id ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select</option>
                        {organisations.map((organisation) => (
                            <option
                                key={organisation.id}
                                value={organisation.id}
                            >
                                {organisation.name}
                                {organisation.short_name
                                    ? ` (${organisation.short_name})`
                                    : ''}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field
                    label="Office location"
                    name="office_location_id"
                    error={errors.office_location_id}
                >
                    <select
                        id="office_location_id"
                        name="office_location_id"
                        defaultValue={defaults.office_location_id ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select</option>
                        {officeLocations.map((location) => (
                            <option key={location.id} value={location.id}>
                                {location.name}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field
                    label="Designation ID"
                    name="designation_id"
                    error={errors.designation_id}
                >
                    <Input
                        id="designation_id"
                        type="number"
                        name="designation_id"
                        defaultValue={defaults.designation_id ?? ''}
                    />
                </Field>
                <Field
                    label="Joining date"
                    name="joining_date"
                    error={errors.joining_date}
                >
                    <Input
                        id="joining_date"
                        type="date"
                        name="joining_date"
                        value={joiningDate}
                        onChange={(e) => setJoiningDate(e.target.value)}
                    />
                </Field>
                <Field
                    label="Resignation date"
                    name="resignation_date"
                    error={errors.resignation_date}
                >
                    <Input
                        id="resignation_date"
                        type="date"
                        name="resignation_date"
                        defaultValue={defaults.resignation_date ?? ''}
                        min={joiningDate}
                    />
                </Field>
                <Field label="Status" name="status" error={errors.status}>
                    <select
                        id="status"
                        name="status"
                        defaultValue={
                            defaults.status === null || defaults.status === undefined
                                ? '1'
                                : String(defaults.status)
                        }
                        className={fieldClass}
                    >
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </Field>
                <Field
                    label="Has report"
                    name="has_report"
                    error={errors.has_report}
                >
                    <select
                        id="has_report"
                        name="has_report"
                        defaultValue={
                            defaults.has_report === false || defaults.has_report === 0
                                ? '0'
                                : '1'
                        }
                        className={fieldClass}
                    >
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </Field>
                <div className="sm:col-span-2">
                    <Field
                        label="Image URL"
                        name="image_url"
                        error={errors.image_url}
                    >
                        <Input
                            id="image_url"
                            name="image_url"
                            defaultValue={defaults.image_url ?? ''}
                        />
                    </Field>
                </div>
            </Section>

            <Section title="Personal profile">
                <Field label="Gender" name="gender" error={errors.gender}>
                    <select
                        id="gender"
                        name="gender"
                        defaultValue={profileDefaults.gender ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select</option>
                        <option value="M">Male</option>
                        <option value="F">Female</option>
                    </select>
                </Field>
                <Field
                    label="Date of birth"
                    name="dob_personal"
                    error={errors.dob_personal}
                >
                    <Input
                        id="dob_personal"
                        type="date"
                        name="dob_personal"
                        defaultValue={profileDefaults.dob_personal ?? ''}
                    />
                </Field>
                <Field
                    label="Marital status"
                    name="marital_status"
                    error={errors.marital_status}
                >
                    <select
                        id="marital_status"
                        name="marital_status"
                        defaultValue={profileDefaults.marital_status ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select</option>
                        <option value="single">Single</option>
                        <option value="married">Married</option>
                        <option value="divorced">Divorced</option>
                        <option value="widowed">Widowed</option>
                    </select>
                </Field>
                <Field
                    label="Nationality"
                    name="nationality"
                    error={errors.nationality}
                >
                    <Input
                        id="nationality"
                        name="nationality"
                        defaultValue={profileDefaults.nationality ?? ''}
                    />
                </Field>
                <Field label="Religion" name="religion" error={errors.religion}>
                    <Input
                        id="religion"
                        name="religion"
                        defaultValue={profileDefaults.religion ?? ''}
                    />
                </Field>
                <Field
                    label="Blood group"
                    name="blood_group"
                    error={errors.blood_group}
                >
                    <Input
                        id="blood_group"
                        name="blood_group"
                        defaultValue={profileDefaults.blood_group ?? ''}
                    />
                </Field>
            </Section>

            <Section title="Contact (UAE)">
                <Field
                    label="Personal email"
                    name="personal_email"
                    error={errors.personal_email}
                >
                    <Input
                        id="personal_email"
                        type="email"
                        name="personal_email"
                        defaultValue={profileDefaults.personal_email ?? ''}
                    />
                </Field>
                <Field
                    label="Personal mobile"
                    name="personal_mobile"
                    error={errors.personal_mobile}
                >
                    <Input
                        id="personal_mobile"
                        name="personal_mobile"
                        defaultValue={profileDefaults.personal_mobile ?? ''}
                    />
                </Field>
                <div className="sm:col-span-2">
                    <Field
                        label="Address (UAE)"
                        name="address_uae"
                        error={errors.address_uae}
                    >
                        <Input
                            id="address_uae"
                            name="address_uae"
                            defaultValue={profileDefaults.address_uae ?? ''}
                        />
                    </Field>
                </div>
                <Field
                    label="Emergency contact"
                    name="emergency_contact_name"
                    error={errors.emergency_contact_name}
                >
                    <Input
                        id="emergency_contact_name"
                        name="emergency_contact_name"
                        defaultValue={
                            profileDefaults.emergency_contact_name ?? ''
                        }
                    />
                </Field>
                <Field
                    label="Emergency relation"
                    name="emergency_relation"
                    error={errors.emergency_relation}
                >
                    <Input
                        id="emergency_relation"
                        name="emergency_relation"
                        defaultValue={profileDefaults.emergency_relation ?? ''}
                    />
                </Field>
                <Field
                    label="Emergency mobile"
                    name="emergency_mobile"
                    error={errors.emergency_mobile}
                >
                    <Input
                        id="emergency_mobile"
                        name="emergency_mobile"
                        defaultValue={profileDefaults.emergency_mobile ?? ''}
                    />
                </Field>
            </Section>

            <Section title="Home country">
                <Field
                    label="Home country code"
                    name="home_country"
                    error={errors.home_country}
                >
                    <Input
                        id="home_country"
                        type="number"
                        name="home_country"
                        defaultValue={profileDefaults.home_country ?? ''}
                    />
                </Field>
                <Field
                    label="Home mobile"
                    name="home_mobile"
                    error={errors.home_mobile}
                >
                    <Input
                        id="home_mobile"
                        name="home_mobile"
                        defaultValue={profileDefaults.home_mobile ?? ''}
                    />
                </Field>
                <div className="sm:col-span-2">
                    <Field
                        label="Home address"
                        name="address_home"
                        error={errors.address_home}
                    >
                        <Input
                            id="address_home"
                            name="address_home"
                            defaultValue={profileDefaults.address_home ?? ''}
                        />
                    </Field>
                </div>
                <Field
                    label="Home emergency name"
                    name="home_emergency_name"
                    error={errors.home_emergency_name}
                >
                    <Input
                        id="home_emergency_name"
                        name="home_emergency_name"
                        defaultValue={
                            profileDefaults.home_emergency_name ?? ''
                        }
                    />
                </Field>
                <Field
                    label="Home emergency relation"
                    name="home_emergency_relation"
                    error={errors.home_emergency_relation}
                >
                    <Input
                        id="home_emergency_relation"
                        name="home_emergency_relation"
                        defaultValue={
                            profileDefaults.home_emergency_relation ?? ''
                        }
                    />
                </Field>
                <Field
                    label="Home emergency mobile"
                    name="home_emergency_mobile"
                    error={errors.home_emergency_mobile}
                >
                    <Input
                        id="home_emergency_mobile"
                        name="home_emergency_mobile"
                        defaultValue={
                            profileDefaults.home_emergency_mobile ?? ''
                        }
                    />
                </Field>
            </Section>

            <Section title="Visa & education">
                <Field
                    label="Visa type"
                    name="visa_type"
                    error={errors.visa_type}
                >
                    <select
                        id="visa_type"
                        name="visa_type"
                        defaultValue={profileDefaults.visa_type ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select</option>
                        <option value="visa">Visa</option>
                        <option value="workpermit">Work permit</option>
                    </select>
                </Field>
                <Field
                    label="Visa from"
                    name="visa_from"
                    error={errors.visa_from}
                >
                    <Input
                        id="visa_from"
                        name="visa_from"
                        defaultValue={profileDefaults.visa_from ?? ''}
                    />
                </Field>
                <Field
                    label="Passport DOB"
                    name="dob_passport"
                    error={errors.dob_passport}
                >
                    <Input
                        id="dob_passport"
                        type="date"
                        name="dob_passport"
                        defaultValue={profileDefaults.dob_passport ?? ''}
                    />
                </Field>
                <Field
                    label="Total experience (years)"
                    name="total_experience"
                    error={errors.total_experience}
                >
                    <Input
                        id="total_experience"
                        type="number"
                        step="0.1"
                        min="0"
                        name="total_experience"
                        defaultValue={profileDefaults.total_experience ?? ''}
                    />
                </Field>
                <Field
                    label="Highest education"
                    name="highest_education"
                    error={errors.highest_education}
                >
                    <Input
                        id="highest_education"
                        name="highest_education"
                        defaultValue={profileDefaults.highest_education ?? ''}
                    />
                </Field>
            </Section>
        </div>
    );
}
