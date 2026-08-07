import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

function hasActiveChild(
    items: NavItem[] | undefined,
    isCurrentOrParentUrl: (href: NavItem['href']) => boolean,
): boolean {
    if (!items?.length) {
        return false;
    }

    return items.some((item) => {
        if (isCurrentOrParentUrl(item.href)) {
            return true;
        }

        return hasActiveChild(item.items, isCurrentOrParentUrl);
    });
}

function NavLinkItem({ item }: { item: NavItem }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={isCurrentOrParentUrl(item.href)}
                tooltip={{ children: item.title }}
                className="rounded-xl"
            >
                <Link href={item.href} prefetch>
                    {item.icon && <item.icon />}
                    <span>{item.title}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}

function NavGroupItem({ item }: { item: NavItem }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const open = hasActiveChild(item.items, isCurrentOrParentUrl);

    return (
        <Collapsible asChild defaultOpen={open} className="group/collapsible">
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        tooltip={{ children: item.title }}
                        className="rounded-xl group-data-[collapsible=icon]:hidden"
                        isActive={open}
                    >
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>

                <SidebarMenuButton
                    asChild
                    className="hidden rounded-xl group-data-[collapsible=icon]:flex"
                    tooltip={{ children: item.title }}
                    isActive={open}
                >
                    <Link href={item.href} prefetch>
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                    </Link>
                </SidebarMenuButton>

                <CollapsibleContent>
                    <SidebarMenuSub>
                        {item.items?.map((subItem) => (
                            <SidebarMenuSubItem key={subItem.title}>
                                <SidebarMenuSubButton
                                    asChild
                                    isActive={isCurrentOrParentUrl(subItem.href)}
                                >
                                    <Link href={subItem.href} prefetch>
                                        {subItem.icon && <subItem.icon />}
                                        <span>{subItem.title}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

export function NavMain({ items = [] }: { items: NavItem[] }) {
    return (
        <SidebarGroup className="px-2 py-1">
            <SidebarMenu>
                {items.map((item) =>
                    item.items?.length ? (
                        <NavGroupItem key={item.title} item={item} />
                    ) : (
                        <NavLinkItem key={item.title} item={item} />
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}
