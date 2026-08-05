import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Errors = Record<string, string>;

type EmployeeFormValues = {
    employee_id?: number | string;
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

type UserOption = {
    id: number;
    name: string;
    email: string;
};

type Props = {
    errors: Errors;
    defaults?: EmployeeFormValues;
    users?: UserOption[];
    lockedEmployee?: { id: number; name: string; email: string } | null;
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
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4 rounded-2xl border border-border bg-white p-5 shadow-sm">
            <h3 className="text-sm font-semibold tracking-tight text-foreground">
                {title}
            </h3>
            <div className="grid gap-4 sm:grid-cols-2">{children}</div>
        </section>
    );
}

export default function EmployeeProfileFormFields({
    errors,
    defaults = {},
    users = [],
    lockedEmployee = null,
}: Props) {
    return (
        <div className="space-y-5">
            <Section title="Employee">
                {lockedEmployee ? (
                    <div className="sm:col-span-2">
                        <p className="text-sm font-medium text-foreground">
                            {lockedEmployee.name}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {lockedEmployee.email}
                        </p>
                    </div>
                ) : (
                    <Field
                        label="User"
                        name="employee_id"
                        error={errors.employee_id}
                    >
                        <select
                            id="employee_id"
                            name="employee_id"
                            required
                            defaultValue={defaults.employee_id ?? ''}
                            className={fieldClass}
                        >
                            <option value="" disabled>
                                Select a user
                            </option>
                            {users.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name} ({user.email})
                                </option>
                            ))}
                        </select>
                    </Field>
                )}
            </Section>

            <Section title="Personal">
                <Field label="Gender" name="gender" error={errors.gender}>
                    <select
                        id="gender"
                        name="gender"
                        defaultValue={defaults.gender ?? ''}
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
                        defaultValue={defaults.dob_personal ?? ''}
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
                        defaultValue={defaults.marital_status ?? ''}
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
                        defaultValue={defaults.nationality ?? ''}
                    />
                </Field>
                <Field label="Religion" name="religion" error={errors.religion}>
                    <Input
                        id="religion"
                        name="religion"
                        defaultValue={defaults.religion ?? ''}
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
                        defaultValue={defaults.blood_group ?? ''}
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
                        defaultValue={defaults.personal_email ?? ''}
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
                        defaultValue={defaults.personal_mobile ?? ''}
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
                            defaultValue={defaults.address_uae ?? ''}
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
                        defaultValue={defaults.emergency_contact_name ?? ''}
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
                        defaultValue={defaults.emergency_relation ?? ''}
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
                        defaultValue={defaults.emergency_mobile ?? ''}
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
                        defaultValue={defaults.home_country ?? ''}
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
                        defaultValue={defaults.home_mobile ?? ''}
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
                            defaultValue={defaults.address_home ?? ''}
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
                        defaultValue={defaults.home_emergency_name ?? ''}
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
                        defaultValue={defaults.home_emergency_relation ?? ''}
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
                        defaultValue={defaults.home_emergency_mobile ?? ''}
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
                        defaultValue={defaults.visa_type ?? ''}
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
                        defaultValue={defaults.visa_from ?? ''}
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
                        defaultValue={defaults.dob_passport ?? ''}
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
                        defaultValue={defaults.total_experience ?? ''}
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
                        defaultValue={defaults.highest_education ?? ''}
                    />
                </Field>
            </Section>
        </div>
    );
}
