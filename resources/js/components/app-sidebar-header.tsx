import { Breadcrumbs } from '@/components/breadcrumbs';
import { HeaderUser } from '@/components/header-user';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex h-14 shrink-0 items-center justify-between gap-4 border-b border-border bg-white px-4 transition-[width,height] ease-linear md:rounded-t-xl md:px-6">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1 text-muted-foreground hover:bg-muted hover:text-foreground" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <HeaderUser />
        </header>
    );
}
