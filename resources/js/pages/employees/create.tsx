import { Form, Head, Link } from '@inertiajs/react';
import EmployeeProfileController from '@/actions/App/Http/Controllers/EmployeeProfileController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import EmployeeProfileFormFields from './form-fields';

type UserOption = {
    id: number;
    name: string;
    email: string;
};

export default function EmployeesCreate({ users }: { users: UserOption[] }) {
    return (
        <>
            <Head title="Add employee" />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <Heading
                    title="Add employee"
                    description="Create a new employee profile"
                />

                <Form
                    {...EmployeeProfileController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <EmployeeProfileFormFields
                                errors={errors}
                                users={users}
                            />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Save employee
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={EmployeeProfileController.index.url()}
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

EmployeesCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Employees',
            href: EmployeeProfileController.index.url(),
        },
        {
            title: 'Add',
            href: EmployeeProfileController.create.url(),
        },
    ],
};
