import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock,
    Eye,
    FileText,
    Mail,
    Search,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';

type LetterRequestItem = {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_email: string;
    emp_num: string;
    document_name: string;
    document_code: string;
    purpose: string;
    details: string | null;
    to_address: string | null;
    visa_designation: string | null;
    status: 'draft' | 'submitted' | 'under_review' | 'approved' | 'rejected' | 'cancelled' | 'archived' | 'expired';
    applied_date: string;
    approved_date: string | null;
};

type PaginatedRequests = {
    data: LetterRequestItem[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

type Props = {
    requests: PaginatedRequests;
    filters: {
        status?: string;
        search?: string;
    };
};

export default function LetterRequestsIndex({ requests, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');

    const handleFilter = (updates: Record<string, string>) => {
        const query: Record<string, string> = {
            ...filters,
            search,
            status: status === 'all' ? '' : status,
            ...updates,
        };

        Object.keys(query).forEach((key) => {
            if (!query[key] || query[key] === 'all') delete query[key];
        });

        router.get('/letter-requests', query, { preserveState: true });
    };

    const getStatusBadge = (st: LetterRequestItem['status']) => {
        switch (st) {
            case 'approved':
                return (
                    <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 gap-1 font-medium">
                        <CheckCircle2 className="size-3.5 text-emerald-600" />
                        Approved
                    </Badge>
                );
            case 'submitted':
            case 'under_review':
                return (
                    <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 gap-1 font-medium">
                        <Clock className="size-3.5 text-amber-600" />
                        Pending Approval
                    </Badge>
                );
            case 'rejected':
                return (
                    <Badge variant="outline" className="bg-rose-50 text-rose-700 border-rose-200 gap-1 font-medium">
                        <XCircle className="size-3.5 text-rose-600" />
                        Rejected
                    </Badge>
                );
            default:
                return (
                    <Badge variant="outline" className="bg-slate-50 text-slate-700 border-slate-200 font-medium">
                        {st}
                    </Badge>
                );
        }
    };

    return (
        <>
            <Head title="Letter Requests" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <Heading
                        title="Letter of Requests (NOC / Salary Certificates)"
                        description="Review, process, and issue NOC, Salary Certificates, and Salary Transfer Letters"
                    />
                </div>

                {/* Filters */}
                <div className="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 rounded-2xl border border-border bg-white p-4 shadow-sm">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            handleFilter({ search });
                        }}
                        className="relative flex-1"
                    >
                        <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            type="text"
                            placeholder="Search by letter type, employee name, or purpose..."
                            className="pl-9 bg-neutral-50/50"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </form>

                    <div className="flex items-center gap-2">
                        <Select
                            value={status}
                            onValueChange={(val) => {
                                setStatus(val);
                                handleFilter({ status: val });
                            }}
                        >
                            <SelectTrigger className="w-[180px] bg-neutral-50/50">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem value="submitted">Pending Approval</SelectItem>
                                <SelectItem value="approved">Approved</SelectItem>
                                <SelectItem value="rejected">Rejected</SelectItem>
                            </SelectContent>
                        </Select>

                        {(search || status !== 'all') && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    setStatus('all');
                                    router.get('/letter-requests');
                                }}
                            >
                                Reset
                            </Button>
                        )}
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-5 py-3.5 font-semibold text-xs tracking-wider uppercase">Employee</th>
                                <th className="px-5 py-3.5 font-semibold text-xs tracking-wider uppercase">Letter Type</th>
                                <th className="hidden px-5 py-3.5 font-semibold text-xs tracking-wider uppercase md:table-cell">Purpose</th>
                                <th className="hidden px-5 py-3.5 font-semibold text-xs tracking-wider uppercase lg:table-cell">Dates</th>
                                <th className="px-5 py-3.5 font-semibold text-xs tracking-wider uppercase">Status</th>
                                <th className="px-5 py-3.5 text-right font-semibold text-xs tracking-wider uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {requests.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-5 py-12 text-center text-muted-foreground">
                                        <Mail className="mx-auto size-10 text-muted-foreground/40 mb-3" />
                                        <p className="text-base font-medium">No letter requests found</p>
                                        <p className="text-sm mt-1">Pending NOC or Salary Certificate applications will appear here.</p>
                                    </td>
                                </tr>
                            ) : (
                                requests.data.map((req) => (
                                    <tr key={req.id} className="hover:bg-muted/20 transition-colors">
                                        <td className="px-5 py-4">
                                            <div className="font-semibold text-foreground">{req.employee_name}</div>
                                            <div className="text-xs text-muted-foreground">{req.emp_num || req.employee_email}</div>
                                        </td>
                                        <td className="px-5 py-4 font-medium text-foreground">
                                            {req.document_name}
                                        </td>
                                        <td className="hidden px-5 py-4 text-muted-foreground md:table-cell">
                                            <div className="line-clamp-1">{req.purpose}</div>
                                        </td>
                                        <td className="hidden px-5 py-4 text-xs lg:table-cell">
                                            <div>Applied: <span className="font-medium text-foreground">{req.applied_date}</span></div>
                                            {req.approved_date && (
                                                <div className="text-emerald-700">Approved: {req.approved_date}</div>
                                            )}
                                        </td>
                                        <td className="px-5 py-4">{getStatusBadge(req.status)}</td>
                                        <td className="px-5 py-4 text-right">
                                            <Button asChild variant="outline" size="sm" className="gap-1.5 rounded-lg">
                                                <Link href={`/letter-requests/${req.id}`}>
                                                    <Eye className="size-3.5" />
                                                    Process Request
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

LetterRequestsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Letter Requests',
            href: '/letter-requests',
        },
    ],
};
