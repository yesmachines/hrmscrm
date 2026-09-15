import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowUpDown,
    Building2,
    CheckCircle2,
    Filter,
    FolderTree,
    Pencil,
    Plus,
    Search,
    Shield,
    Trash2,
    UserCheck,
    UserMinus,
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
    CardDescription,
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

type ManagerAssignment = {
    id: number;
    employee_id: number;
    manager_id: number;
    department_id: number | null;
    priority: number;
    employee?: {
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

type PaginatedAssignments = {
    data: ManagerAssignment[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    from: number | null;
    to: number | null;
};

type PageProps = {
    assignments: PaginatedAssignments;
    employees: EmployeeSummary[];
    departments: DepartmentOption[];
    filters: {
        search: string;
        department_id: string;
        manager_id: string;
        priority: string;
    };
    stats: {
        total_assignments: number;
        assigned_employees: number;
        active_managers: number;
        total_employees: number;
    };
};

function getInitials(name?: string | null): string {
    if (!name) return 'EM';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

export default function EmployeeManagersIndex({
    assignments,
    employees = [],
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

    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingAssignment, setEditingAssignment] =
        useState<ManagerAssignment | null>(null);

    // Create Form
    const createForm = useForm({
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
            {},
            { preserveState: true },
        );
    };

    const hasActiveFilters =
        Boolean(search) ||
        Boolean(departmentFilter) ||
        Boolean(managerFilter) ||
        Boolean(priorityFilter);

    const openEditModal = (assignment: ManagerAssignment) => {
        setEditingAssignment(assignment);
        editForm.setData({
            manager_id: String(assignment.manager_id),
            department_id: assignment.department_id
                ? String(assignment.department_id)
                : '',
            priority: assignment.priority || 1,
        });
    };

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(EmployeeManagerController.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
            },
        });
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editingAssignment) return;
        editForm.put(
            EmployeeManagerController.update.url(editingAssignment.id),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingAssignment(null);
                },
            },
        );
    };

    // Filter managers for Create modal so employee cannot select themselves
    const availableManagersForCreate = useMemo(() => {
        if (!createForm.data.employee_id) return employees;
        const empId = Number(createForm.data.employee_id);
        return employees.filter((e) => e.id !== empId);
    }, [employees, createForm.data.employee_id]);

    const availableManagersForEdit = useMemo(() => {
        if (!editingAssignment) return employees;
        return employees.filter((e) => e.id !== editingAssignment.employee_id);
    }, [employees, editingAssignment]);

    const assignmentList = assignments?.data ?? [];
    const assignmentLinks = assignments?.links ?? [];

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
                                    Configure reporting lines, department hierarchies, and manager priority levels.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button
                            onClick={() => setIsCreateOpen(true)}
                            className="shadow-sm"
                        >
                            <Plus className="mr-1.5 h-4 w-4" />
                            Assign Manager
                        </Button>
                    </div>
                </div>

                {/* KPI Metrics */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-border/80 shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Total Mappings
                            </CardTitle>
                            <UserCheck className="h-4 w-4 text-primary" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.total_assignments}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Active reporting connections
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Assigned Employees
                            </CardTitle>
                            <Users className="h-4 w-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.assigned_employees}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Out of {stats.total_employees} active employees
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
                                Leading teams & departments
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Unassigned
                            </CardTitle>
                            <UserMinus className="h-4 w-4 text-amber-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {Math.max(
                                    0,
                                    stats.total_employees - stats.assigned_employees,
                                )}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Awaiting manager assignment
                            </p>
                        </CardContent>
                    </Card>
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
                                placeholder="Search by employee, manager, designation, or department..."
                                className="pl-9 bg-background"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>

                        <div className="flex flex-wrap items-center gap-2.5">
                            <select
                                value={departmentFilter}
                                onChange={(e) => setDepartmentFilter(e.target.value)}
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
                                onChange={(e) => setPriorityFilter(e.target.value)}
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

                {/* Assignments Table */}
                <div className="rounded-2xl border border-border/80 bg-card shadow-xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border/70 bg-muted/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-3.5">Employee</th>
                                    <th className="px-6 py-3.5">Reporting Manager</th>
                                    <th className="px-6 py-3.5">Department</th>
                                    <th className="px-6 py-3.5">Priority</th>
                                    <th className="px-6 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y border-border/60">
                                {assignmentList.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-6 py-12 text-center text-sm text-muted-foreground"
                                        >
                                            <div className="flex flex-col items-center justify-center gap-2">
                                                <UserCheck className="h-8 w-8 text-muted-foreground/50" />
                                                <p className="font-medium text-foreground">
                                                    No manager assignments found
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {hasActiveFilters
                                                        ? 'Try clearing search filters or broadening criteria.'
                                                        : 'Click "Assign Manager" to set up your first employee reporting structure.'}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    assignmentList.map((assignment) => {
                                        const empName =
                                            assignment.employee?.user?.name ??
                                            `Emp #${assignment.employee?.emp_num ?? assignment.employee_id}`;
                                        const mgrName =
                                            assignment.manager?.user?.name ??
                                            `Emp #${assignment.manager?.emp_num ?? assignment.manager_id}`;

                                        return (
                                            <tr
                                                key={assignment.id}
                                                className="transition-colors hover:bg-muted/30"
                                            >
                                                {/* Employee Column */}
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                                            {getInitials(empName)}
                                                        </div>
                                                        <div>
                                                            <Link
                                                                href={EmployeeController.show.url(
                                                                    assignment.employee_id,
                                                                )}
                                                                className="font-medium text-foreground hover:underline"
                                                            >
                                                                {empName}
                                                            </Link>
                                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                                <span>
                                                                    {assignment.employee
                                                                        ?.designation ??
                                                                        'Employee'}
                                                                </span>
                                                                <span>•</span>
                                                                <span>
                                                                    #
                                                                    {assignment.employee
                                                                        ?.emp_num ??
                                                                        assignment.employee_id}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>

                                                {/* Manager Column */}
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-500/10 text-xs font-bold text-blue-600 dark:text-blue-400">
                                                            {getInitials(mgrName)}
                                                        </div>
                                                        <div>
                                                            <Link
                                                                href={EmployeeController.show.url(
                                                                    assignment.manager_id,
                                                                )}
                                                                className="font-medium text-foreground hover:underline"
                                                            >
                                                                {mgrName}
                                                            </Link>
                                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                                <span>
                                                                    {assignment.manager
                                                                        ?.designation ??
                                                                        'Manager'}
                                                                </span>
                                                                <span>•</span>
                                                                <span>
                                                                    #
                                                                    {assignment.manager
                                                                        ?.emp_num ??
                                                                        assignment.manager_id}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>

                                                {/* Department Column */}
                                                <td className="px-6 py-4">
                                                    {assignment.department ? (
                                                        <span className="inline-flex items-center gap-1.5 rounded-md bg-secondary/80 px-2.5 py-1 text-xs font-medium text-secondary-foreground">
                                                            <Building2 className="h-3 w-3 text-muted-foreground" />
                                                            {assignment.department.name}
                                                            {assignment.department.code && (
                                                                <span className="text-muted-foreground">
                                                                    ({assignment.department.code})
                                                                </span>
                                                            )}
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground italic">
                                                            Default / Direct
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Priority Column */}
                                                <td className="px-6 py-4">
                                                    {assignment.priority === 1 ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                                            <CheckCircle2 className="h-3 w-3" />
                                                            Priority 1 (Primary)
                                                        </span>
                                                    ) : assignment.priority === 2 ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-400">
                                                            Priority 2 (Secondary)
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                                                            Priority {assignment.priority}
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Actions Column */}
                                                <td className="px-6 py-4 text-right">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8 text-muted-foreground hover:text-foreground"
                                                            onClick={() => openEditModal(assignment)}
                                                            title="Edit assignment"
                                                        >
                                                            <Pencil className="h-3.5 w-3.5" />
                                                        </Button>

                                                        <DeleteConfirmDialog
                                                            title="Remove Manager Assignment?"
                                                            description={`Are you sure you want to remove ${mgrName} as the reporting manager for ${empName}?`}
                                                            confirmLabel="Remove Assignment"
                                                            form={{
                                                                action: EmployeeManagerController.destroy.url(
                                                                    assignment.id,
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
                    {assignmentLinks.length > 3 && (
                        <div className="flex items-center justify-between border-t border-border/70 px-6 py-3.5">
                            <p className="text-xs text-muted-foreground">
                                Showing {assignments.from ?? 0} to {assignments.to ?? 0} of{' '}
                                {assignments.total} assignments
                            </p>
                            <div className="flex items-center gap-1">
                                {assignmentLinks.map((link, index) => (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`rounded-md px-3 py-1.5 text-xs font-medium ${
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

            {/* Create Assignment Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="sm:max-w-[480px]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <UserCheck className="h-5 w-5 text-primary" />
                            Assign Employee to Manager
                        </DialogTitle>
                        <DialogDescription>
                            Select an employee, designate their reporting manager, department, and priority level.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitCreate} className="space-y-4 py-2">
                        {/* Employee Select */}
                        <div className="space-y-1.5">
                            <Label htmlFor="create-employee">
                                Employee <span className="text-destructive">*</span>
                            </Label>
                            <select
                                id="create-employee"
                                required
                                value={createForm.data.employee_id}
                                onChange={(e) =>
                                    createForm.setData('employee_id', e.target.value)
                                }
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
                            >
                                <option value="">Select an Employee...</option>
                                {employees.map((e) => (
                                    <option key={e.id} value={e.id}>
                                        {e.name} ({e.designation} - #{e.emp_num})
                                    </option>
                                ))}
                            </select>
                            {createForm.errors.employee_id && (
                                <p className="text-xs text-destructive">
                                    {createForm.errors.employee_id}
                                </p>
                            )}
                        </div>

                        {/* Manager Select */}
                        <div className="space-y-1.5">
                            <Label htmlFor="create-manager">
                                Reporting Manager <span className="text-destructive">*</span>
                            </Label>
                            <select
                                id="create-manager"
                                required
                                value={createForm.data.manager_id}
                                onChange={(e) =>
                                    createForm.setData('manager_id', e.target.value)
                                }
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
                            >
                                <option value="">Select a Manager...</option>
                                {availableManagersForCreate.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name} ({m.designation} - #{m.emp_num})
                                    </option>
                                ))}
                            </select>
                            {createForm.errors.manager_id && (
                                <p className="text-xs text-destructive">
                                    {createForm.errors.manager_id}
                                </p>
                            )}
                        </div>

                        {/* Department Select */}
                        <div className="space-y-1.5">
                            <Label htmlFor="create-department">
                                Department <span className="text-xs text-muted-foreground">(Optional)</span>
                            </Label>
                            <select
                                id="create-department"
                                value={createForm.data.department_id}
                                onChange={(e) =>
                                    createForm.setData('department_id', e.target.value)
                                }
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
                            >
                                <option value="">Default / Not Specified</option>
                                {departments.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name} {d.code ? `(${d.code})` : ''}
                                    </option>
                                ))}
                            </select>
                            {createForm.errors.department_id && (
                                <p className="text-xs text-destructive">
                                    {createForm.errors.department_id}
                                </p>
                            )}
                        </div>

                        {/* Priority Select */}
                        <div className="space-y-1.5">
                            <Label htmlFor="create-priority">
                                Priority Level
                            </Label>
                            <select
                                id="create-priority"
                                value={createForm.data.priority}
                                onChange={(e) =>
                                    createForm.setData(
                                        'priority',
                                        Number(e.target.value),
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
                            >
                                <option value={1}>Priority 1 - Primary Manager (Direct Report)</option>
                                <option value={2}>Priority 2 - Secondary Manager (Functional / Co-report)</option>
                                <option value={3}>Priority 3 - Tertiary / Project Lead</option>
                                <option value={4}>Priority 4</option>
                                <option value={5}>Priority 5</option>
                            </select>
                            {createForm.errors.priority && (
                                <p className="text-xs text-destructive">
                                    {createForm.errors.priority}
                                </p>
                            )}
                        </div>

                        <DialogFooter className="pt-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsCreateOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createForm.processing}>
                                {createForm.processing ? 'Assigning...' : 'Assign Manager'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Assignment Dialog */}
            <Dialog
                open={Boolean(editingAssignment)}
                onOpenChange={(open) => {
                    if (!open) setEditingAssignment(null);
                }}
            >
                <DialogContent className="sm:max-w-[480px]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Pencil className="h-5 w-5 text-primary" />
                            Edit Manager Assignment
                        </DialogTitle>
                        <DialogDescription>
                            Update reporting manager, department, or priority for{' '}
                            <span className="font-semibold text-foreground">
                                {editingAssignment?.employee?.user?.name ??
                                    `Emp #${editingAssignment?.employee?.emp_num}`}
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
                                    editForm.setData('manager_id', e.target.value)
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
                                Department <span className="text-xs text-muted-foreground">(Optional)</span>
                            </Label>
                            <select
                                id="edit-department"
                                value={editForm.data.department_id}
                                onChange={(e) =>
                                    editForm.setData('department_id', e.target.value)
                                }
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-hidden focus:ring-1 focus:ring-ring"
                            >
                                <option value="">Default / Not Specified</option>
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
                                <option value={1}>Priority 1 - Primary Manager (Direct Report)</option>
                                <option value={2}>Priority 2 - Secondary Manager (Functional / Co-report)</option>
                                <option value={3}>Priority 3 - Tertiary / Project Lead</option>
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
                                onClick={() => setEditingAssignment(null)}
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
