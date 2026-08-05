import { Head, usePage } from '@inertiajs/react';
import { dashboard } from '@/routes';

export default function Dashboard() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <div className="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border md:p-8">
                    <p className="text-sm font-medium text-muted-foreground">
                        {name}
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                        Welcome{auth.user?.name ? `, ${auth.user.name}` : ''}
                    </h1>
                    <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                        Your human resource workspace for organisations,
                        employees, documents, leave, and ideas.
                    </p>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
