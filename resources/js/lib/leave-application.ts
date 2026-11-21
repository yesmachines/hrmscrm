export type LeaveDetailKey =
    | 'due_date'
    | 'child_birth_date'
    | 'family_relationship'
    | 'festival_id'
    | 'local_contact_no'
    | 'traveling_outside_country'
    | 'destination'
    | 'travel_contact_no'
    | 'ticket_status';

export type LeaveDetailValues = Record<LeaveDetailKey, string>;

export interface LeaveFieldCondition {
    key: LeaveDetailKey;
    value: string;
}

export interface LeaveFieldDefinition {
    key: LeaveDetailKey;
    label: string;
    type: 'date' | 'select' | 'festival' | 'text' | 'tel' | 'radio';
    required: boolean;
    help: string | null;
    options: Record<string, { label: string; group?: string; max_days?: number; value?: string }>;
    required_if?: LeaveFieldCondition;
    visible_if?: LeaveFieldCondition;
}

export function isLeaveFieldVisible(
    field: LeaveFieldDefinition,
    values: Partial<LeaveDetailValues>,
): boolean {
    return !field.visible_if || values[field.visible_if.key] === field.visible_if.value;
}

export function isLeaveFieldRequired(
    field: LeaveFieldDefinition,
    values: Partial<LeaveDetailValues>,
): boolean {
    return field.required || (
        field.required_if !== undefined &&
        values[field.required_if.key] === field.required_if.value
    );
}

export function leaveValidationFormData(
    values: Record<string, string | number | boolean | File | null>,
): FormData {
    const formData = new FormData();

    for (const [key, value] of Object.entries(values)) {
        if (value instanceof File) {
            formData.append(key, value);
        } else if (typeof value === 'boolean') {
            formData.append(key, value ? '1' : '0');
        } else {
            formData.append(key, value == null ? '' : String(value));
        }
    }

    return formData;
}
