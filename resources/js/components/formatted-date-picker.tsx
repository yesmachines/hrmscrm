import { CalendarDays } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';

type FormattedDatePickerProps = {
    id?: string;
    name?: string;
    type?: 'date' | 'datetime-local';
    value?: string;
    defaultValue?: string;
    onChange?: (value: string) => void;
    min?: string;
    max?: string;
    required?: boolean;
    disabled?: boolean;
    className?: string;
};

function formatPickerValue(value: string, type: 'date' | 'datetime-local'): string {
    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/);

    if (!match) {
        return value;
    }

    const [, year, month, day, hour, minute] = match;
    const date = `${day}-${month}-${year}`;

    return type === 'datetime-local' && hour && minute
        ? `${date} ${hour}:${minute}`
        : date;
}

export function FormattedDatePicker({
    id,
    name,
    type = 'date',
    value,
    defaultValue,
    onChange,
    min,
    max,
    required,
    disabled,
    className,
}: FormattedDatePickerProps) {
    const generatedId = useId();
    const nativeId = id ? `${id}-native` : `date-picker-${generatedId}`;
    const pickerRef = useRef<HTMLInputElement>(null);
    const [internalValue, setInternalValue] = useState(defaultValue ?? '');
    const currentValue = value ?? internalValue;

    const openPicker = () => {
        const picker = pickerRef.current;

        if (picker && typeof picker.showPicker === 'function') {
            picker.showPicker();
        } else {
            picker?.click();
        }
    };

    const updateValue = (nextValue: string) => {
        if (value === undefined) {
            setInternalValue(nextValue);
        }

        onChange?.(nextValue);
    };

    return (
        <div className="relative">
            <Input
                id={id}
                type="text"
                value={formatPickerValue(currentValue, type)}
                placeholder={type === 'datetime-local' ? 'dd-mm-yyyy hh:mm' : 'dd-mm-yyyy'}
                readOnly
                required={required}
                disabled={disabled}
                onClick={openPicker}
                className={`cursor-pointer pr-10 ${className ?? ''}`}
            />
            <input
                ref={pickerRef}
                id={nativeId}
                type={type}
                name={name}
                value={currentValue}
                min={min}
                max={max}
                disabled={disabled}
                tabIndex={-1}
                aria-hidden="true"
                onChange={(event) => updateValue(event.target.value)}
                className="pointer-events-none absolute right-10 top-1/2 h-px w-px -translate-y-1/2 opacity-0"
            />
            <button
                type="button"
                aria-label={type === 'datetime-local' ? 'Choose date and time' : 'Choose date'}
                disabled={disabled}
                onClick={openPicker}
                className="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
            >
                <CalendarDays className="size-4" />
            </button>
        </div>
    );
}
