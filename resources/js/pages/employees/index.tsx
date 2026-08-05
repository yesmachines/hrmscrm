import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import {
    index as employeesIndex,
    create as employeesCreate,
    show as employeesShow,
    edit as employeesEdit,
} from '@/actions/App/Http/Controllers/EmployeeProfileController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type EmployeeRow = {
    id: number;
    personal_email: string | null;
    personal_mobile: string | null;
    nationality: string | null;
    employee: {
        id: number;
        name: string;
        email: string;
    } | null;
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
                        description="Manage employee profile records"
                    />
                    <Button asChild>
                        <Link href={employeesCreate.url()} prefetch>
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
                                <th className="hidden px-4 py-3 font-medium md:table-cell">
                                    Email
                                </th>
                                <th className="hidden px-4 py-3 font-medium lg:table-cell">
                                    Mobile
                                </th>
                                <th className="hidden px-4 py-3 font-medium sm:table-cell">
                                    Nationality
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
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No employee profiles yet.{' '}
                                        <Link
                                            href={employeesCreate.url()}
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
                                        <td className="px-4 py-3 font-medium">
                                            {row.employee?.name ?? '—'}
                                        </td>
                                        <td className="hidden px-4 py-3 text-muted-foreground md:table-cell">
                                            {row.personal_email ??
                                                row.employee?.email ??
                                                '—'}
                                        </td>
                                        <td className="hidden px-4 py-3 text-muted-foreground lg:table-cell">
                                            {row.personal_mobile ?? '—'}
                                        </td>
                                        <td className="hidden px-4 py-3 text-muted-foreground sm:table-cell">
                                            {row.nationality ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={employeesShow.url(
                                                            row.id,
                                                        )}
                                                        prefetch
                                                    >
                                                        View
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={employeesEdit.url(
                                                            row.id,
                                                        )}
                                                        prefetch
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                            </div>
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
        { title: 'Employees', href: employeesIndex.url() },
    ],
};
