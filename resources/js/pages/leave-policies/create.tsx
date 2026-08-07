import { Form, Head, Link } from '@inertiajs/react';
import LeavePolicyController from '@/actions/App/Http/Controllers/Leave/LeavePolicyController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import LeavePolicyFormFields from './form-fields';

type Option = {
    id: number;
    name: string;
};

export default function LeavePoliciesCreate({
    leaveTypes,
    organisations,
}: {
    leaveTypes: Option[];
    organisations: Option[];
}) {
    return (
        <>
            <Head title="Add leave policy" />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Add leave policy"
                    description="Create a leave policy for an organisation"
                />

                <Form
                    {...LeavePolicyController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <LeavePolicyFormFields
                                errors={errors}
                                leaveTypes={leaveTypes}
                                organisations={organisations}
                            />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Save leave policy
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={LeavePolicyController.index.url()}
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

LeavePoliciesCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Leave policies',
            href: LeavePolicyController.index.url(),
        },
        { title: 'Add', href: LeavePolicyController.create.url() },
    ],
};
