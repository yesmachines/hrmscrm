import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';

type StatusToggleProps = {
    status: number;
    url: string;
    label: string;
};

export default function StatusToggle({ status, url, label }: StatusToggleProps) {
    const [processing, setProcessing] = useState(false);
    const isActive = status === 1;

    return (
        <Badge
            asChild
            variant={isActive ? 'default' : 'outline'}
            className={`cursor-pointer rounded-full px-2.5 py-1 text-xs transition-colors disabled:cursor-wait disabled:opacity-60 ${
                isActive
                    ? 'hover:bg-primary/90'
                    : 'hover:bg-accent hover:text-accent-foreground'
            }`}
        >
            <button
                type="button"
                aria-pressed={isActive}
                aria-label={`${label}: ${isActive ? 'Active' : 'Inactive'}`}
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
            >
                {isActive ? 'Active' : 'Inactive'}
            </button>
        </Badge>
    );
}