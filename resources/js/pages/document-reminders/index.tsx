import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    Calendar,
    CheckCircle2,
    Eye,
    FileText,
    Filter,
    ShieldAlert,
    User,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type ReminderItem = {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_email: string;
    emp_num: string;
    document_name: string;
    document_number: string | null;
    expiry_date: string;
    days_remaining: number;
    urgency: 'expired' | 'critical' | 'warning' | 'normal';
    status: string;
};

type PaginatedReminders = {
    data: ReminderItem[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

type Props = {
    reminders: PaginatedReminders;
    filters: {
        urgency?: string;
    };
};

export default function DocumentRemindersIndex({ reminders, filters }: Props) {
    const handleUrgencyFilter = (urgency: string) => {
        router.get('/document-reminders', urgency ? { urgency } : {}, { preserveState: true });
    };

    const getUrgencyBadge = (urgency: ReminderItem['urgency'], days: number) => {
        if (urgency === 'expired') {
            return (
                <Badge variant="outline" className="bg-rose-50 text-rose-700 border-rose-200 gap-1 font-semibold">
                    <ShieldAlert className="size-3.5 text-rose-600" />
                    Expired ({Math.abs(days)} days ago)
                </Badge>
            );
        }
        if (urgency === 'critical') {
            return (
                <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 gap-1 font-semibold">
                    <AlertTriangle className="size-3.5 text-amber-600" />
                    Expires in {days} days (&lt; 1 month)
                </Badge>
            );
        }
        return (
            <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 gap-1 font-medium">
                <AlertCircle className="size-3.5 text-blue-600" />
                Expires in {days} days (&lt; 3 months)
            </Badge>
        );
    };

    return (
        <>
            <Head title="3-Month Expiry Reminders" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <Heading
                        title="3-Month Document Expiry Monitor"
                        description="Track Passport, Visa, Emirates ID, Labour Card, and Driving Licence renewals 90 days before expiry"
                    />
                </div>

                {/* Filter Tabs */}
                <div className="flex flex-wrap items-center gap-2 p-1.5 bg-neutral-100/80 rounded-2xl w-fit border border-border">
                    <Button
                        variant={!filters.urgency ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => handleUrgencyFilter('')}
                        className="rounded-xl text-xs font-semibold"
                    >
                        All Active Reminders
                    </Button>
                    <Button
                        variant={filters.urgency === 'critical' ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => handleUrgencyFilter('critical')}
                        className="rounded-xl text-xs font-semibold text-amber-700"
                    >
                        Urgent (&lt; 30 Days)
                    </Button>
                    <Button
                        variant={filters.urgency === 'warning' ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => handleUrgencyFilter('warning')}
                        className="rounded-xl text-xs font-semibold text-blue-700"
                    >
                        Upcoming (&lt; 90 Days)
                    </Button>
                    <Button
                        variant={filters.urgency === 'expired' ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => handleUrgencyFilter('expired')}
                        className="rounded-xl text-xs font-semibold text-rose-700"
                    >
                        Already Expired
                    </Button>
                </div>

                {/* Reminders Table */}
                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-5 py-3.5 font-semibold text-xs tracking-wider uppercase">Employee</th>
                                <th className="px-5 py-3.5 font-semibold text-xs tracking-wider uppercase">Document</th>
                                <th className="hidden px-5 py-3.5 font-semibold text-xs tracking-wider uppercase md:table-cell">Doc #</th>
                                <th className="px-5 py-3.5 font-semibold text-xs tracking-wider uppercase">Expiry Date</th>
                                <th className="px-5 py-3.5 font-semibold text-xs tracking-wider uppercase">Alert Urgency</th>
                                <th className="px-5 py-3.5 text-right font-semibold text-xs tracking-wider uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {reminders.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-5 py-12 text-center text-muted-foreground">
                                        <CheckCircle2 className="mx-auto size-10 text-emerald-500 mb-3" />
                                        <p className="text-base font-semibold text-foreground">No Upcoming Expiries</p>
                                        <p className="text-sm mt-1">All employee documents are up to date within the 90-day window.</p>
                                    </td>
                                </tr>
                            ) : (
                                reminders.data.map((rem) => (
                                    <tr key={rem.id} className="hover:bg-muted/20 transition-colors">
                                        <td className="px-5 py-4">
                                            <div className="font-semibold text-foreground">{rem.employee_name}</div>
                                            <div className="text-xs text-muted-foreground">{rem.emp_num || rem.employee_email}</div>
                                        </td>
                                        <td className="px-5 py-4 font-medium text-foreground">
                                            {rem.document_name}
                                        </td>
                                        <td className="hidden px-5 py-4 text-muted-foreground md:table-cell">
                                            {rem.document_number || '—'}
                                        </td>
                                        <td className="px-5 py-4 font-semibold text-foreground">
                                            {rem.expiry_date}
                                        </td>
                                        <td className="px-5 py-4">
                                            {getUrgencyBadge(rem.urgency, rem.days_remaining)}
                                        </td>
                                        <td className="px-5 py-4 text-right">
                                            <Button asChild variant="outline" size="sm" className="gap-1.5 rounded-lg">
                                                <Link href={`/employee-documents/${rem.id}`}>
                                                    <Eye className="size-3.5" />
                                                    View Document
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

DocumentRemindersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Expiry Reminders',
            href: '/document-reminders',
        },
    ],
};
