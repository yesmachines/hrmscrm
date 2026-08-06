import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Errors = Record<string, string>;

type Option = {
    id: number;
    name: string;
    short_name?: string;
    code?: string;
};

type OfficeLocationFormValues = {
    organisation_id?: number | string | null;
    office_name?: string | null;
    country_id?: number | string | null;
    city?: string | null;
    address?: string | null;
};

type Props = {
    errors: Errors;
    defaults?: OfficeLocationFormValues;
    organisations?: Option[];
    countries?: Option[];
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

export default function OfficeLocationFormFields({
    errors,
    defaults = {},
    organisations = [],
    countries = [],
}: Props) {
    return (
        <section className="space-y-4 rounded-2xl border border-border bg-white p-5 shadow-sm">
            <div>
                <h3 className="text-sm font-semibold tracking-tight text-foreground">
                    Office location details
                </h3>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Link an office to an organisation and country
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Organisation"
                    name="organisation_id"
                    error={errors.organisation_id}
                >
                    <select
                        id="organisation_id"
                        name="organisation_id"
                        required
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
                    label="Office name"
                    name="office_name"
                    error={errors.office_name}
                >
                    <Input
                        id="office_name"
                        name="office_name"
                        required
                        defaultValue={defaults.office_name ?? ''}
                    />
                </Field>

                <Field
                    label="Country"
                    name="country_id"
                    error={errors.country_id}
                >
                    <select
                        id="country_id"
                        name="country_id"
                        required
                        defaultValue={defaults.country_id ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select</option>
                        {countries.map((country) => (
                            <option key={country.id} value={country.id}>
                                {country.name}
                                {country.code ? ` (${country.code})` : ''}
                            </option>
                        ))}
                    </select>
                </Field>

                <Field label="City" name="city" error={errors.city}>
                    <Input
                        id="city"
                        name="city"
                        required
                        defaultValue={defaults.city ?? ''}
                    />
                </Field>

                <div className="sm:col-span-2">
                    <Field label="Address" name="address" error={errors.address}>
                        <Input
                            id="address"
                            name="address"
                            required
                            defaultValue={defaults.address ?? ''}
                        />
                    </Field>
                </div>
            </div>
        </section>
    );
}
