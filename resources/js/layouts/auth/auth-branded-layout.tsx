import { usePage } from '@inertiajs/react';
import {
    CalendarDays,
    FileText,
    Lightbulb,
    Users,
} from 'lucide-react';
import type { AuthLayoutProps } from '@/types';

const highlights = [
    {
        title: 'People',
        description: 'Profiles, roles & organisation structure',
        icon: Users,
    },
    {
        title: 'Leave',
        description: 'Requests, approvals & balances',
        icon: CalendarDays,
    },
    {
        title: 'Documents',
        description: 'Employee files & reminders',
        icon: FileText,
    },
    {
        title: 'Ideas',
        description: 'Capture & track workplace ideas',
        icon: Lightbulb,
    },
] as const;

export default function AuthBrandedLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;
    const year = new Date().getFullYear();

    return (
        <div className="relative flex min-h-svh overflow-hidden bg-[#001A33] text-neutral-900">
            <img
                src="/images/login-hero.png"
                alt=""
                className="absolute inset-0 size-full object-cover object-left"
            />
            <div className="absolute inset-0 bg-gradient-to-r from-[#001A33]/85 via-[#002B5E]/55 to-[#00F5D4]/25" />
            <div className="pointer-events-none absolute -top-20 -right-16 size-72 rounded-full bg-[#00F5D4]/20 blur-3xl" />
            <div className="pointer-events-none absolute -right-10 -bottom-24 size-80 rounded-full bg-[#00F5D4]/15 blur-3xl" />

            <div className="relative z-10 flex min-h-svh w-full flex-col">
                <div className="flex flex-1 flex-col gap-10 px-4 py-10 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:gap-12 lg:px-12 xl:px-16">
                    <div className="hidden max-w-xl text-white lg:block">
                        <p className="text-sm font-semibold tracking-[0.28em] text-[#00F5D4] uppercase">
                            {name} Platform
                        </p>
                        <h1 className="mt-4 text-4xl font-bold leading-tight tracking-tight xl:text-5xl">
                            Empowering people.
                            <br />
                            Simplifying HR.
                            <br />
                            Driving{' '}
                            <span className="text-[#00F5D4]">performance.</span>
                        </h1>
                        <p className="mt-5 max-w-md text-base leading-relaxed text-white/80">
                            One workspace for organisations, employees, leave,
                            documents, and workplace ideas — built for modern HR
                            teams.
                        </p>

                        <div className="mt-8 grid grid-cols-2 gap-3">
                            {highlights.map((item) => (
                                <div
                                    key={item.title}
                                    className="rounded-xl border border-white/15 bg-white/10 p-3.5 backdrop-blur-sm"
                                >
                                    <div className="flex items-center gap-2.5">
                                        <div className="flex size-8 items-center justify-center rounded-lg bg-[#00F5D4]/15 text-[#00F5D4]">
                                            <item.icon className="size-4" />
                                        </div>
                                        <p className="text-sm font-semibold">
                                            {item.title}
                                        </p>
                                    </div>
                                    <p className="mt-2 text-xs leading-snug text-white/70">
                                        {item.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="mx-auto w-full max-w-[420px] lg:mx-0 lg:shrink-0">
                        <div className="mb-6 text-center text-white lg:hidden">
                            <p className="text-xs font-semibold tracking-[0.25em] text-[#00F5D4] uppercase">
                                {name} Platform
                            </p>
                            <p className="mt-2 text-lg font-semibold">
                                People · Leave · Documents · Ideas
                            </p>
                        </div>

                        <div className="rounded-2xl bg-white p-7 shadow-[0_25px_60px_-15px_rgba(10,42,92,0.45)] sm:p-9">
                            <div className="mb-6 flex flex-col items-center text-center">
                                <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-[#00F5D4]/15 text-[#00B8A3]">
                                    <Users className="size-6" strokeWidth={1.75} />
                                </div>
                                <p className="text-xl font-bold tracking-tight text-[#001A33]">
                                    {name}
                                </p>
                            </div>

                            {(title || description) && (
                                <div className="mb-6 space-y-2 text-center">
                                    {title && (
                                        <h2 className="text-2xl font-semibold tracking-tight text-neutral-900">
                                            {title}
                                        </h2>
                                    )}
                                    {description && (
                                        <p className="text-sm text-neutral-500">
                                            {description}
                                        </p>
                                    )}
                                </div>
                            )}

                            {children}
                        </div>
                    </div>
                </div>

                <footer className="flex flex-col gap-2 px-6 py-4 text-xs text-white/85 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:px-12 xl:px-16">
                    <p>
                        © {year} {name}. All rights reserved.
                    </p>
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span>Privacy Policy</span>
                        <span className="text-white/50">|</span>
                        <span>Terms of Use</span>
                        <span className="text-white/50">|</span>
                        <span>Support</span>
                    </div>
                </footer>
            </div>
        </div>
    );
}
