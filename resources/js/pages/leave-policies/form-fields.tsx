import { useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Errors = Record<string, string>;

type Option = {
    id: number;
    name: string;
    disabled?: boolean;
};

type PolicyTierValue = {
    id?: number;
    from_day: number | string;
    to_day: number | string;
    pay_percentage: number | string;
};

type TierFormValue = {
    from_day: string;
    to_day: string;
    pay_percentage: string;
};

type LeavePolicyFormValues = {
    leave_type_id?: number | string | null;
    organisation_id?: number | string | null;
    annual_limit?: number | string | null;
    allow_once?: number | string | null;
    allow_balance?: number | string | null;
    requires_document_after_days?: number | string | null;
    requires_weekend_document?: number | string | null;
    requires_handover?: number | string | null;
    carry_forward_weeks?: number | string | null;
    encashment?: number | string | null;
    remarks?: string | null;
    requires_attachment?: number | string | null;
    probation_applicable?: number | string | null;
    minimum_service_months?: number | string | null;
    exclude_rules?: unknown[] | Record<string, unknown> | null;
    tiers?: PolicyTierValue[] | null;
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
    const [tiers, setTiers] = useState<TierFormValue[]>(() =>
        (defaults.tiers ?? []).map((tier) => ({
            from_day: String(tier.from_day),
            to_day: String(tier.to_day),
            pay_percentage: String(tier.pay_percentage),
        })),
    );

    function updateTier(
        index: number,
        field: keyof TierFormValue,
        value: string,
    ) {
        setTiers((currentTiers) =>
            currentTiers.map((tier, tierIndex) =>
                tierIndex === index ? { ...tier, [field]: value } : tier,
            ),
        );
    }

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
                            <option
                                key={leaveType.id}
                                value={leaveType.id}
                                disabled={leaveType.disabled}
                            >
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
                    label="Annual limit"
                    name="annual_limit"
                    error={errors.annual_limit}
                >
                    <Input
                        id="annual_limit"
                        name="annual_limit"
                        type="number"
                        min={0}
                        step={1}
                        defaultValue={defaults.annual_limit ?? ''}
                    />
                </Field>

                <Field
                    label="Allow once"
                    name="allow_once"
                    error={errors.allow_once}
                >
                    <YesNoSelect
                        name="allow_once"
                        defaultValue={defaults.allow_once}
                    />
                </Field>

                <Field
                    label="Allow balance"
                    name="allow_balance"
                    error={errors.allow_balance}
                >
                    <YesNoSelect
                        name="allow_balance"
                        defaultValue={defaults.allow_balance ?? 1}
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
                    label="Requires handover"
                    name="requires_handover"
                    error={errors.requires_handover}
                >
                    <YesNoSelect
                        name="requires_handover"
                        defaultValue={defaults.requires_handover}
                    />
                </Field>

                <Field
                    label="Carry forward weeks (optional)"
                    name="carry_forward_weeks"
                    error={errors.carry_forward_weeks}
                >
                    <Input
                        id="carry_forward_weeks"
                        name="carry_forward_weeks"
                        type="number"
                        min={1}
                        step={1}
                        defaultValue={defaults.carry_forward_weeks ?? ''}
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
                        label="Exclude rules (JSON object or array)"
                        name="exclude_rules"
                        error={errors.exclude_rules}
                    >
                        <textarea
                            id="exclude_rules"
                            name="exclude_rules"
                            rows={4}
                            defaultValue={
                                defaults.exclude_rules
                                    ? JSON.stringify(
                                          defaults.exclude_rules,
                                          null,
                                          2,
                                      )
                                    : ''
                            }
                            placeholder="{}"
                            aria-describedby="exclude_rules_help"
                            className="min-h-24 w-full rounded-lg border border-input bg-white px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                        />
                        <p
                            id="exclude_rules_help"
                            className="text-xs text-muted-foreground"
                        >
                            Leave empty if there are no exclusion rules.
                        </p>
                    </Field>
                </div>

                <div className="space-y-4 rounded-xl border border-border p-4 sm:col-span-2">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h4 className="text-sm font-semibold text-foreground">
                                Pay tiers (optional)
                            </h4>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Add day ranges and pay percentages for this
                                policy, or leave empty.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                setTiers((currentTiers) => [
                                    ...currentTiers,
                                    {
                                        from_day: '',
                                        to_day: '',
                                        pay_percentage: '',
                                    },
                                ])
                            }
                        >
                            <Plus className="size-4" />
                            Add tier
                        </Button>
                    </div>

                    <input
                        type="hidden"
                        name="tiers"
                        value={JSON.stringify(tiers)}
                        readOnly
                    />
                    <InputError message={errors.tiers} />

                    {tiers.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No pay tiers configured.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {tiers.map((tier, index) => (
                                <div
                                    key={index}
                                    className="grid gap-3 rounded-lg border border-border p-3 sm:grid-cols-[1fr_1fr_1fr_auto]"
                                >
                                    <Field
                                        label="From day"
                                        name={`tiers.${index}.from_day`}
                                        error={
                                            errors[
                                                `tiers.${index}.from_day`
                                            ]
                                        }
                                    >
                                        <Input
                                            id={`tiers.${index}.from_day`}
                                            type="number"
                                            min={1}
                                            step={1}
                                            value={tier.from_day}
                                            onChange={(event) =>
                                                updateTier(
                                                    index,
                                                    'from_day',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="To day"
                                        name={`tiers.${index}.to_day`}
                                        error={
                                            errors[`tiers.${index}.to_day`]
                                        }
                                    >
                                        <Input
                                            id={`tiers.${index}.to_day`}
                                            type="number"
                                            min={1}
                                            step={1}
                                            value={tier.to_day}
                                            onChange={(event) =>
                                                updateTier(
                                                    index,
                                                    'to_day',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Pay percentage"
                                        name={`tiers.${index}.pay_percentage`}
                                        error={
                                            errors[
                                                `tiers.${index}.pay_percentage`
                                            ]
                                        }
                                    >
                                        <Input
                                            id={`tiers.${index}.pay_percentage`}
                                            type="number"
                                            min={0}
                                            max={100}
                                            step={0.01}
                                            value={tier.pay_percentage}
                                            onChange={(event) =>
                                                updateTier(
                                                    index,
                                                    'pay_percentage',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <div className="flex items-end">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            aria-label={`Remove tier ${index + 1}`}
                                            onClick={() =>
                                                setTiers((currentTiers) =>
                                                    currentTiers.filter(
                                                        (_, tierIndex) =>
                                                            tierIndex !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                            Remove
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

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
