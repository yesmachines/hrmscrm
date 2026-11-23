import { Form, Head, Link } from '@inertiajs/react';
import LeaveTemplateController from '@/actions/App/Http/Controllers/Leave/LeaveTemplateController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import LeaveTemplateFormFields from './form-fields';

type Option = {
    id: number;
    name: string;
    code?: string;
    short_name?: string | null;
};

type Template = {
    id: number;
    leave_type_id: number;
    organisation_id: number | null;
    template_name: string;
    template_code: string;
    status: number;
};

export default function LeaveTemplatesEdit({
    template,
    leaveTypes,
    organisations,
}: {
    template: Template;
    leaveTypes: Option[];
    organisations: Option[];
}) {
    return (
        <>
            <Head title={`Edit ${template.template_name}`} />
            <div className="mx-auto flex w-full max-w-full flex-1 flex-col gap-6 p-6 md:p-8 2xl:max-w-[1600px]">
                <Heading
                    title="Edit leave template"
                    description="Update template details"
                />
                <Form
                    {...LeaveTemplateController.update.form(template.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <LeaveTemplateFormFields
                                errors={errors}
                                leaveTypes={leaveTypes}
                                organisations={organisations}
                                defaults={template}
                            />
                            <div className="flex items-center justify-end gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Save changes
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={LeaveTemplateController.show.url(
                                            template.id,
                                        )}
                                    >
                                        Cancel
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

LeaveTemplatesEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave templates', href: LeaveTemplateController.index.url() },
        { title: 'Edit', href: '#' },
    ],
};
