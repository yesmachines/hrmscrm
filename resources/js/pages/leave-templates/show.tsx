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
    companyLogoUrl,
}: {
    template: Template;
    companyLogoUrl: string;
}) {
    const handoverPreviewRows = [
        '<tr><td style="width: 8%; border: 1px solid #222; padding: 6px; text-align: center;">1</td><td style="border: 1px solid #222; padding: 6px;"><span style="color: red;">{{handover_description}}</span></td></tr>',
        ...Array.from(
            { length: 7 },
            (_, index) =>
                `<tr><td style="width: 8%; border: 1px solid #222; padding: 6px; text-align: center;">${index + 2}</td><td style="border: 1px solid #222; padding: 6px;">&nbsp;</td></tr>`,
        ),
    ].join('');
    const previewHtml = template.template_code
        .replaceAll('{{company_logo_url}}', companyLogoUrl)
        .replace('{{handover_description}}', handoverPreviewRows)
        .replaceAll('{{travel_yes_checkbox}}', '&#9744;')
        .replaceAll('{{travel_no_checkbox}}', '&#9744;')
        .replaceAll('{{ticket_checkbox}}', '&#9744;')
        .replaceAll('{{no_ticket_checkbox}}', '&#9744;');

    return (
        <>
            <Head title={template.template_name} />
            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
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

                <section className="space-y-4 rounded-2xl border border-border bg-white p-5 shadow-sm">
                    <div>
                        <h3 className="text-sm font-semibold tracking-tight">
                            Template Preview
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            Live rendering of the HTML layout with its
                            placeholders in position.
                        </p>
                    </div>
                    <div
                        className="min-h-[250px] rounded-xl border border-slate-200 bg-slate-50/50 p-6"
                        dangerouslySetInnerHTML={{
                            __html: previewHtml,
                        }}
                    />
                </section>

                <section className="space-y-4 rounded-2xl border border-border bg-white p-5 shadow-sm">
                    <h3 className="text-sm font-semibold tracking-tight">
                        HTML Source Code
                    </h3>
                    <pre className="max-h-[350px] overflow-x-auto rounded-xl bg-slate-950 p-4 font-mono text-xs text-slate-100">
                        <code>{template.template_code}</code>
                    </pre>
                </section>
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
