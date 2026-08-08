import { Form, Head, Link } from '@inertiajs/react';
import LeaveTypeController from '@/actions/App/Http/Controllers/Leave/LeaveTypeController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import LeaveTypeFormFields from './form-fields';

export default function LeaveTypesCreate() {
    return (
        <>
            <Head title="Add leave type" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Add leave type"
                    description="Create a new leave type"
                />

                <Form
                    {...LeaveTypeController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <LeaveTypeFormFields errors={errors} />

                            <div className="flex items-center justify-end gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Save leave type
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={LeaveTypeController.index.url()}
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

LeaveTypesCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Leave types',
            href: LeaveTypeController.index.url(),
        },
        { title: 'Add', href: LeaveTypeController.create.url() },
    ],
};
