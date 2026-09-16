import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import DepartmentController from '@/actions/App/Http/Controllers/Organisation/DepartmentController';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import DepartmentFormFields from './form-fields';

type Department = {
    id: number;
    name: string;
    code: string | null;
    status: number;
};

export default function DepartmentsEdit({ department }: { department: Department }) {
    const { data, setData, put, processing, errors } = useForm({
        name: department.name ?? '',
        code: department.code ?? '',
        status: department.status ?? 1,
    });

    function handleSubmit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        put(DepartmentController.update.url(department.id));
    }

    return (
        <>
            <Head title={`Edit Department: ${department.name}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">
                            Edit Department: {department.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Update department name, code, or status
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={DepartmentController.show.url(department.id)}>
                                View Details
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={DepartmentController.index.url()}>
                                <ArrowLeft className="mr-1.5 h-4 w-4" />
                                Back
                            </Link>
                        </Button>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <DepartmentFormFields
                        data={data}
                        setData={setData}
                        errors={errors}
                    />

                    <div className="flex items-center justify-end gap-3">
                        <Button variant="outline" asChild>
                            <Link href={DepartmentController.index.url()}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

DepartmentsEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Departments', href: DepartmentController.index.url() },
        { title: 'Edit Department', href: '#' },
    ],
};
