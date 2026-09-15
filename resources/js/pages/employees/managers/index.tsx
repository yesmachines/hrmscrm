import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Building2,
    CheckCircle2,
    Filter,
    Pencil,
    Plus,
    Search,
    Shield,
    Trash2,
    UserCheck,
    UserMinus,
    UserPlus,
    Users,
    X,
} from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import EmployeeManagerController from '@/actions/App/Http/Controllers/Employees/EmployeeManagerController';
import { DeleteConfirmDialog } from '@/components/delete-confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type EmployeeSummary = {
    id: number;
    name: string;
    email?: string | null;
    emp_num: string;
    employee_code?: string | null;
    designation: string;
    image_url?: string | null;
};

type DepartmentOption = {
    id: number;
    name: string;
    code?: string | null;
};

type ReportingManagerMapping = {
    id: number;
    employee_id: number;
    manager_id: number;
    department_id: number | null;
    priority: number;
    manager?: {
        id: number;
        user_id: number;
        emp_num: string;
        employee_code?: string | null;
        designation: string;
        division?: string | null;
        image_url?: string | null;
        user?: {
            id: number;
            name: string;
            email: string;
        } | null;
    } | null;
    department?: {
        id: number;
        name: string;
        code?: string | null;
    } | null;
};

type EmployeeRow = {
    id: number;
    user_id: number;
    emp_num: string;
    employee_code?: string | null;
    designation: string;
    division?: string | null;
    image_url?: string | null;
    user?: {
        id: number;
        name: string;
        email: string;
    } | null;
    department?: {
        id: number;
        name: string;
        code?: string | null;
    } | null;
    reporting_managers: ReportingManagerMapping[];
};

type PaginatedEmployees = {
    data: EmployeeRow[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    from: number | null;
    to: number | null;
};

type PageProps = {
    employees: PaginatedEmployees;
    allEmployees: EmployeeSummary[];
    departments: DepartmentOption[];
    filters: {
        search: string;
        department_id: string;
        manager_id: string;
        priority: string;
        tab: string;
    };
    stats: {
        total_assignments: number;
        assigned_employees: number;
        active_managers: number;
        total_employees: number;
        unassigned_employees: number;
    };
};

function getInitials(name?: string | null): string {
    if (!name) return 'EM';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

export default function EmployeeManagersIndex({
    employees,
    allEmployees = [],
    departments = [],
    filters,
    stats,
}: PageProps) {
    const [search, setSearch] = useState(filters.search || '');
    const [departmentFilter, setDepartmentFilter] = useState(
        filters.department_id || '',
    );
    const [managerFilter, setManagerFilter] = useState(filters.manager_id || '');
    const [priorityFilter, setPriorityFilter] = useState(filters.priority || '');
    const currentTab = filters.tab || 'all';

    const [isAssignOpen, setIsAssignOpen] = useState(false);
    const [selectedEmployeeForAssign, setSelectedEmployeeForAssign] =
        useState<EmployeeRow | null>(null);

    const [editingMapping, setEditingMapping] =
        useState<{ mapping: ReportingManagerMapping; employeeName: string } | null>(
            null,
        );

    // Assign Form
    const assignForm = useForm({
        employee_id: '',
        manager_id: '',
        department_id: '',
        priority: 1,
    });

    // Edit Form
    const editForm = useForm({
        manager_id: '',
        department_id: '',
        priority: 1,
    });

    const handleFilterSubmit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            EmployeeManagerController.index.url(),
            {
                search,
                department_id: departmentFilter,
                manager_id: managerFilter,
                priority: priorityFilter,
                tab: currentTab,
            },
            { preserveState: true },
        );
    };

    const handleTabChange = (newTab: string) => {
        router.get(
            EmployeeManagerController.index.url(),
            {
                search,
                department_id: departmentFilter,
                manager_id: managerFilter,
                priority: priorityFilter,
                tab: newTab,
            },
            { preserveState: true },
        );
    };

    const handleClearFilters = () => {
        setSearch('');
        setDepartmentFilter('');
        setManagerFilter('');
        setPriorityFilter('');
        router.get(
            EmployeeManagerController.index.url(),
            { tab: currentTab },
            { preserveState: true },
        );
    };

    const hasActiveFilters =
        Boolean(search) ||
        Boolean(departmentFilter) ||
        Boolean(managerFilter) ||
        Boolean(priorityFilter);

    const openAssignForEmployee = (employee?: EmployeeRow) => {
        if (employee) {
            setSelectedEmployeeForAssign(employee);
            // Default priority: if employee already has managers, default to next priority
            const nextPriority = (employee.reporting_managers?.length ?? 0) + 1;
            assignForm.setData({
                employee_id: String(employee.id),
                manager_id: '',
                department_id: employee.department?.id
                    ? String(employee.department.id)
                    : '',
                priority: nextPriority,
            });
        } else {
            setSelectedEmployeeForAssign(null);
            assignForm.setData({
                employee_id: '',
                manager_id: '',
                department_id: '',
                priority: 1,
            });
        }
        setIsAssignOpen(true);
    };

    const openEditModal = (
        mapping: ReportingManagerMapping,
        employeeName: string,
    ) => {
        setEditingMapping({ mapping, employeeName });
        editForm.setData({
            manager_id: String(mapping.manager_id),
            department_id: mapping.department_id
                ? String(mapping.department_id)
                : '',
            priority: mapping.priority || 1,
        });
    };

    const submitAssign = (e: FormEvent) => {
        e.preventDefault();
        assignForm.post(EmployeeManagerController.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setIsAssignOpen(false);
                assignForm.reset();
                setSelectedEmployeeForAssign(null);
            },
        });
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editingMapping) return;
        editForm.put(
            EmployeeManagerController.update.url(editingMapping.mapping.id),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingMapping(null);
                },
            },
        );
    };

    // Filter available managers so an employee cannot report to themselves
    const availableManagersForAssign = useMemo(() => {
        const empId = Number(assignForm.data.employee_id);
        if (!empId) return allEmployees;
        return allEmployees.filter((e) => e.id !== empId);
    }, [allEmployees, assignForm.data.employee_id]);

    const availableManagersForEdit = useMemo(() => {
        if (!editingMapping) return allEmployees;
        return allEmployees.filter(
            (e) => e.id !== editingMapping.mapping.employee_id,
        );
    }, [allEmployees, editingMapping]);

    const employeeList = employees?.data ?? [];
    const paginationLinks = employees?.links ?? [];

    return (
        <>
            <Head title="Assign Employees to Manager" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <UserCheck className="h-5 w-5" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                    Assign Employees to Manager
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Assign employees to reporting managers, select department hierarchies, and define priority levels.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button
                            onClick={() => openAssignForEmployee()}
                            className="shadow-sm"
                        >
                            <Plus className="mr-1.5 h-4 w-4" />
                            Assign Manager
                        </Button>
                    </div>
                </div>

                {/* KPI Metrics */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card
                        onClick={() => handleTabChange('all')}
                        className={`cursor-pointer transition-all border-border/80 shadow-xs hover:border-primary/50 ${
                            currentTab === 'all'
                                ? 'ring-2 ring-primary/20 bg-primary/5'
                                : ''
                        }`}
                    >
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Total Employees
                            </CardTitle>
                            <Users className="h-4 w-4 text-primary" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.total_employees}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {stats.total_assignments} active reporting connections
                            </p>
                        </CardContent>
                    </Card>

                    <Card
                        onClick={() => handleTabChange('assigned')}
                        className={`cursor-pointer transition-all border-border/80 shadow-xs hover:border-emerald-500/50 ${
                            currentTab === 'assigned'
                                ? 'ring-2 ring-emerald-500/20 bg-emerald-50/20 dark:bg-emerald-950/20'
                                : ''
                        }`}
                    >
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Assigned Employees
                            </CardTitle>
                            <UserCheck className="h-4 w-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                                {stats.assigned_employees}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Reporting to {stats.active_managers} managers
                            </p>
                        </CardContent>
                    </Card>

                    <Card
                        onClick={() => handleTabChange('unassigned')}
                        className={`cursor-pointer transition-all border-border/80 shadow-xs hover:border-amber-500/50 ${
                            currentTab === 'unassigned'
                                ? 'ring-2 ring-amber-500/20 bg-amber-50/20 dark:bg-amber-950/20'
                                : ''
                        }`}
                    >
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Unassigned Employees
                            </CardTitle>
                            <UserMinus className="h-4 w-4 text-amber-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-amber-600 dark:text-amber-400">
                                {stats.unassigned_employees}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Awaiting manager assignment
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Active Managers
                            </CardTitle>
                            <Shield className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.active_managers}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Leading reporting departments
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Tabs & Filter Bar */}
                <div className="space-y-3">
                    {/* View Tabs */}
                    <div className="flex items-center gap-2 border-b border-border/80 pb-2">
                        <button
                            type="button"
                            onClick={() => handleTabChange('all')}
                            className={`flex items-center gap-2 rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors ${
                                currentTab === 'all'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-muted'
                            }`}
                        >
                            <Users className="h-3.5 w-3.5" />
                            All Employees ({stats.total_employees})
                        </button>

                        <button
                            type="button"
                            onClick={() => handleTabChange('assigned')}
                            className={`flex items-center gap-2 rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors ${
                                currentTab === 'assigned'
                                    ? 'bg-emerald-600 text-white'
                                    : 'text-muted-foreground hover:bg-muted'
                            }`}
                        >
                            <CheckCircle2 className="h-3.5 w-3.5 text-emerald-300" />
                            Assigned ({stats.assigned_employees})
                        </button>

                        <button
                            type="button"
                            onClick={() => handleTabChange('unassigned')}
                            className={`flex items-center gap-2 rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors ${
                                currentTab === 'unassigned'
                                    ? 'bg-amber-600 text-white'
                                    : 'text-muted-foreground hover:bg-muted'
                            }`}
                        >
                            <UserMinus className="h-3.5 w-3.5 text-amber-300" />
                            Unassigned ({stats.unassigned_employees})
                        </button>
                    </div>

                    {/* Filter and Search Bar */}
                    <div className="rounded-2xl border border-border/80 bg-card p-4 shadow-xs">
                        <form
                            onSubmit={handleFilterSubmit}
                            className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                        >
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    type="text"
                                    placeholder="Search by employee name, manager name, designation, or department..."
                                    className="pl-9 bg-background"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>

                            <div className="flex flex-wrap items-center gap-2.5">
                                <select
                                    value={departmentFilter}
                                    onChange={(e) =>
                                        setDepartmentFilter(e.target.value)
                                    }
                                    className="h-9 rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
                                >
                                    <option value="">All Departments</option>
                                    {departments.map((d) => (
                                        <option key={d.id} value={d.id}>
                                            {d.name} {d.code ? `(${d.code})` : ''}
                                        </option>
                                    ))}
                                </select>

                                <select
                                    value={priorityFilter}
                                    onChange={(e) =>
                                        setPriorityFilter(e.target.value)
                                    }
                                    className="h-9 rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
                                >
                                    <option value="">All Priorities</option>
                                    <option value="1">Priority 1 (Primary)</option>
                                    <option value="2">Priority 2 (Secondary)</option>
                                    <option value="3">Priority 3</option>
                                </select>

                                <Button type="submit" size="sm" variant="secondary">
                                    <Filter className="mr-1.5 h-3.5 w-3.5" />
                                    Filter
                                </Button>

                                {hasActiveFilters && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={handleClearFilters}
                                        className="text-muted-foreground"
                                    >
                                        <X className="mr-1 h-3.5 w-3.5" />
                                        Clear
                                    </Button>
                                )}
                            </div>
                        </form>
                    </div>
                </div>

                {/* Employees & Manager Assignments Table */}
                <div className="rounded-2xl border border-border/80 bg-card shadow-xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border/70 bg-muted/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-3.5">Employee</th>
                                    <th className="px-6 py-3.5">Reporting Manager</th>
                                    <th className="px-6 py-3.5">Reporting Department</th>
                                    <th className="px-6 py-3.5">Priority</th>
                                    <th className="px-6 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y border-border/60">
                                {employeeList.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-6 py-12 text-center text-sm text-muted-foreground"
                                        >
                                            <div className="flex flex-col items-center justify-center gap-2">
                                                <Users className="h-8 w-8 text-muted-foreground/50" />
                                                <p className="font-medium text-foreground">
                                                    No employees found
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {hasActiveFilters
                                                        ? 'Try clearing search filters or broadening criteria.'
                                                        : 'No employees matched the selected view.'}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    employeeList.map((emp) => {
                                        const empName =
                                            emp.user?.name ?? `Emp #${emp.emp_num}`;
                                        const mappings = emp.reporting_managers ?? [];
                                        const isAssigned = mappings.length > 0;

                                        return (
                                            <tr
                                                key={emp.id}
                                                className="transition-colors hover:bg-muted/30"
                                            >
                                                {/* Employee Info */}
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                                            {getInitials(empName)}
                                                        </div>
                                                        <div>
                                                            <Link
                                                                href={EmployeeController.show.url(
                                                                    emp.id,
                                                                )}
                                                                className="font-medium text-foreground hover:underline"
                                                            >
                                                                {empName}
                                                            </Link>
                                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                                <span>
                                                                    {emp.designation ||
                                                                        'Employee'}
                                                                </span>
                                                                <span>•</span>
                                                                <span>
                                                                    #{emp.emp_num}
                                                                </span>
                                                                {emp.department && (
                                                                    <>
                                                                        <span>•</span>
                                                                        <span className="text-foreground/80 font-medium">
                                                                            {
                                                                                emp
                                                                                    .department
                                                                                    .name
                                                                            }
                                                                        </span>
                                                                    </>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>

                                                {/* Reporting Manager(s) */}
                                                <td className="px-6 py-4">
                                                    {!isAssigned ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">
                                                            <UserMinus className="h-3 w-3" />
                                                            Not Assigned
                                                        </span>
                                                    ) : (
                                                        <div className="space-y-2">
                                                            {mappings.map((m) => {
                                                                const mgrName =
                                                                    m.manager?.user
                                                                        ?.name ??
                                                                    `Emp #${m.manager?.emp_num ?? m.manager_id}`;
                                                                return (
                                                                    <div
                                                                        key={m.id}
                                                                        className="flex items-center gap-2"
                                                                    >
                                                                        <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-500/10 text-[10px] font-bold text-blue-600 dark:text-blue-400">
                                                                            {getInitials(
                                                                                mgrName,
                                                                            )}
                                                                        </div>
                                                                        <div>
                                                                            <Link
                                                                                href={EmployeeController.show.url(
                                                                                    m.manager_id,
                                                                                )}
                                                                                className="font-medium text-foreground hover:underline text-xs"
                                                                            >
                                                                                {mgrName}
                                                                            </Link>
                                                                            <span className="text-[11px] text-muted-foreground block">
                                                                                {m.manager
                                                                                    ?.designation ??
                                                                                    'Manager'}{' '}
                                                                                (#{m.manager?.emp_num})
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    )}
                                                </td>

                                                {/* Reporting Department */}
                                                <td className="px-6 py-4">
                                                    {!isAssigned ? (
                                                        <span className="text-xs text-muted-foreground italic">
                                                            -
                                                        </span>
                                                    ) : (
                                                        <div className="space-y-1.5">
                                                            {mappings.map((m) => (
                                                                <div key={m.id}>
                                                                    {m.department ? (
                                                                        <span className="inline-flex items-center gap-1.5 rounded-md bg-secondary/80 px-2 py-0.5 text-xs font-medium text-secondary-foreground">
                                                                            <Building2 className="h-3 w-3 text-muted-foreground" />
                                                                            {
                                                                                m
                                                                                    .department
                                                                                    .name
                                                                            }
                                                                            {m
                                                                                .department
                                                                                .code && (
                                                                                <span className="text-muted-foreground">
                                                                                    (
                                                                                    {
                                                                                        m
                                                                                            .department
                                                                                            .code
                                                                                    }
                                                                                    )
                                                                                </span>
                                                                            )}
                                                                        </span>
                                                                    ) : (
                                                                        <span className="text-xs text-muted-foreground italic">
                                                                            Default / Direct
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            ))}
                                                        </div>
                                                    )}
                                                </td>

                                                {/* Priority */}
                                                <td className="px-6 py-4">
                                                    {!isAssigned ? (
                                                        <span className="text-xs text-muted-foreground italic">
                                                            -
                                                        </span>
                                                    ) : (
                                                        <div className="space-y-1.5">
                                                            {mappings.map((m) => (
                                                                <div key={m.id}>
                                                                    {m.priority === 1 ? (
                                                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                                                            <CheckCircle2 className="h-3 w-3" />
                                                                            Priority 1 (Primary)
                                                                        </span>
                                                                    ) : m.priority === 2 ? (
                                                                        <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-400">
                                                                            Priority 2 (Secondary)
                                                                        </span>
                                                                    ) : (
                                                                        <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                                                                            Priority {m.priority}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            ))}
                                                        </div>
                                                    )}
                                                </td>

                                                {/* Actions */}
                                                <td className="px-6 py-4 text-right">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        {!isAssigned ? (
                                                            <Button
                                                                size="sm"
                                                                variant="default"
                                                                onClick={() =>
                                                                    openAssignForEmployee(
                                                                        emp,
                                                                    )
                                                                }
                                                                className="h-8 gap-1 text-xs"
                                                            >
                                                                <Plus className="h-3.5 w-3.5" />
                                                                Assign Manager
                                                            </Button>
                                                        ) : (
                                                            <>
                                                                {/* Edit primary manager */}
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-8 w-8 text-muted-foreground hover:text-foreground"
                                                                    onClick={() =>
                                                                        openEditModal(
                                                                            mappings[0],
                                                                            empName,
                                                                        )
                                                                    }
                                                                    title="Edit manager, department or priority"
                                                                >
                                                                    <Pencil className="h-3.5 w-3.5" />
                                                                </Button>

                                                                {/* Add another manager (e.g. secondary) */}
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-8 w-8 text-muted-foreground hover:text-primary"
                                                                    onClick={() =>
                                                                        openAssignForEmployee(
                                                                            emp,
                                                                        )
                                                                    }
                                                                    title="Add secondary manager"
                                                                >
                                                                    <UserPlus className="h-3.5 w-3.5" />
                                                                </Button>

                                                                {/* Remove mapping */}
                                                                <DeleteConfirmDialog
                                                                    title="Remove Manager Assignment?"
                                                                    description={`Are you sure you want to remove the reporting manager assignment for ${empName}?`}
                                                                    confirmLabel="Remove Assignment"
                                                                    form={{
                                                                        action: EmployeeManagerController.destroy.url(
                                                                            mappings[0]
                                                                                .id,
                                                                        ),
                                                                        method: 'delete',
                                                                    }}
                                                                >
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="h-8 w-8 text-muted-foreground hover:text-destructive"
                                                                        title="Delete assignment"
                                                                    >
                                                                        <Trash2 className="h-3.5 w-3.5" />
                                                                    </Button>
                                                                </DeleteConfirmDialog>
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {paginationLinks.length > 3 && (
                        <div className="flex items-center justify-between border-t border-border/70 px-6 py-3.5">
                            <p className="text-xs text-muted-foreground">
                                Showing {employees.from ?? 0} to{' '}
                                {employees.to ?? 0} of {employees.total} employees
                            </p>
                            <div className="flex items-center gap-1">
                                {paginationLinks.map((link, index) => (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`rounded-md px-3 py-1.5 text-xs font-semibold ${
                                            link.active
                                                ? 'bg-primary text-primary-foreground'
                                                : link.url
                                                  ? 'text-muted-foreground hover:bg-muted'
                                                  : 'cursor-not-allowed opacity-40'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Assign Manager Modal */}
            <Dialog open={isAssignOpen} onOpenChange={setIsAssignOpen}>
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <UserCheck className="h-5 w-5 text-primary" />
                            {selectedEmployeeForAssign
                                ? `Assign Manager to ${selectedEmployeeForAssign.user?.name ?? `Emp #${selectedEmployeeForAssign.emp_num}`}`
                                : 'Assign Employee to Manager'}
                        </DialogTitle>
                        <DialogDescription>
                            Select the reporting manager, department, and priority level.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitAssign} className="space-y-4 py-2">
                        {/* Employee Selector (Locked if opened from row, or dropdown) */}
                        <div className="space-y-1.5">
                            <Label htmlFor="assign-employee">
                                Employee <span className="text-destructive">*</span>
                            </Label>
                            {selectedEmployeeForAssign ? (
                                <div className="flex items-center justify-between rounded-md border border-border bg-muted/40 px-3 py-2 text-xs">
                                    <div>
                                        <span className="font-semibold text-foreground">
                                            {selectedEmployeeForAssign.user?.name}
                                        </span>
                                        <span className="text-muted-foreground ml-2">
                                            (
                                            {selectedEmployeeForAssign.designation} - #
                                            {selectedEmployeeForAssign.emp_num})
                                        </span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-6 text-[11px] text-muted-foreground"
                                        onClick={() =>
                                            setSelectedEmployeeForAssign(null)
                                        }
                                    >
                                        Change
                                    </Button>
                                </div>
                            ) : (
                                <select
                                    id="assign-employee"
                                    required
                                    value={assignForm.data.employee_id}
                                    onChange={(e) =>
                                        assignForm.setData(
                                            'employee_id',
                                            e.target.value,
                                        )
                                    }
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
                                >
                                    <option value="">Select an Employee...</option>
                                    {allEmployees.map((e) => (
                                        <option key={e.id} value={e.id}>
                                            {e.name} ({e.designation} - #{e.emp_num})
                                        </option>
                                    ))}
                                </select>
                            )}
                            {assignForm.errors.employee_id && (
                                <p className="text-xs text-destructive">
                                    {assignForm.errors.employee_id}
                                </p>
                            )}
                        </div>

                        {/* Manager Select */}
                        <div className="space-y-1.5">
                            <Label htmlFor="assign-manager">
                                Reporting Manager <span className="text-destructive">*</span>
                            </Label>
                            <select
                                id="assign-manager"
                                required
                                value={assignForm.data.manager_id}
                                onChange={(e) =>
                                    assignForm.setData(
                                        'manager_id',
                                        e.target.value,
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
                            >
                                <option value="">Select a Manager...</option>
                                {availableManagersForAssign.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name} ({m.designation} - #{m.emp_num})
                                    </option>
                                ))}
                            </select>
                            {assignForm.errors.manager_id && (
                                <p className="text-xs text-destructive">
                                    {assignForm.errors.manager_id}
                                </p>
                            )}
                        </div>

                        {/* Department Select */}
                        <div className="space-y-1.5">
                            <Label htmlFor="assign-department">
                                Reporting Department{' '}
                                <span className="text-xs text-muted-foreground">
                                    (Optional)
                                </span>
                            </Label>
                            <select
                                id="assign-department"
                                value={assignForm.data.department_id}
                                onChange={(e) =>
                                    assignForm.setData(
                                        'department_id',
                                        e.target.value,
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
                            >
                                <option value="">Default / Same as Employee</option>
                                {departments.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name} {d.code ? `(${d.code})` : ''}
                                    </option>
                                ))}
                            </select>
                            {assignForm.errors.department_id && (
                                <p className="text-xs text-destructive">
                                    {assignForm.errors.department_id}
                                </p>
                            )}
                        </div>

                        {/* Priority Select */}
                        <div className="space-y-1.5">
                            <Label htmlFor="assign-priority">Priority Level</Label>
                            <select
                                id="assign-priority"
                                value={assignForm.data.priority}
                                onChange={(e) =>
                                    assignForm.setData(
                                        'priority',
                                        Number(e.target.value),
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
                            >
                                <option value={1}>
                                    Priority 1 - Primary Manager (Direct Report)
                                </option>
                                <option value={2}>
                                    Priority 2 - Secondary Manager (Functional / Co-report)
                                </option>
                                <option value={3}>
                                    Priority 3 - Tertiary / Project Lead
                                </option>
                                <option value={4}>Priority 4</option>
                                <option value={5}>Priority 5</option>
                            </select>
                            {assignForm.errors.priority && (
                                <p className="text-xs text-destructive">
                                    {assignForm.errors.priority}
                                </p>
                            )}
                        </div>

                        <DialogFooter className="pt-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsAssignOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={assignForm.processing}>
                                {assignForm.processing
                                    ? 'Assigning...'
                                    : 'Assign Manager'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Assignment Modal */}
            <Dialog
                open={Boolean(editingMapping)}
                onOpenChange={(open) => {
                    if (!open) setEditingMapping(null);
                }}
            >
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Pencil className="h-5 w-5 text-primary" />
                            Edit Manager Assignment
                        </DialogTitle>
                        <DialogDescription>
                            Update reporting manager, department, or priority for{' '}
                            <span className="font-semibold text-foreground">
                                {editingMapping?.employeeName}
                            </span>
                            .
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitEdit} className="space-y-4 py-2">
                        {/* Manager Select */}
                        <div className="space-y-1.5">
                            <Label htmlFor="edit-manager">
                                Reporting Manager <span className="text-destructive">*</span>
                            </Label>
                            <select
                                id="edit-manager"
                                required
                                value={editForm.data.manager_id}
                                onChange={(e) =>
                                    editForm.setData(
                                        'manager_id',
                                        e.target.value,
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
                            >
                                <option value="">Select a Manager...</option>
                                {availableManagersForEdit.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name} ({m.designation} - #{m.emp_num})
                                    </option>
                                ))}
                            </select>
                            {editForm.errors.manager_id && (
                                <p className="text-xs text-destructive">
                                    {editForm.errors.manager_id}
                                </p>
                            )}
                        </div>

                        {/* Department Select */}
                        <div className="space-y-1.5">
                            <Label htmlFor="edit-department">
                                Reporting Department{' '}
                                <span className="text-xs text-muted-foreground">
                                    (Optional)
                                </span>
                            </Label>
                            <select
                                id="edit-department"
                                value={editForm.data.department_id}
                                onChange={(e) =>
                                    editForm.setData(
                                        'department_id',
                                        e.target.value,
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
                            >
                                <option value="">Default / Same as Employee</option>
                                {departments.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name} {d.code ? `(${d.code})` : ''}
                                    </option>
                                ))}
                            </select>
                            {editForm.errors.department_id && (
                                <p className="text-xs text-destructive">
                                    {editForm.errors.department_id}
                                </p>
                            )}
                        </div>

                        {/* Priority Select */}
                        <div className="space-y-1.5">
                            <Label htmlFor="edit-priority">Priority Level</Label>
                            <select
                                id="edit-priority"
                                value={editForm.data.priority}
                                onChange={(e) =>
                                    editForm.setData(
                                        'priority',
                                        Number(e.target.value),
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
                            >
                                <option value={1}>
                                    Priority 1 - Primary Manager (Direct Report)
                                </option>
                                <option value={2}>
                                    Priority 2 - Secondary Manager (Functional / Co-report)
                                </option>
                                <option value={3}>
                                    Priority 3 - Tertiary / Project Lead
                                </option>
                                <option value={4}>Priority 4</option>
                                <option value={5}>Priority 5</option>
                            </select>
                            {editForm.errors.priority && (
                                <p className="text-xs text-destructive">
                                    {editForm.errors.priority}
                                </p>
                            )}
                        </div>

                        <DialogFooter className="pt-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditingMapping(null)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={editForm.processing}>
                                {editForm.processing ? 'Saving...' : 'Save Changes'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

EmployeeManagersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employees', href: EmployeeController.index.url() },
        {
            title: 'Assign to Manager',
            href: EmployeeManagerController.index.url(),
        },
    ],
};
