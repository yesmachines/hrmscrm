import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Errors = Record<string, string>;

type Option = {
    id: number;
    name: string;
};

type DocumentCategoryFormValues = {
    category_name?: string | null;
    short_code?: string | null;
    status?: number | string | null;
    parent_id?: number | string | null;
};

type Props = {
    errors: Errors;
    defaults?: DocumentCategoryFormValues;
    parents?: Option[];
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

export default function DocumentCategoryFormFields({
    errors,
    defaults = {},
    parents = [],
}: Props) {
    return (
        <section className="space-y-4 rounded-2xl border border-border bg-white p-5 shadow-sm">
            <div>
                <h3 className="text-sm font-semibold tracking-tight text-foreground">
                    Category details
                </h3>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Organise documents into categories
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Category name"
                    name="category_name"
                    error={errors.category_name}
                >
                    <Input
                        id="category_name"
                        name="category_name"
                        required
                        defaultValue={defaults.category_name ?? ''}
                    />
                </Field>

                <Field
                    label="Short code"
                    name="short_code"
                    error={errors.short_code}
                >
                    <Input
                        id="short_code"
                        name="short_code"
                        required
                        defaultValue={defaults.short_code ?? ''}
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

                <Field label="Parent" name="parent_id" error={errors.parent_id}>
                    <select
                        id="parent_id"
                        name="parent_id"
                        defaultValue={defaults.parent_id ?? ''}
                        className={fieldClass}
                    >
                        <option value="">None</option>
                        {parents.map((parent) => (
                            <option key={parent.id} value={parent.id}>
                                {parent.name}
                            </option>
                        ))}
                    </select>
                </Field>
            </div>
        </section>
    );
}
