import { useId } from 'react';
import { FormattedDatePicker } from '@/components/formatted-date-picker';
import { Label } from '@/components/ui/label';
import type {
    LeaveDetailKey,
    LeaveDetailValues,
    LeaveFieldDefinition,
} from '@/lib/leave-application';

interface FestivalOption {
    id: number;
    name: string;
    start_date: string | null;
    end_date: string | null;
}

interface Props {
    fields: LeaveFieldDefinition[];
    values: LeaveDetailValues;
    errors: Partial<Record<LeaveDetailKey, string>>;
    active?: boolean;
    festivals: FestivalOption[];
    onChange: (key: LeaveDetailKey, value: string) => void;
    onFestivalDatesChange: (startDate: string, endDate: string) => void;
}

export default function LeaveTypeFields({
    fields,
    values,
    errors,
    active = true,
    festivals,
    onChange,
    onFestivalDatesChange,
}: Props) {
    const id = useId();

    return fields.map((field) => {
        const fieldId = `${id}-${field.key}`;
        const required = active && field.required;

        return (
            <div key={field.key} className="space-y-2">
                <Label htmlFor={fieldId}>
                    {field.label}
                    {required && <span className="text-destructive"> *</span>}
                </Label>
                {field.type === 'date' ? (
                    <FormattedDatePicker
                        id={fieldId}
                        value={values[field.key]}
                        onChange={(value) => onChange(field.key, value)}
                        required={required}
                    />
                ) : (
                    <select
                        id={fieldId}
                        value={values[field.key]}
                        required={required}
                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        onChange={(event) => {
                            onChange(field.key, event.target.value);

                            if (field.type === 'festival') {
                                const festival = festivals.find(
                                    (item) =>
                                        String(item.id) === event.target.value,
                                );

                                if (festival?.start_date) {
                                    onFestivalDatesChange(
                                        festival.start_date,
                                        festival.end_date ||
                                            festival.start_date,
                                    );
                                }
                            }
                        }}
                    >
                        <option value="">-- Choose {field.label} --</option>
                        {field.type === 'festival'
                            ? festivals.map((festival) => (
                                  <option key={festival.id} value={festival.id}>
                                      {festival.name}
                                  </option>
                              ))
                            : Object.entries(field.options).map(
                                  ([key, option]) => (
                                      <option key={key} value={key}>
                                          {option.label}
                                      </option>
                                  ),
                              )}
                    </select>
                )}
                {field.help && (
                    <p className="text-xs text-muted-foreground">
                        {field.help}
                    </p>
                )}
                {errors[field.key] && (
                    <p className="text-sm text-destructive">
                        {errors[field.key]}
                    </p>
                )}
                {field.type === 'festival' && festivals.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No festivals are applicable to this employee.
                    </p>
                )}
            </div>
        );
    });
}
