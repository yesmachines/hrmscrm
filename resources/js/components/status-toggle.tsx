import { router } from '@inertiajs/react';
import { useState } from 'react';

type StatusToggleProps = {
    status: number;
    url: string;
    label: string;
};

export default function StatusToggle({ status, url, label }: StatusToggleProps) {
    const [processing, setProcessing] = useState(false);
    const isActive = status === 1;

    return (
        <button
            type="button"
            role="switch"
            aria-checked={isActive}
            aria-label={`${label} status`}
            disabled={processing}
            onClick={() => {
                setProcessing(true);
                router.patch(
                    url,
                    { status: isActive ? 0 : 1 },
                    {
                        preserveScroll: true,
                        onFinish: () => setProcessing(false),
                    },
                );
            }}
            className="inline-flex items-center gap-2 rounded-full text-xs font-medium transition-opacity disabled:cursor-wait disabled:opacity-60"
        >
            <span
                aria-hidden="true"
                className={`inline-flex h-5 w-9 items-center rounded-full transition-colors ${
                    isActive ? 'bg-primary' : 'bg-muted-foreground/35'
                }`}
            >
                <span
                    className={`size-4 rounded-full bg-white shadow-sm transition-transform ${
                        isActive ? 'translate-x-[18px]' : 'translate-x-0.5'
                    }`}
                />
            </span>
            <span className={isActive ? 'text-primary' : 'text-muted-foreground'}>
                {isActive ? 'Active' : 'Inactive'}
            </span>
        </button>
    );
}