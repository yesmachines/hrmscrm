import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import LeaveTypeController from '@/actions/App/Http/Controllers/Leave/LeaveTypeController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import LeaveTypeFormFields from './form-fields';

type LeaveType = {
    id: number;
    leave_name: string;
    code: string;
    is_paid: number;
    requires_attachment: number;
    requires_approval: number;
    max_days: number | null;
    annual_limit: number | null;
    gender: string | null;
    allow_once: number;
    allow_balance: number;
    status: number;
    requires_handover: number;
};

export default function LeaveTypesEdit({ leaveType }: { leaveType: LeaveType }) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Leave types',
                href: LeaveTypeController.index.url(),
            },
            {
                title: leaveType.leave_name,
                href: LeaveTypeController.show.url(leaveType.id),
            },
            {
                title: 'Edit',
                href: LeaveTypeController.edit.url(leaveType.id),
            },
        ],
    });

    return (
        <>
            <Head title={`Edit ${leaveType.leave_name}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Edit leave type"
                    description="Update leave type details"
                />

                <Form
                    {...LeaveTypeController.update.form(leaveType.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <LeaveTypeFormFields
                                errors={errors}
                                defaults={leaveType}
                            />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Update leave type
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={LeaveTypeController.show.url(
                                            leaveType.id,
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
