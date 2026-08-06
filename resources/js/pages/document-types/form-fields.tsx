import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Errors = Record<string, string>;

type Option = {
    id: number;
    name: string;
};

type DocumentTypeFormValues = {
    category_id?: number | string | null;
    document_name?: string | null;
    document_code?: string | null;
    requires_number?: number | string | null;
    requires_expiry?: number | string | null;
    editable_before_approval?: number | string | null;
    requires_hr_approval?: number | string | null;
    requires_reminder?: number | string | null;
    requires_attachments?: number | string | null;
    record_source?: string | null;
};

type Props = {
    errors: Errors;
    defaults?: DocumentTypeFormValues;
    categories?: Option[];
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

function yesNoDefault(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    return String(value);
}

function YesNoSelect({
    name,
    defaultValue,
}: {
    name: string;
    defaultValue: number | string | null | undefined;
}) {
    return (
        <select
            id={name}
            name={name}
            defaultValue={yesNoDefault(defaultValue)}
            className={fieldClass}
        >
            <option value="1">Yes</option>
            <option value="0">No</option>
        </select>
    );
}

export default function DocumentTypeFormFields({
    errors,
    defaults = {},
    categories = [],
}: Props) {
    return (
        <section className="space-y-4 rounded-2xl border border-border bg-white p-5 shadow-sm">
            <div>
                <h3 className="text-sm font-semibold tracking-tight text-foreground">
                    Document type details
                </h3>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Configure how this document type behaves
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Category"
                    name="category_id"
                    error={errors.category_id}
                >
                    <select
                        id="category_id"
                        name="category_id"
                        required
                        defaultValue={defaults.category_id ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                </Field>

                <Field
                    label="Document name"
                    name="document_name"
                    error={errors.document_name}
                >
                    <Input
                        id="document_name"
                        name="document_name"
                        required
                        defaultValue={defaults.document_name ?? ''}
                    />
                </Field>

                <Field
                    label="Document code"
                    name="document_code"
                    error={errors.document_code}
                >
                    <Input
                        id="document_code"
                        name="document_code"
                        required
                        defaultValue={defaults.document_code ?? ''}
                    />
                </Field>

                <Field
                    label="Record source"
                    name="record_source"
                    error={errors.record_source}
                >
                    <select
                        id="record_source"
                        name="record_source"
                        defaultValue={defaults.record_source ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select</option>
                        <option value="uploaded">Uploaded</option>
                        <option value="generated">Generated</option>
                    </select>
                </Field>

                <Field
                    label="Requires number"
                    name="requires_number"
                    error={errors.requires_number}
                >
                    <YesNoSelect
                        name="requires_number"
                        defaultValue={defaults.requires_number}
                    />
                </Field>

                <Field
                    label="Requires expiry"
                    name="requires_expiry"
                    error={errors.requires_expiry}
                >
                    <YesNoSelect
                        name="requires_expiry"
                        defaultValue={defaults.requires_expiry}
                    />
                </Field>

                <Field
                    label="Editable before approval"
                    name="editable_before_approval"
                    error={errors.editable_before_approval}
                >
                    <YesNoSelect
                        name="editable_before_approval"
                        defaultValue={defaults.editable_before_approval}
                    />
                </Field>

                <Field
                    label="Requires HR approval"
                    name="requires_hr_approval"
                    error={errors.requires_hr_approval}
                >
                    <YesNoSelect
                        name="requires_hr_approval"
                        defaultValue={defaults.requires_hr_approval}
                    />
                </Field>

                <Field
                    label="Requires reminder"
                    name="requires_reminder"
                    error={errors.requires_reminder}
                >
                    <YesNoSelect
                        name="requires_reminder"
                        defaultValue={defaults.requires_reminder}
                    />
                </Field>

                <Field
                    label="Requires attachments"
                    name="requires_attachments"
                    error={errors.requires_attachments}
                >
                    <YesNoSelect
                        name="requires_attachments"
                        defaultValue={defaults.requires_attachments}
                    />
                </Field>
            </div>
        </section>
    );
}
