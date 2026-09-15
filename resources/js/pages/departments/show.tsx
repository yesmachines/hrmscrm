import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Edit, Users, FolderTree } from 'lucide-react';
import DepartmentController from '@/actions/App/Http/Controllers/Organisation/DepartmentController';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type Department = {
    id: number;
    name: string;
    code: string | null;
    status: number;
    created_at: string | null;
    updated_at: string | null;
};

type Employee = {
    id: number;
    name: string | null;
    email: string | null;
    emp_num: string;
    designation: string;
    status: number;
};

export default function DepartmentsShow({
    department,
    employees = [],
}: {
    department: Department;
    employees?: Employee[];
}) {
    return (
        <>
            <Head title={`Department: ${department.name}`} />

            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                                <FolderTree className="h-6 w-6 text-primary" />
                                {department.name}
                            </h1>
                            <span
                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                    department.status === 1
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                        : 'bg-muted text-muted-foreground'
                                }`}
                            >
                                {department.status === 1 ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Department code: {department.code ?? '—'}
                        </p>
                    </div>                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={DepartmentController.edit.url(department.id)}>
                                <Edit className="mr-1.5 h-4 w-4" />
                                Edit
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={DepartmentController.index.url()}>
                                <ArrowLeft className="mr-1.5 h-4 w-4" />
                                Back to Departments
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="rounded-2xl border border-border/80 bg-card p-6 shadow-xs">
                        <div className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                            Department Code
                        </div>
                        <div className="mt-2 font-mono text-lg font-bold text-foreground">
                            {department.code ?? '—'}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-border/80 bg-card p-6 shadow-xs">
                        <div className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                            Status
                        </div>
                        <div className="mt-2 text-lg font-bold text-foreground">
                            {department.status === 1 ? 'Active' : 'Inactive'}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-border/80 bg-card p-6 shadow-xs">
                        <div className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                            Total Employees
                        </div>
                        <div className="mt-2 text-lg font-bold text-primary flex items-center gap-1.5">
                            <Users className="h-5 w-5" />
                            {employees.length}
                        </div>
                    </div>
                </div>

                <div className="rounded-2xl border border-border/80 bg-card shadow-xs overflow-hidden">
                    <div className="border-b border-border/70 bg-muted/40 px-6 py-4">
                        <h2 className="text-base font-semibold text-foreground">
                            Employees in {department.name}
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border/70 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-3">Emp #</th>
                                    <th className="px-6 py-3">Name</th>
                                    <th className="px-6 py-3">Designation</th>
                                    <th className="px-6 py-3">Status</th>
                                    <th className="px-6 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/60">
                                {employees.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-6 py-8 text-center text-sm text-muted-foreground"
                                        >
                                            No employees assigned to this department yet.
                                        </td>
                                    </tr>
                                ) : (
                                    employees.map((emp) => (
                                        <tr key={emp.id} className="hover:bg-muted/30">
                                            <td className="px-6 py-3 font-mono text-xs text-muted-foreground">
                                                {emp.emp_num}
                                            </td>
                                            <td className="px-6 py-3 font-medium text-foreground">
                                                <Link
                                                    href={EmployeeController.show.url(emp.id)}
                                                    className="hover:underline font-semibold text-primary"
                                                >
                                                    {emp.name ?? `Employee #${emp.id}`}
                                                </Link>
                                            </td>
                                            <td className="px-6 py-3 text-muted-foreground">
                                                {emp.designation}
                                            </td>
                                            <td className="px-6 py-3">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                        emp.status === 1
                                                             ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                                            : 'bg-muted text-muted-foreground'
                                                    }`}
                                                >
                                                    {emp.status === 1 ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-3 text-right">
                                                <Button variant="ghost" size="sm" asChild>
                                                    <Link href={EmployeeController.show.url(emp.id)}>
                                                        View
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

DepartmentsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Departments', href: DepartmentController.index.url() },
        { title: 'Department Details', href: '#' },
    ],
};
