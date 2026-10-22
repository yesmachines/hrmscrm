import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock,
    Download,
    Eye,
    FileDown,
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    status:
        | 'draft'
        | 'submitted'
        | 'under_review'
        | 'approved'
        | 'rejected'
        | 'cancelled'
        | 'archived'
        | 'expired';
    created_at: string;
    file_url: string | null;
};

type PaginatedDocuments = {
    data: DocumentItem[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

type DownloadableForm = {
    id: number;
    name: string;
    code: string;
    download_url: string;
};

type Props = {
    documents: PaginatedDocuments;
    categories: { id: number; category_name: string; short_code: string }[];
    documentTypes: {
        id: number;
        category_id: number;
        document_name: string;
        document_code: string;
    }[];
    employees: {
        id: number;
        user_id: number;
        emp_num: string;
        designation: string;
        user?: { name: string };
    }[];
    downloadableForms?: DownloadableForm[];
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
    downloadableForms = [],
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState(
        filters.category_id || 'all',
    );
    const [selectedStatus, setSelectedStatus] = useState(
        filters.status || 'all',
    );

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
                    <Badge
                        variant="outline"
                        className="gap-1 border-emerald-200 bg-emerald-50 font-medium text-emerald-700"
                    >
                        <CheckCircle2 className="size-3.5 text-emerald-600" />
                        Approved
                    </Badge>
                );
            case 'submitted':
            case 'under_review':
                return (
                    <Badge
                        variant="outline"
                        className="gap-1 border-amber-200 bg-amber-50 font-medium text-amber-700"
                    >
                        <Clock className="size-3.5 text-amber-600" />
                        Pending Approval
                    </Badge>
                );
            case 'rejected':
                return (
                    <Badge
                        variant="outline"
                        className="gap-1 border-rose-200 bg-rose-50 font-medium text-rose-700"
                    >
                        <XCircle className="size-3.5 text-rose-600" />
                        Rejected
                    </Badge>
                );
            default:
                return (
                    <Badge
                        variant="outline"
                        className="border-slate-200 bg-slate-50 font-medium text-slate-700"
                    >
                        {status}
                    </Badge>
                );
        }
    };

    return (
        <>
            <Head title="Employee Documents" />

            <div className="mx-auto flex w-full max-w-full flex-1 flex-col gap-6 p-6 md:p-8 2xl:max-w-[1600px]">
                {/* Header */}
                <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title="Employee Documents"
                        description="Review employee uploads, personal files, and manage HR documents"
                    />
                    <div className="flex flex-wrap items-center gap-3">
                        {downloadableForms && downloadableForms.length > 0 && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="outline"
                                        className="border-border bg-white text-neutral-800 shadow-sm hover:bg-neutral-50"
                                    >
                                        <FileDown className="mr-1.5 size-4 text-primary" />
                                        Download Blank Forms
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="w-80 p-2"
                                >
                                    <DropdownMenuLabel className="px-2 py-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Claim & Declaration Forms
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    {downloadableForms.map((form) => (
                                        <DropdownMenuItem
                                            key={form.id}
                                            className="flex cursor-pointer items-center justify-between rounded-lg p-2.5 text-sm focus:bg-neutral-100"
                                            onClick={() =>
                                                window.open(
                                                    form.download_url,
                                                    '_blank',
                                                )
                                            }
                                        >
                                            <div className="flex flex-col pr-2">
                                                <span className="leading-snug font-medium text-neutral-900">
                                                    {form.name}
                                                </span>
                                                <span className="mt-0.5 text-[11px] text-muted-foreground">
                                                    Blank PDF for manual filling
                                                    & signing
                                                </span>
                                            </div>
                                            <Download className="size-4 shrink-0 text-primary" />
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}

                        <Button
                            asChild
                            className="bg-primary text-white shadow-sm hover:bg-primary/90"
                        >
                            <Link href="/employee-documents/create">
                                <Plus className="mr-1.5 size-4" />
                                Direct HR Upload
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Search & Filter Bar */}
                <div className="flex flex-col items-stretch justify-between gap-3 rounded-2xl border border-border bg-white p-4 shadow-sm md:flex-row md:items-center">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            handleFilter({ search });
                        }}
                        className="relative flex-1"
                    >
                        <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                        <Input
                            type="text"
                            placeholder="Search document title, number, or employee..."
                            className="bg-neutral-50/50 pl-9"
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
                                <SelectItem value="all">
                                    All Categories
                                </SelectItem>
                                {categories.map((cat) => (
                                    <SelectItem
                                        key={cat.id}
                                        value={cat.id.toString()}
                                    >
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
                                <SelectItem value="all">
                                    All Statuses
                                </SelectItem>
                                <SelectItem value="submitted">
                                    Pending Approval
                                </SelectItem>
                                <SelectItem value="approved">
                                    Approved
                                </SelectItem>
                                <SelectItem value="rejected">
                                    Rejected
                                </SelectItem>
                                <SelectItem value="draft">Draft</SelectItem>
                            </SelectContent>
                        </Select>

                        {(search ||
                            selectedCategory !== 'all' ||
                            selectedStatus !== 'all') && (
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
                <div className="overflow-x-auto rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-5 py-3.5 text-xs font-semibold tracking-wider uppercase">
                                    Employee
                                </th>
                                <th className="px-5 py-3.5 text-xs font-semibold tracking-wider uppercase">
                                    Document
                                </th>
                                <th className="hidden px-5 py-3.5 text-xs font-semibold tracking-wider uppercase md:table-cell">
                                    Category
                                </th>
                                <th className="hidden px-5 py-3.5 text-xs font-semibold tracking-wider uppercase lg:table-cell">
                                    Doc # / Expiry
                                </th>
                                <th className="px-5 py-3.5 text-xs font-semibold tracking-wider uppercase">
                                    Status
                                </th>
                                <th className="px-5 py-3.5 text-right text-xs font-semibold tracking-wider uppercase">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {documents.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-5 py-12 text-center text-muted-foreground"
                                    >
                                        <FileText className="mx-auto mb-3 size-10 text-muted-foreground/40" />
                                        <p className="text-base font-medium">
                                            No documents found
                                        </p>
                                        <p className="mt-1 text-sm">
                                            Uploaded or requested documents will
                                            appear here.
                                        </p>
                                    </td>
                                </tr>
                            ) : (
                                documents.data.map((doc) => (
                                    <tr
                                        key={doc.id}
                                        className="transition-colors hover:bg-muted/20"
                                    >
                                        <td className="px-5 py-4">
                                            <div className="font-semibold text-foreground">
                                                {doc.employee_name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {doc.emp_num ||
                                                    doc.employee_email}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="font-medium text-foreground">
                                                {doc.document_title ||
                                                    doc.document_name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                Version {doc.current_version} •
                                                Uploaded {doc.created_at}
                                            </div>
                                        </td>
                                        <td className="hidden px-5 py-4 text-muted-foreground md:table-cell">
                                            <span className="inline-flex items-center rounded-md bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-800">
                                                {doc.category_name ||
                                                    'Personal'}
                                            </span>
                                        </td>
                                        <td className="hidden px-5 py-4 lg:table-cell">
                                            <div className="text-sm text-foreground">
                                                {doc.document_number || '—'}
                                            </div>
                                            {doc.expiry_date_display && (
                                                <div className="text-xs text-muted-foreground">
                                                    Expires:{' '}
                                                    {doc.expiry_date_display}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-5 py-4">
                                            {getStatusBadge(doc.status)}
                                        </td>
                                        <td className="px-5 py-4 text-right">
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                                className="gap-1.5 rounded-lg"
                                            >
                                                <Link
                                                    href={`/employee-documents/${doc.id}`}
                                                >
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
