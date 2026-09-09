import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FestivalController from '@/actions/App/Http/Controllers/Leave/FestivalController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import FestivalFormFields from './form-fields';

type FestivalData = {
    id: number;
    name: string;
    type: string;
    shortcode: string;
    is_active: number;
    start_date: string | null;
    end_date: string | null;
    countries: number[];
};

export default function FestivalEdit({
    festival,
    countries,
}: {
    festival: FestivalData;
    countries: { id: number; name: string }[];
}) {
    const { data, setData, put, processing, errors } = useForm({
        name: festival.name,
        type: festival.type,
        shortcode: festival.shortcode,
        start_date: festival.start_date ?? '',
        end_date: festival.end_date ?? '',
        is_active: String(festival.is_active),
        countries: festival.countries ?? [],
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        put(FestivalController.update.url(festival.id));
    }

    return (
        <>
            <Head title={`Edit ${festival.name}`} />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={`Edit ${festival.name}`}
                        description="Update festival or holiday details"
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
                            Save changes
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

FestivalEdit.layout = (page: React.ReactElement) => {
    return {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Festivals & Holidays', href: FestivalController.index.url() },
            { title: 'Edit', href: '#' },
        ],
    };
};
