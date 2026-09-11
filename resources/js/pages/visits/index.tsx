import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Building2,
    Calendar,
    Check,
    Clock,
    Eye,
    MapPin,
    Search,
    User,
    Users,
    X,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

interface VisitorDetail {
    name: string;
    designation?: string;
}

interface VisitRow {
    id: number;
    total_visitors: number;
    visitor_details: VisitorDetail[];
    company: string;
    contact_no: string;
    email: string;
    purpose: string;
    location: string;
    expected_start_date: string;
    expected_end_date: string | null;
    status: 'pending' | 'approved' | 'rejected' | 'completed' | 'cancelled';
    created_at: string;
    creator?: {
        id: number;
        emp_num: string;
        employee_code: string | null;
        designation: string | null;
        user?: {
            id: number;
            name: string;
        } | null;
    } | null;
    latest_approval?: {
        instructions: string | null;
        rejected_reason: string | null;
        approver?: {
            name: string;
        } | null;
    } | null;
}

interface PaginatedVisits {
    data: VisitRow[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Props {
    visits: PaginatedVisits;
    filters: {
        status: string;
        search: string;
    };
}

export default function VisitsIndex({ visits, filters }: Props) {
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [searchTerm, setSearchTerm] = useState(filters.search || '');

    const handleFilterChange = (status: string) => {
        setStatusFilter(status);
        router.get(
            '/visits',
            { status, search: searchTerm },
            { preserveState: true, replace: true }
        );
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/visits',
            { status: statusFilter, search: searchTerm },
            { preserveState: true, replace: true }
        );
    };

    const getStatusBadge = (status: VisitRow['status']) => {
        switch (status) {
            case 'approved':
                return <span className="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Approved</span>;
            case 'rejected':
                return <span className="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800">Rejected</span>;
            case 'completed':
                return <span className="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">Completed</span>;
            case 'cancelled':
                return <span className="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">Cancelled</span>;
            default:
                return <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Pending Review</span>;
        }
    };

    return (
        <>
            <Head title="Visits & Visitor Management" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Visits & Visitor Requests"
                        description="Review, approve, and track visitor requests for client meetings, supplier visits, and guests."
                    />
                </div>

                {/* Filters and Search Bar */}
                <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border bg-white p-4 shadow-sm">
                    <form onSubmit={handleSearch} className="flex flex-1 items-center gap-2 max-w-md">
                        <div className="relative w-full">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Search by company, location, visitor name..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit" variant="secondary" size="sm">
                            Search
                        </Button>
                    </form>

                    <div className="flex items-center gap-2">
                        <span className="text-xs font-medium text-muted-foreground">Status:</span>
                        <select
                            value={statusFilter}
                            onChange={(e) => handleFilterChange(e.target.value)}
                            className="h-9 rounded-md border border-input bg-background px-3 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                {/* Visits Table */}
                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Visitors & Company</th>
                                    <th className="px-4 py-3 font-medium">Host / Employee</th>
                                    <th className="px-4 py-3 font-medium">Location & Purpose</th>
                                    <th className="px-4 py-3 font-medium">Expected Date & Time</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="px-4 py-3 text-right font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {visits.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-12 text-center text-muted-foreground">
                                            No visit requests found.
                                        </td>
                                    </tr>
                                ) : (
                                    visits.data.map((visit) => {
                                        const primaryVisitor = visit.visitor_details?.[0]?.name ?? 'Visitor';
                                        const otherCount = (visit.visitor_details?.length ?? 1) - 1;

                                        return (
                                            <tr key={visit.id} className="border-b border-border hover:bg-muted/20 last:border-0 transition-colors">
                                                <td className="px-4 py-3.5">
                                                    <div>
                                                        <div className="font-semibold text-foreground flex items-center gap-1.5">
                                                            <Users className="size-3.5 text-primary shrink-0" />
                                                            <span>{primaryVisitor}</span>
                                                            {otherCount > 0 && (
                                                                <span className="text-xs font-normal text-muted-foreground">
                                                                    (+{otherCount} more)
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
                                                            <Building2 className="size-3 shrink-0" />
                                                            <span>{visit.company}</span>
                                                            <span className="text-muted-foreground/50">•</span>
                                                            <span>{visit.contact_no}</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3.5">
                                                    <div className="flex items-center gap-2">
                                                        <User className="size-3.5 text-muted-foreground" />
                                                        <div>
                                                            <div className="font-medium text-foreground">
                                                                {visit.creator?.user?.name ?? 'Employee'}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {visit.creator?.employee_code || visit.creator?.emp_num || ''}
                                                                {visit.creator?.designation ? ` (${visit.creator.designation})` : ''}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3.5 max-w-xs">
                                                    <div className="flex items-center gap-1.5 text-xs font-medium text-foreground">
                                                        <MapPin className="size-3.5 text-muted-foreground shrink-0" />
                                                        <span>{visit.location}</span>
                                                    </div>
                                                    <div className="text-xs text-muted-foreground line-clamp-1 mt-0.5">
                                                        {visit.purpose}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3.5 text-xs text-muted-foreground whitespace-nowrap">
                                                    <div className="flex items-center gap-1.5 text-foreground font-medium">
                                                        <Calendar className="size-3.5 text-muted-foreground shrink-0" />
                                                        <span>
                                                            {new Date(visit.expected_start_date).toLocaleDateString([], {
                                                                month: 'short',
                                                                day: 'numeric',
                                                                year: 'numeric',
                                                            })}
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center gap-1.5 text-muted-foreground mt-0.5">
                                                        <Clock className="size-3 shrink-0" />
                                                        <span>
                                                            {new Date(visit.expected_start_date).toLocaleTimeString([], {
                                                                hour: '2-digit',
                                                                minute: '2-digit',
                                                            })}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3.5 whitespace-nowrap">
                                                    {getStatusBadge(visit.status)}
                                                </td>
                                                <td className="px-4 py-3.5 text-right whitespace-nowrap">
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={`/visits/${visit.id}`}>
                                                            <Eye className="mr-1.5 size-3.5" />
                                                            Review
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pagination */}
                {visits.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {visits.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={`rounded-md border px-3 py-1.5 text-sm ${
                                        link.active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-white text-foreground hover:bg-muted'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={i}
                                    className="rounded-md border border-border px-3 py-1.5 text-sm text-muted-foreground opacity-50"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            )
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

VisitsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Visits' },
    ],
};
