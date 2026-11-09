import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Errors = Record<string, string>;

type LeaveTypeFormValues = {
    leave_name?: string | null;
    code?: string | null;
    is_paid?: number | string | null;
    requires_approval?: number | string | null;
    gender?: string | null;
    religion?: string | null;
    status?: number | string | null;
};

type Props = {
    errors: Errors;
    defaults?: LeaveTypeFormValues;
    genderOptions: { value: string; label: string }[];
    religionOptions: { value: string; label: string }[];
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

function yesNoDefault(
    value: number | string | null | undefined,
    fallback = '0',
): string {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }

    return String(value);
}

function YesNoSelect({
    name,
    defaultValue,
    fallback = '0',
}: {
    name: string;
    defaultValue: number | string | null | undefined;
    fallback?: string;
}) {
    return (
        <select
            id={name}
            name={name}
            defaultValue={yesNoDefault(defaultValue, fallback)}
            className={fieldClass}
        >
            <option value="1">Yes</option>
            <option value="0">No</option>
        </select>
    );
}

export default function LeaveTypeFormFields({
    errors,
    defaults = {},
    genderOptions,
    religionOptions,
}: Props) {
    return (
        <section className="space-y-4 rounded-2xl border border-border bg-white p-5 shadow-sm">
            <div>
                <h3 className="text-sm font-semibold tracking-tight text-foreground">
                    Leave type details
                </h3>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Define how this leave type behaves
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Leave name"
                    name="leave_name"
                    error={errors.leave_name}
                >
                    <Input
                        id="leave_name"
                        name="leave_name"
                        required
                        defaultValue={defaults.leave_name ?? ''}
                    />
                </Field>

                <Field label="Code" name="code" error={errors.code}>
                    <Input
                        id="code"
                        name="code"
                        required
                        defaultValue={defaults.code ?? ''}
                    />
                </Field>

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

                <Field label="Gender" name="gender" error={errors.gender}>
                    <select
                        id="gender"
                        name="gender"
                        defaultValue={defaults.gender ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Any</option>
                        {genderOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </Field>

                <Field label="Religion" name="religion" error={errors.religion}>
                    <select
                        id="religion"
                        name="religion"
                        defaultValue={defaults.religion ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Any</option>
                        {religionOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </Field>

                <Field label="Is paid" name="is_paid" error={errors.is_paid}>
                    <YesNoSelect
                        name="is_paid"
                        defaultValue={defaults.is_paid}
                        fallback="1"
                    />
                </Field>

                <Field
                    label="Requires approval"
                    name="requires_approval"
                    error={errors.requires_approval}
                >
                    <YesNoSelect
                        name="requires_approval"
                        defaultValue={defaults.requires_approval}
                        fallback="1"
                    />
                </Field>

            </div>
        </section>
    );
}
