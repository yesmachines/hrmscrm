import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Pencil } from 'lucide-react';
import LeaveTemplateController from '@/actions/App/Http/Controllers/Leave/LeaveTemplateController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type Template = {
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

export default function LeaveTemplatesShow({
    template,
}: {
    template: Template;
}) {
    return (
        <>
            <Head title={template.template_name} />
            <div className="mx-auto flex w-full max-w-full flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" asChild>
                            <Link href={LeaveTemplateController.index.url()}>
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <Heading
                            title={template.template_name}
                            description="Leave PDF template preview"
                        />
                    </div>
                    <Button asChild>
                        <Link
                            href={LeaveTemplateController.edit.url(template.id)}
                        >
                            <Pencil className="mr-2 size-4" />
                            Edit template
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 rounded-xl border border-border bg-white p-5 sm:grid-cols-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Leave type
                        </p>
                        <p className="font-medium">
                            {template.leave_type?.name ?? '—'}
                        </p>
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Organisation
                        </p>
                        <p className="font-medium">
                            {template.organisation?.short_name ||
                                template.organisation?.name ||
                                'All organisations'}
                        </p>
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Status</p>
                        <p className="font-medium">
                            {template.status === 1 ? 'Active' : 'Inactive'}
                        </p>
                    </div>
                </div>

                <iframe
                    title={`${template.template_name} HTML preview`}
                    sandbox=""
                    srcDoc={template.template_code}
                    className="h-[760px] w-full rounded-xl border border-border bg-white"
                />
            </div>
        </>
    );
}

LeaveTemplatesShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave templates', href: LeaveTemplateController.index.url() },
    ],
};
