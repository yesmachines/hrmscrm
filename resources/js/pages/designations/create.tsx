import { Form, Head, Link } from '@inertiajs/react';
import DesignationController from '@/actions/App/Http/Controllers/Designation/DesignationController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import DesignationFormFields from './form-fields';

type DepartmentOption = {
    id: number;
    name: string;
    code: string;
};

export default function DesignationsCreate({
    departments,
}: {
    departments: DepartmentOption[];
}) {
    return (
        <>
            <Head title="Add designation" />

            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">
                            Add Designation
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Create a new designation position in HRMS
                        </p>
                    </div>
                    <div className="flex items-center gap-2.5">
                        <Button variant="outline" asChild>
                            <Link href={DesignationController.index.url()}>
                                Back to Designations
                            </Link>
                        </Button>
                    </div>
                </div>

                <Form
                    {...DesignationController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <DesignationFormFields
                                errors={errors}
                                departments={departments}
                            />

                            <div className="flex items-center justify-end gap-3 rounded-xl border border-border/80 bg-background/95 p-4 shadow-sm">
                                <Button variant="outline" asChild>
                                    <Link href={DesignationController.index.url()}>
                                        Cancel
                                    </Link>
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="min-w-36"
                                >
                                    {processing && <Spinner />}
                                    Save designation
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

DesignationsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Designations',
            href: DesignationController.index.url(),
        },
        { title: 'Add', href: DesignationController.create.url() },
    ],
};
