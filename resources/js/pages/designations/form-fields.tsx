import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Errors = Record<string, string>;

type DepartmentOption = {
    id: number;
    name: string;
    code: string;
};

type DesignationFormValues = {
    department_id?: number | string | null;
    title?: string | null;
    shortcode?: string | null;
    status?: number | string | null;
};

type Props = {
    errors: Errors;
    defaults?: DesignationFormValues;
    departments?: DepartmentOption[];
};

const fieldClass =
    'h-10 w-full rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none transition-colors placeholder:text-muted-foreground/60 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-50';

function Field({
    label,
    name,
    error,
    required = false,
    children,
}: {
    label: string;
    name: string;
    error?: string;
    required?: boolean;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor={name} className="text-xs font-semibold tracking-wide text-foreground/85">
                {label} {required && <span className="font-bold text-destructive">*</span>}
            </Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

export default function DesignationFormFields({
    errors,
    defaults = {},
    departments = [],
}: Props) {
    return (
        <section className="space-y-6 rounded-2xl border border-border/80 bg-card p-6 shadow-xs">
            <div className="border-b border-border/60 pb-4">
                <h3 className="text-base font-semibold tracking-tight text-foreground">
                    Designation Details
                </h3>
                <p className="mt-1 text-xs text-muted-foreground">
                    Define the position title, short code, and associated department
                </p>
            </div>

            <div className="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                <Field
                    label="Department"
                    name="department_id"
                    error={errors.department_id}
                    required
                >
                    <select
                        id="department_id"
                        name="department_id"
                        required
                        defaultValue={defaults.department_id ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select department</option>
                        {departments.map((dept) => (
                            <option key={dept.id} value={dept.id}>
                                {dept.name} ({dept.code})
                            </option>
                        ))}
                    </select>
                </Field>

                <Field
                    label="Designation title"
                    name="title"
                    error={errors.title}
                    required
                >
                    <Input
                        id="title"
                        name="title"
                        placeholder="e.g. Senior Sales Engineer"
                        required
                        defaultValue={defaults.title ?? ''}
                    />
                </Field>

                <Field
                    label="Shortcode"
                    name="shortcode"
                    error={errors.shortcode}
                    required
                >
                    <Input
                        id="shortcode"
                        name="shortcode"
                        placeholder="e.g. SSE"
                        required
                        defaultValue={defaults.shortcode ?? ''}
                    />
                </Field>

                <Field
                    label="Status"
                    name="status"
                    error={errors.status}
                >
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
            </div>
        </section>
    );
}
