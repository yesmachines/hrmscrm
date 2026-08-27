import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock,
    Eye,
    FileText,
    Filter,
    Plus,
    Search,
    ShieldAlert,
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

type DocumentItem = {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_email: string;
    emp_num: string;
    document_name: string;
    document_code: string;
    category_name: string;
    category_code: string;
    document_title: string;
    document_number: string | null;
    issue_date: string | null;
    issue_date_display: string | null;
    expiry_date: string | null;
    expiry_date_display: string | null;
    current_version: string;
    status: 'draft' | 'submitted' | 'under_review' | 'approved' | 'rejected' | 'cancelled' | 'archived' | 'expired';
    created_at: string;
    file_url: string | null;
};

type PaginatedDocuments = {
    data: DocumentItem[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

type Props = {
    documents: PaginatedDocuments;
    categories: { id: number; category_name: string; short_code: string }[];
    documentTypes: { id: number; category_id: number; document_name: string; document_code: string }[];
    employees: { id: number; user_id: number; emp_num: string; designation: string; user?: { name: string } }[];
    filters: {
        employee_id?: string;
        category_id?: string;
        document_type_id?: string;
        status?: string;
        search?: string;
    };
};

export default function EmployeeDocumentsIndex({
    documents,
    categories,
    documentTypes,
    employees,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState(filters.category_id || 'all');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || 'all');

    const handleFilter = (updates: Record<string, string>) => {
        const query: Record<string, string> = {
            ...filters,
            search,
            category_id: selectedCategory === 'all' ? '' : selectedCategory,
            status: selectedStatus === 'all' ? '' : selectedStatus,
            ...updates,
        };

        // Remove empty keys
        Object.keys(query).forEach((key) => {
            if (!query[key] || query[key] === 'all') delete query[key];
        });

        router.get('/employee-documents', query, { preserveState: true });
    };

    const getStatusBadge = (status: DocumentItem['status']) => {
        switch (status) {
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
                        {status}
                    </Badge>
                );
        }
    };

    return (
        <>
            <Head title="Employee Documents" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <Heading
                        title="Employee Documents"
                        description="Review employee uploads, personal files, and manage HR documents"
                    />
                    <div className="flex items-center gap-3">
                        <Button asChild className="bg-primary hover:bg-primary/90 text-white shadow-sm">
                            <Link href="/employee-documents/create">
                                <Plus className="size-4 mr-1.5" />
                                Direct HR Upload
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Search & Filter Bar */}
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
                            placeholder="Search document title, number, or employee..."
                            className="pl-9 bg-neutral-50/50"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </form>

                    <div className="flex flex-wrap items-center gap-2">
                        <Select
                            value={selectedCategory}
                            onValueChange={(val) => {
                                setSelectedCategory(val);
                                handleFilter({ category_id: val });
                            }}
                        >
                            <SelectTrigger className="w-[180px] bg-neutral-50/50">
                                <SelectValue placeholder="Category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Categories</SelectItem>
                                {categories.map((cat) => (
                                    <SelectItem key={cat.id} value={cat.id.toString()}>
                                        {cat.category_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={selectedStatus}
                            onValueChange={(val) => {
                                setSelectedStatus(val);
                                handleFilter({ status: val });
                            }}
                        >
                            <SelectTrigger className="w-[170px] bg-neutral-50/50">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem value="submitted">Pending Approval</SelectItem>
                                <SelectItem value="approved">Approved</SelectItem>
                                <SelectItem value="rejected">Rejected</SelectItem>
                                <SelectItem value="draft">Draft</SelectItem>
                            </SelectContent>
                        </Select>

                        {(search || selectedCategory !== 'all' || selectedStatus !== 'all') && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    setSelectedCategory('all');
                                    setSelectedStatus('all');
                                    router.get('/employee-documents');
                                }}
                                className="text-muted-foreground hover:text-foreground"
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
                                <th className="px-5 py-3.5 font-semibold text-xs tracking-wider uppercase">Document</th>
                                <th className="hidden px-5 py-3.5 font-semibold text-xs tracking-wider uppercase md:table-cell">Category</th>
                                <th className="hidden px-5 py-3.5 font-semibold text-xs tracking-wider uppercase lg:table-cell">Doc # / Expiry</th>
                                <th className="px-5 py-3.5 font-semibold text-xs tracking-wider uppercase">Status</th>
                                <th className="px-5 py-3.5 text-right font-semibold text-xs tracking-wider uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {documents.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-5 py-12 text-center text-muted-foreground">
                                        <FileText className="mx-auto size-10 text-muted-foreground/40 mb-3" />
                                        <p className="text-base font-medium">No documents found</p>
                                        <p className="text-sm mt-1">Uploaded or requested documents will appear here.</p>
                                    </td>
                                </tr>
                            ) : (
                                documents.data.map((doc) => (
                                    <tr key={doc.id} className="hover:bg-muted/20 transition-colors">
                                        <td className="px-5 py-4">
                                            <div className="font-semibold text-foreground">{doc.employee_name}</div>
                                            <div className="text-xs text-muted-foreground">{doc.emp_num || doc.employee_email}</div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="font-medium text-foreground">{doc.document_title || doc.document_name}</div>
                                            <div className="text-xs text-muted-foreground">Version {doc.current_version} • Uploaded {doc.created_at}</div>
                                        </td>
                                        <td className="hidden px-5 py-4 text-muted-foreground md:table-cell">
                                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-neutral-100 text-neutral-800">
                                                {doc.category_name || 'Personal'}
                                            </span>
                                        </td>
                                        <td className="hidden px-5 py-4 lg:table-cell">
                                            <div className="text-sm text-foreground">{doc.document_number || '—'}</div>
                                            {doc.expiry_date_display && (
                                                <div className="text-xs text-muted-foreground">Expires: {doc.expiry_date_display}</div>
                                            )}
                                        </td>
                                        <td className="px-5 py-4">{getStatusBadge(doc.status)}</td>
                                        <td className="px-5 py-4 text-right">
                                            <Button asChild variant="outline" size="sm" className="gap-1.5 rounded-lg">
                                                <Link href={`/employee-documents/${doc.id}`}>
                                                    <Eye className="size-3.5" />
                                                    View / Review
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

EmployeeDocumentsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Documents',
            href: '/employee-documents',
        },
    ],
};
