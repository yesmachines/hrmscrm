import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Option = {
    id: number;
    name: string;
    code?: string;
    short_name?: string | null;
};

type TemplateDefaults = {
    leave_type_id: number;
    organisation_id: number | null;
    template_name: string;
    template_code: string;
    status: number;
};

type Props = {
    errors: Record<string, string>;
    leaveTypes: Option[];
    organisations: Option[];
    defaults?: Partial<TemplateDefaults>;
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

const placeholders = [
    '{{company_logo_url}}',
    '{{company_name}}',
    '{{employee_name}}',
    '{{employee_code}}',
    '{{employee_phone}}',
    '{{designation}}',
    '{{department}}',
    '{{leave_type}}',
    '{{start_date}}',
    '{{end_date}}',
    '{{total_days}}',
    '{{request_type}}',
    '{{local_contact_no}}',
    '{{traveling_outside_country}}',
    '{{travel_yes_checkbox}}',
    '{{travel_no_checkbox}}',
    '{{destination}}',
    '{{travel_contact_no}}',
    '{{ticket_status}}',
    '{{ticket_checkbox}}',
    '{{no_ticket_checkbox}}',
    '{{handover_person}}',
    '{{handover_other_name}}',
    '{{handover_other_organisation}}',
    '{{handover_other_designation}}',
    '{{handover_other_contact_number}}',
    '{{handover_description}}',
    '{{remarks}}',
    '{{employee_signature}}',
    '{{signature}}',
    '{{handover_giver_signature}}',
    '{{handover_receiver_signature}}',
    '{{approved_by}}',
    '{{approved_date}}',
    '{{issue_date}}',
];

export default function LeaveTemplateFormFields({
    errors,
    leaveTypes,
    organisations,
    defaults = {},
}: Props) {
    return (
        <section className="space-y-4 rounded-2xl border border-border bg-white p-5 shadow-sm">
            <div>
                <h3 className="text-sm font-semibold tracking-tight text-foreground">
                    Template details
                </h3>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Link a template to a leave type and optional organisation
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Leave type"
                    name="leave_type_id"
                    error={errors.leave_type_id}
                >
                    <select
                        id="leave_type_id"
                        name="leave_type_id"
                        required
                        defaultValue={defaults.leave_type_id ?? ''}
                        className={fieldClass}
                    >
                        <option value="">Select</option>
                        {leaveTypes.map((leaveType) => (
                            <option key={leaveType.id} value={leaveType.id}>
                                {leaveType.name}
                                {leaveType.code ? ` (${leaveType.code})` : ''}
                            </option>
                        ))}
                    </select>
                </Field>

                <Field
                    label="Organisation"
                    name="organisation_id"
                    error={errors.organisation_id}
                >
                    <select
                        id="organisation_id"
                        name="organisation_id"
                        defaultValue={defaults.organisation_id ?? ''}
                        className={fieldClass}
                    >
                        <option value="">All Organisations (General)</option>
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

                <Field label="Status" name="status" error={errors.status}>
                    <select
                        id="status"
                        name="status"
                        required
                        defaultValue={String(defaults.status ?? 1)}
                        className={fieldClass}
                    >
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </Field>

                <div className="sm:col-span-2">
                    <Field
                        label="Template HTML Code"
                        name="template_code"
                        error={errors.template_code}
                    >
                        <div className="space-y-2">
                            <div className="flex flex-wrap gap-1.5 text-xs text-muted-foreground">
                                <span className="font-medium text-foreground">
                                    Available Placeholders:
                                </span>
                                {placeholders.map((placeholder) => (
                                    <code
                                        key={placeholder}
                                        className="cursor-pointer rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-foreground hover:bg-muted/80"
                                        title="Click to copy placeholder"
                                        onClick={() =>
                                            navigator.clipboard?.writeText(
                                                placeholder,
                                            )
                                        }
                                    >
                                        {placeholder}
                                    </code>
                                ))}
                            </div>
                            <textarea
                                id="template_code"
                                name="template_code"
                                required
                                rows={14}
                                defaultValue={defaults.template_code ?? ''}
                                placeholder="<div style='font-family: DejaVu Sans...'>&#10;  <h2>{{company_name}}</h2>&#10;  <p>{{employee_name}}</p>&#10;</div>"
                                className="w-full rounded-xl border border-input bg-slate-950 p-4 font-mono text-xs text-slate-100 shadow-inner outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                            />
                            <p className="text-xs text-muted-foreground">
                                Handover descriptions render as a task list.
                                Signature placeholders remain blank when no
                                signature image is available.
                            </p>
                        </div>
                    </Field>
                </div>
            </div>
        </section>
    );
}
