import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import LeaveTemplateController from '@/actions/App/Http/Controllers/Leave/LeaveTemplateController';
import Heading from '@/components/heading';
import RowActionsMenu from '@/components/row-actions-menu';
import StatusToggle from '@/components/status-toggle';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type TemplateRow = {
    id: number;
    template_name: string;
    template_code: string;
    status: number;
    leave_type: { id: number; name: string; code: string } | null;
    organisation: {
        id: number;
        name: string;
        short_name: string | null;
    } | null;
};

type PaginatedTemplates = {
    data: TemplateRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function LeaveTemplatesIndex({
    templates,
}: {
    templates: PaginatedTemplates;
}) {
    return (
        <>
            <Head title="Leave templates" />

            <div className="mx-auto flex w-full max-w-full flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Leave templates"
                        description="Manage the HTML templates used to generate approved leave PDFs."
                    />
                    <Button asChild>
                        <Link href={LeaveTemplateController.create.url()}>
                            <Plus className="size-4" />
                            Add template
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">
                                    Leave type
                                </th>
                                <th className="hidden px-4 py-3 font-medium lg:table-cell">
                                    Organisation
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
                            {templates.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No leave templates found.{' '}
                                        <Link
                                            href={LeaveTemplateController.create.url()}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            Create one
                                        </Link>
                                    </td>
                                </tr>
                            ) : (
                                templates.data.map((template) => (
                                    <tr
                                        key={template.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            <Link
                                                href={LeaveTemplateController.show.url(
                                                    template.id,
                                                )}
                                                className="font-semibold hover:text-primary hover:underline"
                                            >
                                                {template.template_name}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3">
                                            {template.leave_type?.name ?? '—'}
                                            {template.leave_type?.code
                                                ? ` (${template.leave_type.code})`
                                                : ''}
                                        </td>
                                        <td className="hidden px-4 py-3 lg:table-cell">
                                            {template.organisation
                                                ?.short_name ||
                                                template.organisation?.name ||
                                                'All organisations'}
                                        </td>
                                        <td className="hidden px-4 py-3 sm:table-cell">
                                            <StatusToggle
                                                status={template.status}
                                                url={LeaveTemplateController.updateStatus.url(
                                                    template.id,
                                                )}
                                                label={`${template.template_name} status`}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <RowActionsMenu
                                                viewHref={LeaveTemplateController.show.url(
                                                    template.id,
                                                )}
                                                editHref={LeaveTemplateController.edit.url(
                                                    template.id,
                                                )}
                                                destroyForm={LeaveTemplateController.destroy.form(
                                                    template.id,
                                                )}
                                                deleteTitle="Delete leave template?"
                                                deleteDescription={`Delete ${template.template_name}? Approved leave PDFs already generated from it will remain available.`}
                                                deleteConfirmLabel="Delete template"
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {templates.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {templates.links.map((link, index) =>
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

LeaveTemplatesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave templates', href: LeaveTemplateController.index.url() },
    ],
};
