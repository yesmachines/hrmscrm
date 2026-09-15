import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, FolderTree } from 'lucide-react';
import { useState } from 'react';
import DepartmentController from '@/actions/App/Http/Controllers/Organisation/DepartmentController';
import RowActionsMenu from '@/components/row-actions-menu';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

type DepartmentRow = {
    id: number;
    name: string;
    code: string | null;
    status: number;
    created_at?: string | null;
};

type PaginatedDepartments = {
    data: DepartmentRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function DepartmentsIndex({
    departments,
}: {
    departments?: PaginatedDepartments;
}) {
    const searchParams = new URLSearchParams(
        typeof window !== 'undefined' ? window.location.search : '',
    );
    const [search, setSearch] = useState(searchParams.get('search') || '');
    const departmentList = departments?.data ?? [];
    const departmentLinks = departments?.links ?? [];

    return (
        <>
            <Head title="Departments" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                            <FolderTree className="h-6 w-6 text-primary" />
                            Departments
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage company departments and reporting structures in HRMS
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.get(
                                    DepartmentController.index.url(),
                                    { search },
                                    { preserveState: true },
                                );
                            }}
                            className="relative w-full sm:w-64"
                        >
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Search departments..."
                                className="bg-background pl-9"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </form>

                        <Button asChild>
                            <Link href={DepartmentController.create.url()}>
                                <Plus className="mr-1.5 h-4 w-4" />
                                Add Department
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="rounded-2xl border border-border/80 bg-card shadow-xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border/70 bg-muted/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-3.5">Name</th>
                                    <th className="px-6 py-3.5">Code</th>
                                    <th className="px-6 py-3.5">Status</th>
                                    <th className="px-6 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y border-border/60">
                                {departmentList.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="px-6 py-12 text-center text-sm text-muted-foreground"
                                        >
                                            No departments found. Click &quot;Add Department&quot; to create your first HRMS department.
                                        </td>
                                    </tr>
                                ) : (
                                    departmentList.map((dept) => (
                                        <tr
                                            key={dept.id}
                                            className="transition-colors hover:bg-muted/30"
                                        >
                                            <td className="px-6 py-4 font-medium text-foreground">
                                                <Link
                                                    href={DepartmentController.show.url(dept.id)}
                                                    className="hover:underline font-semibold text-primary"
                                                >
                                                    {dept.name}
                                                </Link>
                                            </td>
                                            <td className="px-6 py-4 font-mono text-xs text-muted-foreground">
                                                {dept.code ? (
                                                    <span className="rounded bg-muted px-2 py-0.5 text-foreground font-medium">
                                                        {dept.code}
                                                    </span>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                        dept.status === 1
                                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                                            : 'bg-muted text-muted-foreground'
                                                    }`}
                                                >
                                                    {dept.status === 1 ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <RowActionsMenu
                                                    viewHref={DepartmentController.show.url(dept.id)}
                                                    editHref={DepartmentController.edit.url(dept.id)}
                                                    destroyForm={DepartmentController.destroy.form(dept.id)}
                                                    deleteTitle="Delete Department?"
                                                    deleteDescription={`Are you sure you want to delete ${dept.name}?`}
                                                    deleteConfirmLabel="Delete Department"
                                                />
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {departmentLinks.length > 3 && (
                        <div className="flex items-center justify-between border-t border-border/70 px-6 py-3.5">
                            <div className="flex items-center gap-1">
                                {departmentLinks.map((link, index) => (
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
        </>
    );
}

DepartmentsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Departments', href: DepartmentController.index.url() },
    ],
};

