import { useId } from 'react';
import { FormattedDatePicker } from '@/components/formatted-date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    LeaveDetailKey,
    LeaveDetailValues,
    LeaveFieldDefinition,
} from '@/lib/leave-application';
import {
    isLeaveFieldRequired,
    isLeaveFieldVisible,
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

    return fields
        .filter((field) => isLeaveFieldVisible(field, values))
        .map((field) => {
            const fieldId = `${id}-${field.key}`;
            const required = active && isLeaveFieldRequired(field, values);

            return (
                <div key={field.key} className="space-y-2">
                    {field.type === 'radio' ? (
                        <fieldset>
                            <legend className="text-sm font-medium">
                                {field.label}
                                {required && (
                                    <span className="text-destructive"> *</span>
                                )}
                            </legend>
                            <div className="mt-2 flex flex-wrap gap-4">
                                {Object.entries(field.options).map(
                                    ([value, option]) => {
                                        const optionId = `${fieldId}-${value}`;
                                        const optionValue =
                                            option.value ?? value;

                                        return (
                                            <label
                                                key={value}
                                                htmlFor={optionId}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    id={optionId}
                                                    type="radio"
                                                    name={fieldId}
                                                    value={optionValue}
                                                    checked={
                                                        values[field.key] ===
                                                        optionValue
                                                    }
                                                    required={required}
                                                    onChange={() =>
                                                        onChange(
                                                            field.key,
                                                            optionValue,
                                                        )
                                                    }
                                                    className="size-4 accent-primary"
                                                />
                                                {option.label}
                                            </label>
                                        );
                                    },
                                )}
                            </div>
                        </fieldset>
                    ) : (
                        <Label htmlFor={fieldId}>
                            {field.label}
                            {required && (
                                <span className="text-destructive"> *</span>
                            )}
                        </Label>
                    )}
                    {field.type === 'date' ? (
                        <FormattedDatePicker
                            id={fieldId}
                            value={values[field.key]}
                            onChange={(value) => onChange(field.key, value)}
                            required={required}
                        />
                    ) : field.type === 'text' || field.type === 'tel' ? (
                        <Input
                            id={fieldId}
                            type={field.type}
                            value={values[field.key]}
                            required={required}
                            pattern={
                                field.key === 'local_contact_no'
                                    ? '[+]?[0-9]+'
                                    : undefined
                            }
                            minLength={
                                field.key === 'local_contact_no' ? 7 : undefined
                            }
                            maxLength={
                                field.key === 'local_contact_no'
                                    ? 20
                                    : undefined
                            }
                            className={
                                field.key === 'local_contact_no' ||
                                field.key === 'destination' ||
                                field.key === 'travel_contact_no'
                                    ? 'max-w-xs'
                                    : undefined
                            }
                            onChange={(event) =>
                                onChange(field.key, event.target.value)
                            }
                        />
                    ) : field.type === 'radio' ? null : (
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
                                            String(item.id) ===
                                            event.target.value,
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
                                      <option
                                          key={festival.id}
                                          value={festival.id}
                                      >
                                          {festival.name}
                                      </option>
                                  ))
                                : Object.entries(field.options).map(
                                      ([key, option]) => (
                                          <option
                                              key={key}
                                              value={option.value ?? key}
                                          >
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
