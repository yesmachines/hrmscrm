import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

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
    '{{destination}}',
    '{{travel_contact_no}}',
    '{{ticket_status}}',
    '{{handover_person}}',
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
        <div className="space-y-6">
            <div className="grid gap-5 rounded-2xl border border-border bg-white p-6 sm:grid-cols-2">
                <div className="space-y-1.5">
                    <Label htmlFor="template_name">Template name</Label>
                    <Input
                        id="template_name"
                        name="template_name"
                        defaultValue={defaults.template_name ?? ''}
                        required
                    />
                    <InputError message={errors.template_name} />
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="leave_type_id">Leave type</Label>
                    <select
                        id="leave_type_id"
                        name="leave_type_id"
                        defaultValue={defaults.leave_type_id ?? ''}
                        className="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm"
                        required
                    >
                        <option value="">Select a leave type</option>
                        {leaveTypes.map((leaveType) => (
                            <option key={leaveType.id} value={leaveType.id}>
                                {leaveType.name} ({leaveType.code})
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.leave_type_id} />
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="organisation_id">Organisation</Label>
                    <select
                        id="organisation_id"
                        name="organisation_id"
                        defaultValue={defaults.organisation_id ?? ''}
                        className="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All organisations (fallback)</option>
                        {organisations.map((organisation) => (
                            <option
                                key={organisation.id}
                                value={organisation.id}
                            >
                                {organisation.short_name || organisation.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.organisation_id} />
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="status">Status</Label>
                    <select
                        id="status"
                        name="status"
                        defaultValue={String(defaults.status ?? 1)}
                        className="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm"
                        required
                    >
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                    <InputError message={errors.status} />
                </div>

                <div className="space-y-1.5 sm:col-span-2">
                    <Label htmlFor="template_code">HTML template</Label>
                    <Textarea
                        id="template_code"
                        name="template_code"
                        defaultValue={defaults.template_code ?? ''}
                        className="min-h-[28rem] font-mono text-xs"
                        required
                    />
                    <InputError message={errors.template_code} />
                </div>

                <div className="space-y-2 text-sm text-muted-foreground sm:col-span-2">
                    <p className="font-medium text-foreground">
                        Available placeholders
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {placeholders.map((placeholder) => (
                            <code
                                key={placeholder}
                                className="rounded border border-border bg-muted/40 px-2 py-1 text-xs"
                            >
                                {placeholder}
                            </code>
                        ))}
                    </div>
                    <p>
                        Handover descriptions are rendered as a task list.
                        Signature placeholders are blank when no signature image
                        is available.
                    </p>
                </div>
            </div>
        </div>
    );
}
