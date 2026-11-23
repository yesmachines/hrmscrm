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

export default function LeaveTemplatesCreate({
    leaveTypes,
    organisations,
}: {
    leaveTypes: Option[];
    organisations: Option[];
}) {
    return (
        <>
            <Head title="Add leave template" />
            <div className="mx-auto flex w-full max-w-full flex-1 flex-col gap-6 p-6 md:p-8 2xl:max-w-[1600px]">
                <Heading
                    title="Add leave template"
                    description="Create a new leave template"
                />
                <Form
                    {...LeaveTemplateController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <LeaveTemplateFormFields
                                errors={errors}
                                leaveTypes={leaveTypes}
                                organisations={organisations}
                            />
                            <div className="flex items-center justify-end gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Save template
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={LeaveTemplateController.index.url()}
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

LeaveTemplatesCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave templates', href: LeaveTemplateController.index.url() },
        { title: 'Add', href: LeaveTemplateController.create.url() },
    ],
};
