export type LeaveDetailKey =
    'due_date' | 'child_birth_date' | 'family_relationship' | 'festival_id';

export type LeaveDetailValues = Record<LeaveDetailKey, string>;

export interface LeaveFieldDefinition {
    key: LeaveDetailKey;
    label: string;
    type: 'date' | 'select' | 'festival';
    required: boolean;
    help: string | null;
    options: Record<string, { label: string; group: string; max_days: number }>;
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
