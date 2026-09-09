import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FestivalController from '@/actions/App/Http/Controllers/Leave/FestivalController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import FestivalFormFields from './form-fields';

export default function FestivalCreate({
    countries,
}: {
    countries: { id: number; name: string }[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: 'festival',
        start_date: '',
        end_date: '',
        is_active: '1',
        countries: [] as number[],
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post(FestivalController.store.url());
    }

    return (
        <>
            <Head title="Create festival or holiday" />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Create festival or holiday"
                        description="Add a new festival or holiday to the system"
                    />
                    <Button variant="outline" asChild>
                        <Link href={FestivalController.index.url()} prefetch>
                            Cancel
                        </Link>
                    </Button>
                </div>

                <form
                    onSubmit={submit}
                    className="flex flex-col gap-6"
                    onChange={(e) => {
                        const target = e.target as HTMLInputElement | HTMLSelectElement;
                        if (target.name === 'countries') return;
                        setData(
                            target.name as keyof typeof data,
                            target.value,
                        );
                    }}
                >
                    <FestivalFormFields
                        errors={errors}
                        defaults={data}
                        countriesList={countries}
                        onCountriesChange={(newCountries) => setData('countries', newCountries)}
                    />

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            Create festival or holiday
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

FestivalCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Festivals & Holidays', href: FestivalController.index.url() },
        { title: 'Create', href: FestivalController.create.url() },
    ],
};
