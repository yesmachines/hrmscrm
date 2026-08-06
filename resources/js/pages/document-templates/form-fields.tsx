import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Errors = Record<string, string>;

type Option = {
    id: number;
    name: string;
    code?: string;
};

type DocumentTemplateFormValues = {
    document_type_id?: number | string | null;
    template_name?: string | null;
    template_code?: string | null;
    status?: number | string | null;
};

type Props = {
    errors: Errors;
    defaults?: DocumentTemplateFormValues;
    documentTypes?: Option[];
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

export default function DocumentTemplateFormFields({
    errors,
    defaults = {},
    documentTypes = [],
}: Props) {
    return (
        <section className="space-y-4 rounded-2xl border border-border bg-white p-5 shadow-sm">
            <div>
                <h3 className="text-sm font-semibold tracking-tight text-foreground">
                    Template details
                </h3>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Link a template to a document type
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Document type"
                    name="document_type_id"
                    error={errors.document_type_id}
                >
                    <select
                        id="document_type_id"
                        name="document_type_id"
                        required
                        defaultValue={defaults.document_type_id ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select</option>
                        {documentTypes.map((type) => (
                            <option key={type.id} value={type.id}>
                                {type.name}
                                {type.code ? ` (${type.code})` : ''}
                            </option>
                        ))}
                    </select>
                </Field>

                <Field
                    label="Template name"
                    name="template_name"
                    error={errors.template_name}
                >
                    <Input
                        id="template_name"
                        name="template_name"
                        required
                        defaultValue={defaults.template_name ?? ''}
                    />
                </Field>

                <Field
                    label="Template code"
                    name="template_code"
                    error={errors.template_code}
                >
                    <Input
                        id="template_code"
                        name="template_code"
                        required
                        defaultValue={defaults.template_code ?? ''}
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
            </div>
        </section>
    );
}
