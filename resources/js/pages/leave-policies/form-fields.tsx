import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Errors = Record<string, string>;

type Option = {
    id: number;
    name: string;
};

type LeavePolicyFormValues = {
    leave_type_id?: number | string | null;
    organisation_id?: number | string | null;
    full_pay_days?: number | string | null;
    half_pay_days?: number | string | null;
    no_pay_days?: number | string | null;
    requires_document_after_days?: number | string | null;
    requires_weekend_document?: number | string | null;
    allocation_days?: number | string | null;
    carry_forward?: number | string | null;
    encashment?: number | string | null;
    remarks?: string | null;
    requires_attachment?: number | string | null;
    probation_applicable?: number | string | null;
    minimum_service_months?: number | string | null;
};

type Props = {
    errors: Errors;
    defaults?: LeavePolicyFormValues;
    leaveTypes?: Option[];
    organisations?: Option[];
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

export default function LeavePolicyFormFields({
    errors,
    defaults = {},
    leaveTypes = [],
    organisations = [],
}: Props) {
    return (
        <section className="space-y-4 rounded-2xl border border-border bg-white p-5 shadow-sm">
            <div>
                <h3 className="text-sm font-semibold tracking-tight text-foreground">
                    Leave policy details
                </h3>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Configure allocation and rules for an organisation
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
                            </option>
                        ))}
                    </select>
                </Field>

                <Field
                    label="Allocation days"
                    name="allocation_days"
                    error={errors.allocation_days}
                >
                    <Input
                        id="allocation_days"
                        name="allocation_days"
                        type="number"
                        min={0}
                        defaultValue={defaults.allocation_days ?? ''}
                    />
                </Field>

                <Field
                    label="Full pay days"
                    name="full_pay_days"
                    error={errors.full_pay_days}
                >
                    <Input
                        id="full_pay_days"
                        name="full_pay_days"
                        type="number"
                        min={0}
                        defaultValue={defaults.full_pay_days ?? ''}
                    />
                </Field>

                <Field
                    label="Half pay days"
                    name="half_pay_days"
                    error={errors.half_pay_days}
                >
                    <Input
                        id="half_pay_days"
                        name="half_pay_days"
                        type="number"
                        min={0}
                        defaultValue={defaults.half_pay_days ?? ''}
                    />
                </Field>

                <Field
                    label="No pay days"
                    name="no_pay_days"
                    error={errors.no_pay_days}
                >
                    <Input
                        id="no_pay_days"
                        name="no_pay_days"
                        type="number"
                        min={0}
                        defaultValue={defaults.no_pay_days ?? ''}
                    />
                </Field>

                <Field
                    label="Requires document after days"
                    name="requires_document_after_days"
                    error={errors.requires_document_after_days}
                >
                    <Input
                        id="requires_document_after_days"
                        name="requires_document_after_days"
                        type="number"
                        min={0}
                        defaultValue={
                            defaults.requires_document_after_days ?? ''
                        }
                    />
                </Field>

                <Field
                    label="Minimum service months"
                    name="minimum_service_months"
                    error={errors.minimum_service_months}
                >
                    <Input
                        id="minimum_service_months"
                        name="minimum_service_months"
                        type="number"
                        min={0}
                        defaultValue={defaults.minimum_service_months ?? ''}
                    />
                </Field>

                <Field
                    label="Requires weekend document"
                    name="requires_weekend_document"
                    error={errors.requires_weekend_document}
                >
                    <YesNoSelect
                        name="requires_weekend_document"
                        defaultValue={defaults.requires_weekend_document}
                    />
                </Field>

                <Field
                    label="Carry forward"
                    name="carry_forward"
                    error={errors.carry_forward}
                >
                    <YesNoSelect
                        name="carry_forward"
                        defaultValue={defaults.carry_forward}
                    />
                </Field>

                <Field
                    label="Encashment"
                    name="encashment"
                    error={errors.encashment}
                >
                    <YesNoSelect
                        name="encashment"
                        defaultValue={defaults.encashment}
                    />
                </Field>

                <Field
                    label="Requires attachment"
                    name="requires_attachment"
                    error={errors.requires_attachment}
                >
                    <YesNoSelect
                        name="requires_attachment"
                        defaultValue={defaults.requires_attachment}
                    />
                </Field>

                <Field
                    label="Probation applicable"
                    name="probation_applicable"
                    error={errors.probation_applicable}
                >
                    <YesNoSelect
                        name="probation_applicable"
                        defaultValue={defaults.probation_applicable}
                    />
                </Field>

                <div className="sm:col-span-2">
                    <Field
                        label="Remarks"
                        name="remarks"
                        error={errors.remarks}
                    >
                        <textarea
                            id="remarks"
                            name="remarks"
                            rows={3}
                            defaultValue={defaults.remarks ?? ''}
                            className="min-h-20 w-full rounded-lg border border-input bg-white px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                        />
                    </Field>
                </div>
            </div>
        </section>
    );
}
