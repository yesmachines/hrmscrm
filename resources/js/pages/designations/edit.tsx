import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
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

type Designation = {
    id: number;
    department_id: number;
    title: string;
    shortcode: string;
    status: number;
};

export default function DesignationsEdit({
    designation,
    departments,
}: {
    designation: Designation;
    departments: DepartmentOption[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Designations',
                href: DesignationController.index.url(),
            },
            {
                title: designation.title,
                href: DesignationController.show.url(designation.id),
            },
            {
                title: 'Edit',
                href: DesignationController.edit.url(designation.id),
            },
        ],
    });

    return (
        <>
            <Head title={`Edit ${designation.title}`} />

            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">
                            Edit Designation: {designation.title}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Update designation properties and department association
                        </p>
                    </div>
                    <div className="flex items-center gap-2.5">
                        <Button variant="outline" asChild>
                            <Link href={DesignationController.show.url(designation.id)}>
                                View Details
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={DesignationController.index.url()}>
                                Back to Designations
                            </Link>
                        </Button>
                    </div>
                </div>

                <Form
                    {...DesignationController.update.form(designation.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <DesignationFormFields
                                errors={errors}
                                departments={departments}
                                defaults={designation}
                            />

                            <div className="flex items-center justify-end gap-3 rounded-xl border border-border/80 bg-background/95 p-4 shadow-sm">
                                <Button variant="outline" asChild>
                                    <Link
                                        href={DesignationController.show.url(
                                            designation.id,
                                        )}
                                    >
                                        Cancel
                                    </Link>
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="min-w-36"
                                >
                                    {processing && <Spinner />}
                                    Update designation
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
