import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                <AppLogoIcon className="size-4 fill-current" />
            </div>
            <div className="ml-1.5 grid flex-1 text-left text-sm">
                <span className="truncate font-semibold tracking-tight text-sidebar-foreground">
                    {name}
                </span>
            </div>
        </>
    );
}
