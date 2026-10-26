// import { Moon, Sun } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { HeaderUser } from '@/components/header-user';
// import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
// import { useAppearance } from '@/hooks/use-appearance';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    // const { appearance, updateAppearance } = useAppearance();
    // const isDark = appearance === 'dark';

    return (
        <header className="flex h-14 shrink-0 items-center justify-between gap-4 border-b border-border bg-background px-4 transition-[width,height] ease-linear md:rounded-t-xl md:px-6">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1 text-muted-foreground hover:bg-muted hover:text-foreground" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="flex items-center gap-2">
                {/*
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={`Switch to ${isDark ? 'light' : 'dark'} mode`}
                    title={`Switch to ${isDark ? 'light' : 'dark'} mode`}
                    aria-pressed={isDark}
                    onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
                    data-test="appearance-toggle"
                >
                    {isDark ? (
                        <Sun aria-hidden="true" />
                    ) : (
                        <Moon aria-hidden="true" />
                    )}
                </Button>
                */}
                <HeaderUser />
            </div>
        </header>
    );
}
