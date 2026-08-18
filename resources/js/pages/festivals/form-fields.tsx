import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Errors = Record<string, string>;

type FestivalFormValues = {
    name?: string | null;
    is_active?: number | string | null;
    type?: string | null;
    shortcode?: string | null;
    start_date?: string | null;
    end_date?: string | null;
    countries?: number[];
};

type Props = {
    errors: Errors;
    defaults?: FestivalFormValues;
    countriesList?: { id: number; name: string }[];
    onCountriesChange?: (countries: number[]) => void;
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

export default function FestivalFormFields({
    errors,
    defaults = {},
    countriesList = [],
    onCountriesChange,
}: Props) {
    return (
        <section className="space-y-4 rounded-2xl border border-border bg-white p-5 shadow-sm">
            <div>
                <h3 className="text-sm font-semibold tracking-tight text-foreground">
                    Festival details
                </h3>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Manage festival information
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Name"
                    name="name"
                    error={errors.name}
                >
                    <Input
                        id="name"
                        name="name"
                        required
                        defaultValue={defaults.name ?? ''}
                    />
                </Field>

                <Field label="Type" name="type" error={errors.type}>
                    <select
                        id="type"
                        name="type"
                        defaultValue={defaults.type ?? 'festival'}
                        className={fieldClass}
                    >
                        <option value="festival">Festival</option>
                        <option value="holiday">Holiday</option>
                    </select>
                </Field>

                {defaults.shortcode && (
                    <Field label="Shortcode" name="shortcode">
                        <Input
                            id="shortcode"
                            name="shortcode"
                            defaultValue={defaults.shortcode}
                            disabled
                        />
                    </Field>
                )}

                <Field label="Start Date" name="start_date" error={errors.start_date}>
                    <Input
                        id="start_date"
                        name="start_date"
                        type="date"
                        defaultValue={defaults.start_date ?? ''}
                    />
                </Field>

                <Field label="End Date" name="end_date" error={errors.end_date}>
                    <Input
                        id="end_date"
                        name="end_date"
                        type="date"
                        defaultValue={defaults.end_date ?? ''}
                    />
                </Field>

                <Field label="Status" name="is_active" error={errors.is_active}>

                    <select
                        id="is_active"
                        name="is_active"
                        defaultValue={
                            defaults.is_active === null ||
                            defaults.is_active === undefined
                                ? '1'
                                : String(defaults.is_active)
                        }
                        className={fieldClass}
                    >
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </Field>
            </div>

            {countriesList && countriesList.length > 0 && (
                <div className="mt-4 pt-4 border-t border-border">
                    <h4 className="text-sm font-semibold tracking-tight text-foreground mb-3">
                        Applicable Countries
                    </h4>
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 max-h-64 overflow-y-auto p-2 border border-input rounded-md bg-white">
                        {countriesList.map((country) => (
                            <label key={country.id} className="flex items-center gap-2 cursor-pointer text-sm hover:bg-muted/50 p-1.5 rounded-sm transition-colors">
                                <input
                                    type="checkbox"
                                    name="countries"
                                    value={country.id}
                                    checked={defaults.countries?.includes(country.id) || false}
                                    onChange={(e) => {
                                        if (onCountriesChange) {
                                            const current = defaults.countries || [];
                                            if (e.target.checked) {
                                                onCountriesChange([...current, country.id]);
                                            } else {
                                                onCountriesChange(current.filter(id => id !== country.id));
                                            }
                                        }
                                    }}
                                    className="h-4 w-4 rounded-sm border-primary text-primary focus:ring-primary/30"
                                />
                                <span>{country.name}</span>
                            </label>
                        ))}
                    </div>
                    <InputError message={errors.countries} className="mt-2" />
                </div>
            )}
        </section>
    );
}
