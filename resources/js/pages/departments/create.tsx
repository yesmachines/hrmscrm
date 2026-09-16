import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import DepartmentController from '@/actions/App/Http/Controllers/Organisation/DepartmentController';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import DepartmentFormFields from './form-fields';

export default function DepartmentsCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        code: '',
        status: 1,
    });

    function handleSubmit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        post(DepartmentController.store.url());
    }

    return (
        <>
            <Head title="Create Department" />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">
                            Create Department
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Add a new department in HRMS for employee placement and reporting hierarchy
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={DepartmentController.index.url()}>
                            <ArrowLeft className="mr-1.5 h-4 w-4" />
                            Back to Departments
                        </Link>
                    </Button>
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
                            {processing ? 'Creating...' : 'Create Department'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

DepartmentsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Departments', href: DepartmentController.index.url() },
        { title: 'Create Department', href: DepartmentController.create.url() },
    ],
};
