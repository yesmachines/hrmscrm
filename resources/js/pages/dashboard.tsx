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
    return (
        <>
            <Head title="Dashboard" />
            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-10 p-6 md:p-10">
                <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-4 mt-4">
                    {modules.map((module) => (
                        <div
                            key={module.title}
                            className="group relative flex flex-col rounded-[1.25rem] border border-border/50 bg-card/80 backdrop-blur-sm p-6 shadow-sm transition-all duration-300 hover:-translate-y-1.5 hover:border-primary/30 hover:bg-card hover:shadow-xl hover:shadow-primary/5"
                        >
                            <div className="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary transition-colors duration-300 group-hover:bg-primary/15 group-hover:text-primary">
                                <module.icon
                                    className="size-6 transition-transform duration-300 group-hover:scale-110"
                                    strokeWidth={2}
                                />
                            </div>
                            <h2 className="mt-6 text-lg font-semibold tracking-tight text-foreground transition-colors group-hover:text-primary">
                                {module.title}
                            </h2>
                            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                                {module.description}
                            </p>
                        </div>
                    ))}
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
