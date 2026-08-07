import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import Heading from '@/components/heading';
import RowActionsMenu from '@/components/row-actions-menu';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type EmployeeRow = {
    id: number;
    emp_num: string;
    designation: string;
    division: string;
    phone: string | null;
    employment_status: string | null;
    status: number;
    user: { id: number; name: string; email: string } | null;
    department: { id: number; name: string } | null;
    profile: { id: number; nationality: string | null } | null;
};

type PaginatedEmployees = {
    data: EmployeeRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function EmployeesIndex({
    employees,
}: {
    employees: PaginatedEmployees;
}) {
    return (
        <>
            <Head title="Employees" />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Employees"
                        description="Manage employees and their profiles"
                    />
                    <Button asChild>
                        <Link href={EmployeeController.create.url()} prefetch>
                            <Plus className="size-4" />
                            Add employee
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Emp #</th>
                                <th className="hidden px-4 py-3 font-medium md:table-cell">
                                    Designation
                                </th>
                                <th className="hidden px-4 py-3 font-medium lg:table-cell">
                                    Division
                                </th>
                                <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {employees.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No employees found.{' '}
                                        <Link
                                            href={EmployeeController.create.url()}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            Create one
                                        </Link>
                                    </td>
                                </tr>
                            ) : (
                                employees.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {row.user?.name ?? '—'}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {row.user?.email}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">{row.emp_num}</td>
                                        <td className="hidden px-4 py-3 md:table-cell">
                                            {row.designation}
                                        </td>
                                        <td className="hidden px-4 py-3 lg:table-cell">
                                            {row.division}
                                        </td>
                                        <td className="hidden px-4 py-3 sm:table-cell">
                                            <span
                                                className={
                                                    row.status === 1
                                                        ? 'text-primary'
                                                        : 'text-muted-foreground'
                                                }
                                            >
                                                {row.status === 1
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <RowActionsMenu
                                                viewHref={EmployeeController.show.url(
                                                    row.id,
                                                )}
                                                editHref={EmployeeController.edit.url(
                                                    row.id,
                                                )}
                                                destroyForm={EmployeeController.destroy.form(
                                                    row.id,
                                                )}
                                                deleteTitle="Delete employee?"
                                                deleteDescription={`This will permanently delete ${row.user?.name ?? 'this employee'}. This cannot be undone.`}
                                                deleteConfirmLabel="Delete employee"
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {employees.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {employees.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={`rounded-md border px-3 py-1.5 text-sm ${
                                        link.active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-white text-foreground hover:bg-muted'
                                    }`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : (
                                <span
                                    key={index}
                                    className="rounded-md border border-border px-3 py-1.5 text-sm text-muted-foreground opacity-50"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

EmployeesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employees', href: EmployeeController.index.url() },
    ],
};
