import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    Check,
    Eye,
    FolderTree,
    Inbox,
    Laptop,
    Plus,
    Search,
    User,
    X,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

interface RequestItem {
    id: number;
    request_no: string;
    request_type: string;
    description: string;
    priority: string;
    status: string;
    requested_date: string;
    requester?: {
        emp_num: string;
        employee_code: string | null;
        user?: {
            name: string;
        } | null;
    } | null;
    asset?: {
        referenceno: string;
        asset_name: string;
    } | null;
    category?: {
        category: string;
    } | null;
}

interface PaginatedRequests {
    data: RequestItem[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Props {
    requests: PaginatedRequests;
    filters: {
        status: string;
        priority: string;
        request_type: string;
        search: string;
    };
}

export default function AssetRequestsIndex({ requests, filters }: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [priority, setPriority] = useState(filters.priority || '');
    const [requestType, setRequestType] = useState(filters.request_type || '');

    const applyFilters = (newFilters: Partial<typeof filters>) => {
        router.get(
            '/asset-requests',
            {
                search: searchTerm,
                status: status,
                priority: priority,
                request_type: requestType,
                ...newFilters,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters({ search: searchTerm });
    };

    const getStatusBadge = (reqStatus: string) => {
        switch (reqStatus) {
            case 'Approved':
                return <span className="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Approved</span>;
            case 'Rejected':
                return <span className="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800">Rejected</span>;
            case 'In Progress':
                return <span className="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">In Progress</span>;
            case 'Completed':
                return <span className="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-semibold text-purple-800">Completed</span>;
            case 'Cancelled':
                return <span className="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">Cancelled</span>;
            case 'Under Review':
                return <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Under Review</span>;
            default:
                return <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Pending Review</span>;
        }
    };

    const getPriorityBadge = (p: string) => {
        switch (p) {
            case 'Urgent':
                return <span className="inline-flex items-center rounded bg-rose-50 px-1.5 py-0.5 text-[11px] font-bold text-rose-700 border border-rose-200">Urgent</span>;
            case 'High':
                return <span className="inline-flex items-center rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-bold text-amber-700 border border-amber-200">High</span>;
            default:
                return <span className="text-xs text-muted-foreground">{p}</span>;
        }
    };

    return (
        <>
            <Head title="Asset Requests Review" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Header */}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Asset Requests"
                        description="Review employee requests for new hardware, equipment repairs, replacements, and loss reports"
                    />
                    <div className="flex items-center gap-2">
                        <Button asChild>
                            <Link href="/asset-requests/create">
                                <Plus className="mr-2 size-4" />
                                New Request
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/assets">
                                <Laptop className="mr-2 size-4" />
                                Assets Inventory
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/asset-categories">
                                <FolderTree className="mr-2 size-4" />
                                Categories
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Filters */}
                <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border bg-white p-4 shadow-sm">
                    <form onSubmit={handleSearch} className="flex flex-1 items-center gap-2 max-w-md">
                        <div className="relative w-full">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Search by request #, employee, asset, description..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit" variant="secondary" size="sm">
                            Search
                        </Button>
                    </form>

                    <div className="flex flex-wrap items-center gap-3">
                        <select
                            value={requestType}
                            onChange={(e) => {
                                setRequestType(e.target.value);
                                applyFilters({ request_type: e.target.value });
                            }}
                            className="h-9 rounded-md border border-input bg-background px-3 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="">All Types</option>
                            <option value="New">New Asset</option>
                            <option value="Repair">Repair</option>
                            <option value="Replacement">Replacement</option>
                            <option value="Lost">Lost Report</option>
                            <option value="Damage">Damage Report</option>
                        </select>

                        <select
                            value={priority}
                            onChange={(e) => {
                                setPriority(e.target.value);
                                applyFilters({ priority: e.target.value });
                            }}
                            className="h-9 rounded-md border border-input bg-background px-3 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="">All Priorities</option>
                            <option value="Low">Low</option>
                            <option value="Normal">Normal</option>
                            <option value="High">High</option>
                            <option value="Urgent">Urgent</option>
                        </select>

                        <select
                            value={status}
                            onChange={(e) => {
                                setStatus(e.target.value);
                                applyFilters({ status: e.target.value });
                            }}
                            className="h-9 rounded-md border border-input bg-background px-3 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="">All Statuses</option>
                            <option value="Pending">Pending Review</option>
                            <option value="Under Review">Under Review</option>
                            <option value="Approved">Approved</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border bg-muted/40 text-muted-foreground text-xs uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Request # & Type</th>
                                    <th className="px-4 py-3 font-medium">Requester</th>
                                    <th className="px-4 py-3 font-medium">Item / Category</th>
                                    <th className="px-4 py-3 font-medium">Priority</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="px-4 py-3 font-medium">Date</th>
                                    <th className="px-4 py-3 text-right font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {requests.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-12 text-center text-muted-foreground">
                                            No asset requests found.
                                        </td>
                                    </tr>
                                ) : (
                                    requests.data.map((req) => (
                                        <tr key={req.id} className="hover:bg-muted/10 transition-colors">
                                            <td className="px-4 py-3.5">
                                                <div className="font-mono font-semibold text-primary">
                                                    {req.request_no}
                                                </div>
                                                <div className="text-xs font-medium text-foreground mt-0.5">
                                                    {req.request_type} Request
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-1.5 font-medium text-foreground">
                                                    <User className="size-3.5 text-muted-foreground" />
                                                    <span>{req.requester?.user?.name ?? 'Employee'}</span>
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {req.requester?.employee_code || req.requester?.emp_num}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5 max-w-xs">
                                                {req.asset ? (
                                                    <div>
                                                        <div className="font-medium text-foreground">{req.asset.asset_name}</div>
                                                        <div className="text-xs font-mono text-muted-foreground">{req.asset.referenceno}</div>
                                                    </div>
                                                ) : (
                                                    <div className="text-sm font-medium text-foreground">
                                                        {req.category?.category ?? 'General Asset'}
                                                    </div>
                                                )}
                                                <div className="text-xs text-muted-foreground line-clamp-1 mt-0.5">
                                                    {req.description}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {getPriorityBadge(req.priority)}
                                            </td>
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                {getStatusBadge(req.status)}
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-muted-foreground whitespace-nowrap">
                                                {req.requested_date}
                                            </td>
                                            <td className="px-4 py-3.5 text-right whitespace-nowrap">
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={`/asset-requests/${req.id}`}>
                                                        <Eye className="mr-1.5 size-3.5" />
                                                        Review
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

                {/* Pagination */}
                {requests.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {requests.links.map((link, i) =>
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

AssetRequestsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Assets', href: '/assets' },
        { title: 'Requests' },
    ],
};
