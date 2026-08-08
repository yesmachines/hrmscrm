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

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
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

                            <div className="flex items-center gap-3">
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
