import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Errors = Record<string, string>;

export type DepartmentFormValues = {
    name: string;
    code: string;
    status: number | string;
};

type Props = {
    data: DepartmentFormValues;
    setData: (key: keyof DepartmentFormValues, value: any) => void;
    errors: Errors;
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

export default function DepartmentFormFields({
    data,
    setData,
    errors,
}: Props) {
    return (
        <section className="space-y-6 rounded-2xl border border-border/80 bg-card p-6 shadow-xs">
            <div>
                <h2 className="text-base font-semibold tracking-tight text-foreground">
                    Department Details
                </h2>
                <p className="text-xs text-muted-foreground">
                    Add or update company department information
                </p>
            </div>

            <div className="grid gap-5 md:grid-cols-2">
                <Field
                    label="Department Name"
                    name="name"
                    error={errors.name}
                    required
                >
                    <Input
                        id="name"
                        name="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="e.g. Human Resources, Operations, Finance"
                        required
                    />
                </Field>

                <Field
                    label="Department Code"
                    name="code"
                    error={errors.code}
                >
                    <Input
                        id="code"
                        name="code"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        placeholder="e.g. HR, OPS, FIN, IT"
                    />
                </Field>
            </div>

            <div className="grid gap-5 md:grid-cols-2">
                <Field
                    label="Status"
                    name="status"
                    error={errors.status}
                    required
                >
                    <select
                        id="status"
                        name="status"
                        value={String(data.status)}
                        onChange={(e) => setData('status', Number(e.target.value))}
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
