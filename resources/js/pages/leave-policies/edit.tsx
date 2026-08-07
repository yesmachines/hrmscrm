import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import LeavePolicyController from '@/actions/App/Http/Controllers/LeavePolicyController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import LeavePolicyFormFields from './form-fields';

type Option = {
    id: number;
    name: string;
};

type LeavePolicy = {
    id: number;
    leave_type_id: number;
    organisation_id: number;
    full_pay_days: number | null;
    half_pay_days: number | null;
    no_pay_days: number | null;
    requires_document_after_days: number | null;
    requires_weekend_document: number;
    allocation_days: number | null;
    carry_forward: number;
    encashment: number;
    remarks: string | null;
    requires_attachment: number;
    probation_applicable: number;
    minimum_service_months: number | null;
    leave_type: { id: number; name: string; code: string } | null;
    organisation: { id: number; name: string } | null;
};

export default function LeavePoliciesEdit({
    leavePolicy,
    leaveTypes,
    organisations,
}: {
    leavePolicy: LeavePolicy;
    leaveTypes: Option[];
    organisations: Option[];
}) {
    const title = leavePolicy.leave_type
        ? `${leavePolicy.leave_type.name} — ${leavePolicy.organisation?.name ?? 'Policy'}`
        : 'Leave policy';

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Leave policies',
                href: LeavePolicyController.index.url(),
            },
            {
                title,
                href: LeavePolicyController.show.url(leavePolicy.id),
            },
            {
                title: 'Edit',
                href: LeavePolicyController.edit.url(leavePolicy.id),
            },
        ],
    });

    return (
        <>
            <Head title={`Edit ${title}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Edit leave policy"
                    description="Update leave policy details"
                />

                <Form
                    {...LeavePolicyController.update.form(leavePolicy.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <LeavePolicyFormFields
                                errors={errors}
                                defaults={leavePolicy}
                                leaveTypes={leaveTypes}
                                organisations={organisations}
                            />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Update leave policy
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={LeavePolicyController.show.url(
                                            leavePolicy.id,
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
