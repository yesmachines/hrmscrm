import { type ReactNode, useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Errors = Record<string, string>;

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
    divisions?: DivisionOption[];
    designations?: DesignationOption[];
    organisations?: OrganisationOption[];
    officeLocations?: OfficeLocationOption[];
    roles?: RoleOption[];
    countries?: { id: number; name: string }[];
    isEdit?: boolean;
};

const fieldClass =
    'h-10 w-full rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none transition-colors placeholder:text-muted-foreground/60 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-50';

function Field({
    label,
    name,
    error,
    required = false,
    className,
    children,
}: {
    label: string;
    name: string;
    error?: string;
    required?: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={`flex flex-col gap-1.5 ${className ?? ''}`}>
            <Label htmlFor={name} className="text-xs font-semibold tracking-wide text-foreground/85">
                {label} {required && <span className="text-destructive font-bold">*</span>}
            </Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function Section({
    title,
    description,
    badge,
    children,
}: {
    title: string;
    description?: string;
    badge?: string;
    children: ReactNode;
}) {
    return (
        <section className="rounded-2xl border border-border/80 bg-card p-6 shadow-xs transition-shadow">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-2 border-b border-border/60 pb-4">
                <div>
                    <div className="flex items-center gap-2.5">
                        <h3 className="text-base font-semibold tracking-tight text-foreground">{title}</h3>
                        {badge && (
                            <span className="inline-flex items-center rounded-md bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                {badge}
                            </span>
                        )}
                    </div>
                    {description && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
            </div>
            <div className="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                {children}
            </div>
        </section>
    );
}

export default function EmployeeProfileFormFields({
    errors,
    defaults = {},
    departments = [],
    divisions = [],
    designations = [],
    organisations = [],
    officeLocations = [],
    roles = [],
    countries = [],
    isEdit = false,
}: Props) {
    const profile = (defaults as EmployeeFormValues & { profile?: EmployeeFormValues })
        .profile;

    const profileDefaults = {
        ...defaults,
        ...(profile ?? {}),
    };

    const [joiningDate, setJoiningDate] = useState(defaults.joining_date ?? '');

    const matchedDesignationValue = (() => {
        if (!defaults.designation) {
            return '';
        }
        const match = designations.find(
            (d) =>
                d.title.toLowerCase() === defaults.designation?.toLowerCase() ||
                d.shortcode.toLowerCase() === defaults.designation?.toLowerCase() ||
                String(d.id) === String(defaults.designation_id),
        );
        return match ? match.title : defaults.designation;
    })();

    const matchedDivisionValue = (() => {
        if (!defaults.division) {
            return '';
        }
        const match = divisions.find(
            (d) =>
                d.value.toLowerCase() === defaults.division?.toLowerCase() ||
                d.code.toLowerCase() === defaults.division?.toLowerCase() ||
                d.name.toLowerCase() === defaults.division?.toLowerCase(),
        );
        return match ? match.value : defaults.division;
    })();

    const matchedVisaFrom = (() => {
        if (!profileDefaults.visa_from) {
            return '';
        }
        const normalized = profileDefaults.visa_from.toLowerCase().trim();
        if (normalized === 'yes machinery' || normalized === 'yes machinary') {
            return 'Yes Machinery';
        }
        if (normalized === 'yes automation') {
            return 'Yes Automation';
        }
        return profileDefaults.visa_from;
    })();

    return (
        <div className="space-y-6">
            <Section
                title="Account information"
                description="Credentials and role access for system authentication"
                badge="Required"
            >
                <Field label="Full name" name="name" error={errors.name} required>
                    <Input
                        id="name"
                        name="name"
                        placeholder="e.g. John Doe"
                        required
                        defaultValue={defaults.name ?? ''}
                    />
                </Field>
                <Field label="Work email" name="email" error={errors.email} required>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        placeholder="e.g. john@yesmachinery.ae"
                        required
                        defaultValue={defaults.email ?? ''}
                    />
                </Field>
                <Field
                    label={isEdit ? 'Password (leave blank to keep current)' : 'Password'}
                    name="password"
                    error={errors.password}
                    required={!isEdit}
                >
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        placeholder={isEdit ? '••••••••' : 'Enter secure password'}
                        required={!isEdit}
                        autoComplete="new-password"
                    />
                </Field>
                <Field
                    label="ACL / System role"
                    name="roles"
                    error={errors.roles}
                    required={!isEdit}
                >
                    <select
                        id="roles"
                        name="roles"
                        required={!isEdit}
                        defaultValue={defaults.roles ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select system role</option>
                        {roles.map((role) => (
                            <option key={role.name} value={role.name}>
                                {role.name}
                            </option>
                        ))}
                    </select>
                </Field>
            </Section>

            <Section
                title="Employment details"
                description="Designation, department, branch, and company placement"
            >
                <Field label="Employee number" name="emp_num" error={errors.emp_num} required>
                    <Input
                        id="emp_num"
                        name="emp_num"
                        placeholder="e.g. EMP-0012"
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
                        placeholder="Internal code / badge"
                        defaultValue={defaults.employee_code ?? ''}
                    />
                </Field>
                <Field
                    label="Designation"
                    name="designation"
                    error={errors.designation}
                    required
                >
                    <select
                        id="designation"
                        name="designation"
                        required
                        defaultValue={matchedDesignationValue}
                        className={fieldClass}
                    >
                        <option value="">Select designation</option>
                        {designations.map((designation) => (
                            <option key={designation.id} value={designation.title}>
                                {designation.title} ({designation.shortcode})
                            </option>
                        ))}
                        {defaults.designation &&
                            !designations.some(
                                (d) =>
                                    d.title.toLowerCase() ===
                                        defaults.designation?.toLowerCase() ||
                                    d.shortcode.toLowerCase() ===
                                        defaults.designation?.toLowerCase(),
                            ) && (
                                <option value={defaults.designation}>
                                    {defaults.designation}
                                </option>
                            )}
                    </select>
                </Field>
                <Field label="Department" name="division" error={errors.division} required>
                    <select
                        id="division"
                        name="division"
                        required
                        defaultValue={matchedDivisionValue}
                        className={fieldClass}
                    >
                        <option value="">Select department</option>
                        {divisions.map((division) => (
                            <option key={division.id} value={division.value}>
                                {division.name} ({division.code})
                            </option>
                        ))}
                        {defaults.division &&
                            !divisions.some(
                                (d) =>
                                    d.value === matchedDivisionValue ||
                                    d.code.toLowerCase() === defaults.division?.toLowerCase() ||
                                    d.name.toLowerCase() === defaults.division?.toLowerCase(),
                            ) && (
                                <option value={defaults.division}>
                                    {defaults.division}
                                </option>
                            )}
                    </select>
                </Field>
                <Field label="Work phone" name="phone" error={errors.phone}>
                    <Input
                        id="phone"
                        name="phone"
                        type="tel"
                        placeholder="e.g. +971 4 123 4567"
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
                        defaultValue={defaults.employment_status ?? 'fulltime'}
                        className={fieldClass}
                    >
                        <option value="">Select status</option>
                        <option value="fulltime">Full time</option>
                        <option value="parttime">Part time</option>
                        <option value="contract">Contract</option>
                        <option value="intern">Intern</option>
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
                        <option value="">Select organisation</option>
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
                        <option value="">Select location</option>
                        {officeLocations.map((location) => (
                            <option key={location.id} value={location.id}>
                                {location.name}
                            </option>
                        ))}
                    </select>
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
                <Field
                    label="Profile photo"
                    name="image_url"
                    error={errors.image_url}
                    className="sm:col-span-2 lg:col-span-1"
                >
                    <div className="flex items-center gap-3">
                        {defaults.image_url ? (
                            <img
                                src={defaults.image_url}
                                alt="Current photo"
                                className="h-10 w-10 shrink-0 rounded-full border border-border object-cover bg-muted/40 shadow-xs"
                            />
                        ) : null}
                        <Input
                            id="image_url"
                            name="image_url"
                            type="file"
                            accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                            className="h-10 cursor-pointer file:mr-2 file:h-7 file:rounded-md file:border-0 file:bg-muted file:px-2.5 file:text-xs file:font-medium"
                        />
                    </div>
                    <p className="text-[11px] text-muted-foreground">
                        JPG, PNG, GIF, or WebP. Max 5MB.
                    </p>
                </Field>
            </Section>

            <Section
                title="Personal information"
                description="Demographic, identification, and personal details"
            >
                <Field label="Gender" name="gender" error={errors.gender}>
                    <select
                        id="gender"
                        name="gender"
                        defaultValue={profileDefaults.gender ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select gender</option>
                        <option value="M">Male</option>
                        <option value="F">Female</option>
                    </select>
                </Field>
                <Field
                    label="Date of birth (Personal)"
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
                        <option value="">Select marital status</option>
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
                    <select
                        id="nationality"
                        name="nationality"
                        defaultValue={
                            countries.find(
                                (country) =>
                                    country.name.toLowerCase() ===
                                        profileDefaults.nationality?.toLowerCase() ||
                                    String(country.id) ===
                                        String(profileDefaults.nationality),
                            )?.name ?? (profileDefaults.nationality ?? '')
                        }
                        className={fieldClass}
                    >
                        <option value="">Select country</option>
                        {countries.map((country) => (
                            <option key={country.id} value={country.name}>
                                {country.name}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Religion" name="religion" error={errors.religion}>
                    <Input
                        id="religion"
                        name="religion"
                        placeholder="e.g. Islam, Christianity"
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
                        placeholder="e.g. O+, A+, B+"
                        defaultValue={profileDefaults.blood_group ?? ''}
                    />
                </Field>
            </Section>

            <Section
                title="Contact (UAE)"
                description="Residential address and emergency contacts in the UAE"
            >
                <Field
                    label="Personal email"
                    name="personal_email"
                    error={errors.personal_email}
                >
                    <Input
                        id="personal_email"
                        type="email"
                        name="personal_email"
                        placeholder="e.g. personal@gmail.com"
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
                        type="tel"
                        placeholder="e.g. +971 50 123 4567"
                        defaultValue={profileDefaults.personal_mobile ?? ''}
                    />
                </Field>
                <Field
                    label="Address (UAE)"
                    name="address_uae"
                    error={errors.address_uae}
                >
                    <Input
                        id="address_uae"
                        name="address_uae"
                        placeholder="Apartment, Street, City / Emirate"
                        defaultValue={profileDefaults.address_uae ?? ''}
                    />
                </Field>
                <Field
                    label="Emergency contact name"
                    name="emergency_contact_name"
                    error={errors.emergency_contact_name}
                >
                    <Input
                        id="emergency_contact_name"
                        name="emergency_contact_name"
                        placeholder="Contact person name"
                        defaultValue={
                            profileDefaults.emergency_contact_name ?? ''
                        }
                    />
                </Field>
                <Field
                    label="Emergency contact relation"
                    name="emergency_relation"
                    error={errors.emergency_relation}
                >
                    <Input
                        id="emergency_relation"
                        name="emergency_relation"
                        placeholder="e.g. Spouse, Brother, Friend"
                        defaultValue={profileDefaults.emergency_relation ?? ''}
                    />
                </Field>
                <Field
                    label="Emergency contact mobile"
                    name="emergency_mobile"
                    error={errors.emergency_mobile}
                >
                    <Input
                        id="emergency_mobile"
                        name="emergency_mobile"
                        type="tel"
                        placeholder="e.g. +971 55 987 6543"
                        defaultValue={profileDefaults.emergency_mobile ?? ''}
                    />
                </Field>
            </Section>

            <Section
                title="Home country contacts"
                description="Permanent address and emergency reach in home country"
            >
                <Field
                    label="Home country"
                    name="home_country"
                    error={errors.home_country}
                >
                    <select
                        id="home_country"
                        name="home_country"
                        defaultValue={profileDefaults.home_country ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select country</option>
                        {countries.map((country) => (
                            <option key={country.id} value={country.id}>
                                {country.name}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field
                    label="Home country mobile"
                    name="home_mobile"
                    error={errors.home_mobile}
                >
                    <Input
                        id="home_mobile"
                        name="home_mobile"
                        type="tel"
                        placeholder="Country code & number"
                        defaultValue={profileDefaults.home_mobile ?? ''}
                    />
                </Field>
                <Field
                    label="Home country address"
                    name="address_home"
                    error={errors.address_home}
                >
                    <Input
                        id="address_home"
                        name="address_home"
                        placeholder="Permanent home address"
                        defaultValue={profileDefaults.address_home ?? ''}
                    />
                </Field>
                <Field
                    label="Home emergency name"
                    name="home_emergency_name"
                    error={errors.home_emergency_name}
                >
                    <Input
                        id="home_emergency_name"
                        name="home_emergency_name"
                        placeholder="Emergency contact name"
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
                        placeholder="e.g. Father, Mother, Relative"
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
                        type="tel"
                        placeholder="Emergency phone number"
                        defaultValue={
                            profileDefaults.home_emergency_mobile ?? ''
                        }
                    />
                </Field>
            </Section>

            <Section
                title="Visa & education"
                description="Visa sponsorship, passport details, experience, and academic qualifications"
            >
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
                        <option value="">Select visa type</option>
                        <option value="visa">Visa</option>
                        <option value="workpermit">Work permit</option>
                    </select>
                </Field>
                <Field
                    label="Visa from"
                    name="visa_from"
                    error={errors.visa_from}
                >
                    <select
                        id="visa_from"
                        name="visa_from"
                        defaultValue={matchedVisaFrom}
                        className={fieldClass}
                    >
                        <option value="">Select visa company</option>
                        <option value="Yes Machinery">Yes Machinery</option>
                        <option value="Yes Automation">Yes Automation</option>
                        {profileDefaults.visa_from &&
                            !['Yes Machinery', 'Yes Automation'].includes(
                                matchedVisaFrom,
                            ) && (
                                <option value={profileDefaults.visa_from}>
                                    {profileDefaults.visa_from}
                                </option>
                            )}
                    </select>
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
                        max="80"
                        placeholder="e.g. 5.5"
                        name="total_experience"
                        defaultValue={profileDefaults.total_experience ?? ''}
                    />
                </Field>
                <Field
                    label="Highest education"
                    name="highest_education"
                    error={errors.highest_education}
                >
                    <select
                        id="highest_education"
                        name="highest_education"
                        defaultValue={profileDefaults.highest_education ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select highest education</option>
                        <option value="Less than High School">Less than High School</option>
                        <option value="High School/Diploma">High School/Diploma</option>
                        <option value="Technical/Vocational Certificate">Technical/Vocational Certificate</option>
                        <option value="Bachelors Degree">Bachelors Degree</option>
                        <option value="Masters Degree">Masters Degree</option>
                        <option value="Doctorate">Doctorate</option>
                        {profileDefaults.highest_education &&
                            ![
                                'Less than High School',
                                'High School/Diploma',
                                'Technical/Vocational Certificate',
                                'Bachelors Degree',
                                'Masters Degree',
                                'Doctorate',
                            ].includes(profileDefaults.highest_education) && (
                                <option value={profileDefaults.highest_education}>
                                    {profileDefaults.highest_education}
                                </option>
                            )}
                    </select>
                </Field>
            </Section>
        </div>
    );
}
