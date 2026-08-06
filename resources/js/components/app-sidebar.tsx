import { Link } from '@inertiajs/react';
import {
    Building2,
    FileStack,
    Files,
    FolderTree,
    LayoutGrid,
    MapPin,
    Users,
} from 'lucide-react';
import { index as documentCategoriesIndex } from '@/actions/App/Http/Controllers/DocumentCategoryController';
import { index as documentTemplatesIndex } from '@/actions/App/Http/Controllers/DocumentTemplateController';
import { index as documentTypesIndex } from '@/actions/App/Http/Controllers/DocumentTypeController';
import { index as employeesIndex } from '@/actions/App/Http/Controllers/EmployeeController';
import { index as officeLocationsIndex } from '@/actions/App/Http/Controllers/OfficeLocationController';
import { index as organisationsIndex } from '@/actions/App/Http/Controllers/OrganisationController';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Employees',
        href: employeesIndex(),
        icon: Users,
    },
    {
        title: 'Organisations',
        href: organisationsIndex(),
        icon: Building2,
    },
    {
        title: 'Office locations',
        href: officeLocationsIndex(),
        icon: MapPin,
    },
    {
        title: 'Document categories',
        href: documentCategoriesIndex(),
        icon: FolderTree,
    },
    {
        title: 'Document types',
        href: documentTypesIndex(),
        icon: Files,
    },
    {
        title: 'Document templates',
        href: documentTemplatesIndex(),
        icon: FileStack,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>
        </Sidebar>
    );
}
