import { Link } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    ClipboardList,
    FileStack,
    Files,
    FolderTree,
    LayoutGrid,
    Lightbulb,
    MapPin,
    Users,
} from 'lucide-react';
import { index as documentCategoriesIndex } from '@/actions/App/Http/Controllers/Documents/DocumentCategoryController';
import { index as documentTemplatesIndex } from '@/actions/App/Http/Controllers/Documents/DocumentTemplateController';
import { index as documentTypesIndex } from '@/actions/App/Http/Controllers/Documents/DocumentTypeController';
import { index as employeesIndex } from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import { index as ideasIndex } from '@/actions/App/Http/Controllers/IdeaController';
import { index as leavePoliciesIndex } from '@/actions/App/Http/Controllers/Leave/LeavePolicyController';
import { index as leaveBalancesIndex } from '@/actions/App/Http/Controllers/Leave/LeaveBalanceController';
import { index as leaveHistoriesIndex } from '@/actions/App/Http/Controllers/Leave/LeaveHistoryController';
import { index as leaveRequestsIndex } from '@/actions/App/Http/Controllers/Leave/LeaveRequestController';
import { index as leaveTypesIndex } from '@/actions/App/Http/Controllers/Leave/LeaveTypeController';
import { index as officeLocationsIndex } from '@/actions/App/Http/Controllers/Organisation/OfficeLocationController';
import { index as organisationsIndex } from '@/actions/App/Http/Controllers/Organisation/OrganisationController';
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
        title: 'Ideas',
        href: ideasIndex(),
        icon: Lightbulb,
    },
    {
        title: 'Organisation',
        icon: Building2,
        href: organisationsIndex(),
        items: [
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
        ],
    },
    {
        title: 'Documents',
        icon: Files,
        href: documentCategoriesIndex(),
        items: [
            {
                title: 'Categories',
                href: documentCategoriesIndex(),
                icon: FolderTree,
            },
            {
                title: 'Types',
                href: documentTypesIndex(),
                icon: Files,
            },
            {
                title: 'Templates',
                href: documentTemplatesIndex(),
                icon: FileStack,
            },
        ],
    },
    {
        title: 'Leave',
        icon: CalendarDays,
        href: leaveTypesIndex(),
        items: [
            {
                title: 'Leave requests',
                href: leaveRequestsIndex(),
                icon: CalendarDays,
            },
            {
                title: 'Leave balances',
                href: leaveBalancesIndex(),
                icon: CalendarDays,
            },
            {
                title: 'Leave histories',
                href: leaveHistoriesIndex(),
                icon: CalendarDays,
            },
            {
                title: 'Leave types',
                href: leaveTypesIndex(),
                icon: CalendarDays,
            },
            {
                title: 'Leave policies',
                href: leavePoliciesIndex(),
                icon: ClipboardList,
            },
        ],
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
