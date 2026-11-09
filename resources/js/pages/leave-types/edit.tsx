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
    requires_approval: number;
    gender: string | null;
    religion: string | null;
    status: number;
};

type Option = {
    value: string;
    label: string;
};

export default function LeaveTypesEdit({
    leaveType,
    genderOptions,
    religionOptions,
}: {
    leaveType: LeaveType;
    genderOptions: Option[];
    religionOptions: Option[];
}) {
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

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
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
                                genderOptions={genderOptions}
                                religionOptions={religionOptions}
                            />

                            <div className="flex items-center justify-end gap-3">
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
