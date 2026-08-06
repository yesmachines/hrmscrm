import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Errors = Record<string, string>;

type OrganisationFormValues = {
    org_name?: string | null;
    short_name?: string | null;
    logo?: string | null;
    status?: number | string | null;
};

type Props = {
    errors: Errors;
    defaults?: OrganisationFormValues;
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

export default function OrganisationFormFields({
    errors,
    defaults = {},
}: Props) {
    return (
        <section className="space-y-4 rounded-2xl border border-border bg-white p-5 shadow-sm">
            <div>
                <h3 className="text-sm font-semibold tracking-tight text-foreground">
                    Organisation details
                </h3>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Basic company information used across HRMS
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Organisation name"
                    name="org_name"
                    error={errors.org_name}
                >
                    <Input
                        id="org_name"
                        name="org_name"
                        required
                        defaultValue={defaults.org_name ?? ''}
                    />
                </Field>

                <Field
                    label="Short name"
                    name="short_name"
                    error={errors.short_name}
                >
                    <Input
                        id="short_name"
                        name="short_name"
                        required
                        defaultValue={defaults.short_name ?? ''}
                    />
                </Field>

                <div className="sm:col-span-2">
                    <Field label="Logo" name="logo" error={errors.logo}>
                        {defaults.logo ? (
                            <div className="mb-3 flex items-center gap-3">
                                <img
                                    src={defaults.logo}
                                    alt="Current logo"
                                    className="h-14 w-14 rounded-lg border border-border object-contain bg-muted/30"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Upload a new file to replace the current logo
                                </p>
                            </div>
                        ) : null}
                        <Input
                            id="logo"
                            name="logo"
                            type="file"
                            accept="image/jpeg,image/png,image/gif,image/svg+xml,image/webp"
                            className="h-auto cursor-pointer py-2 file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:font-medium"
                        />
                        <p className="text-xs text-muted-foreground">
                            JPG, PNG, GIF, SVG or WebP. Max 2MB.
                        </p>
                    </Field>
                </div>

                <Field label="Status" name="status" error={errors.status}>
                    <select
                        id="status"
                        name="status"
                        defaultValue={
                            defaults.status === null ||
                            defaults.status === undefined
                                ? '1'
                                : String(defaults.status)
                        }
                        className={fieldClass}
                    >
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </Field>
            </div>
        </section>
    );
}
