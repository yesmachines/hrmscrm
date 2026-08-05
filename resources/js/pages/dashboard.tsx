import { Head, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    FileText,
    Lightbulb,
    Users,
} from 'lucide-react';
import { dashboard } from '@/routes';

const modules = [
    {
        title: 'People',
        description: 'Organisations, profiles, and structure',
        icon: Users,
    },
    {
        title: 'Leave',
        description: 'Requests, approvals, and balances',
        icon: CalendarDays,
    },
    {
        title: 'Documents',
        description: 'Employee files and reminders',
        icon: FileText,
    },
    {
        title: 'Ideas',
        description: 'Capture and track workplace ideas',
        icon: Lightbulb,
    },
] as const;

export default function Dashboard() {
    const { auth } = usePage().props;
    const firstName = auth.user?.name?.split(' ')[0] ?? '';

    return (
        <>
            <Head title="Dashboard" />
            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-10 p-6 md:p-8">
                <div className="space-y-3">
                    <p className="text-sm font-medium tracking-[0.2em] text-primary uppercase">
                        HRMS
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight text-foreground md:text-4xl">
                        Good {greeting()}
                        {firstName ? `, ${firstName}` : ''}
                    </h1>
                    <p className="max-w-xl text-base text-muted-foreground">
                        Empowering people. Simplifying HR. Driving{' '}
                        <span className="font-medium text-primary">
                            performance
                        </span>
                        .
                    </p>
                </div>

                <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    {modules.map((module) => (
                        <div
                            key={module.title}
                            className="rounded-2xl border border-border bg-white p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                        >
                            <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <module.icon
                                    className="size-5"
                                    strokeWidth={1.75}
                                />
                            </div>
                            <h2 className="mt-5 text-base font-semibold tracking-tight">
                                {module.title}
                            </h2>
                            <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                                {module.description}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

function greeting(): string {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'morning';
    }

    if (hour < 17) {
        return 'afternoon';
    }

    return 'evening';
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
