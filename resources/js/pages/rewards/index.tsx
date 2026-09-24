import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Award,
    Calendar,
    CheckCircle2,
    Clock,
    DollarSign,
    Download,
    Eye,
    FileSpreadsheet,
    FileText,
    Filter,
    FolderTree,
    History,
    IndianRupee,
    Plus,
    Printer,
    RefreshCw,
    Search,
    SlidersHorizontal,
    Trash2,
    UploadCloud,
    User,
    X,
    XCircle,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';

interface RewardCategoryItem {
    id: number;
    reward_name: string;
    short_code: string;
}

interface EmployeeItem {
    id: number;
    user_id: number;
    emp_num: string;
    designation?: string;
    user?: {
        id: number;
        name: string;
        email?: string;
    } | null;
}

interface ApprovalStatusItem {
    id: number;
    reward_id: number;
    status: 'pending' | 'approved' | 'paid' | 'rejected';
    done_by: number | null;
    done_on: string;
    comments: string | null;
    done_by_user?: {
        id: number;
        name: string;
    } | null;
}

interface RewardItem {
    id: number;
    claim_no: string;
    category_id: number;
    submitted_by: number | null;
    amount: number | string;
    status: 'pending' | 'approved' | 'paid' | 'rejected';
    description: string;
    document_path: string | null;
    submitted_date: string;
    created_at: string;
    updated_at: string;
    category?: RewardCategoryItem;
    employee?: EmployeeItem | null;
    approval_statuses?: ApprovalStatusItem[];
}

interface PaginatedRewards {
    data: RewardItem[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface SummaryMetrics {
    total_claims: number;
    paid_this_month: number;
    pending_count: number;
    pending_amount: number;
    approved_count: number;
    approved_amount: number;
}

interface Props {
    summary: SummaryMetrics;
    rewards: PaginatedRewards;
    categories: RewardCategoryItem[];
    employees: EmployeeItem[];
    filters: {
        status?: string;
        category_id?: string;
        date_filter?: string;
        search?: string;
    };
}

export default function RewardsIndex({
    summary,
    rewards,
    categories,
    employees,
    filters,
}: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [categoryFilter, setCategoryFilter] = useState(filters.category_id || 'all');
    const [dateFilter, setDateFilter] = useState(filters.date_filter || 'all');

    // Dialog state for Apply / Nominate Reward Claim
    const [applyDialogOpen, setApplyDialogOpen] = useState(false);
    const [formCategoryId, setFormCategoryId] = useState('');
    const [formSubmittedBy, setFormSubmittedBy] = useState('');
    const [formAmount, setFormAmount] = useState('');
    const [formDescription, setFormDescription] = useState('');
    const [formDocument, setFormDocument] = useState<File | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Dialog state for Claim Details & Approval Audit Trail
    const [detailDialogOpen, setDetailDialogOpen] = useState(false);
    const [selectedReward, setSelectedReward] = useState<RewardItem | null>(null);
    const [actionComment, setActionComment] = useState('');
    const [isUpdatingStatus, setIsUpdatingStatus] = useState(false);

    const applyFilters = (newFilters: {
        status?: string;
        category_id?: string;
        date_filter?: string;
        search?: string;
    }) => {
        router.get(
            '/rewards',
            {
                status: newFilters.status ?? statusFilter,
                category_id: newFilters.category_id ?? categoryFilter,
                date_filter: newFilters.date_filter ?? dateFilter,
                search: newFilters.search ?? searchTerm,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters({ search: searchTerm });
    };

    const resetClaimForm = () => {
        setFormCategoryId('');
        setFormSubmittedBy('');
        setFormAmount('');
        setFormDescription('');
        setFormDocument(null);
    };

    const handleApplyClaim = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        const formData = new FormData();
        formData.append('category_id', formCategoryId);
        if (formSubmittedBy && formSubmittedBy !== 'none') {
            formData.append('submitted_by', formSubmittedBy);
        }
        formData.append('amount', formAmount);
        formData.append('description', formDescription);
        if (formDocument) {
            formData.append('document', formDocument);
        }

        router.post('/rewards', formData, {
            onSuccess: () => {
                setIsSubmitting(false);
                setApplyDialogOpen(false);
                resetClaimForm();
            },
            onError: () => {
                setIsSubmitting(false);
            },
        });
    };

    const handleUpdateStatus = (newStatus: 'approved' | 'paid' | 'rejected') => {
        if (!selectedReward) return;

        if (newStatus === 'rejected' && !actionComment.trim()) {
            alert('Rejection reason / comment is required when rejecting a reward claim.');
            return;
        }

        setIsUpdatingStatus(true);
        router.post(
            `/rewards/${selectedReward.id}/status`,
            {
                status: newStatus,
                comments: actionComment,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsUpdatingStatus(false);
                    setActionComment('');
                    setDetailDialogOpen(false);
                    setSelectedReward(null);
                },
                onError: () => {
                    setIsUpdatingStatus(false);
                },
            }
        );
    };

    const handleDeleteReward = (reward: RewardItem) => {
        if (confirm(`Are you sure you want to delete claim ${reward.claim_no}?`)) {
            router.delete(`/rewards/${reward.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    if (selectedReward?.id === reward.id) {
                        setDetailDialogOpen(false);
                        setSelectedReward(null);
                    }
                },
            });
        }
    };

    const formatCurrency = (val: number | string) => {
        const num = Number(val) || 0;
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            maximumFractionDigits: 2,
        }).format(num);
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'pending':
                return (
                    <Badge variant="outline" className="border-amber-400 bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 flex items-center gap-1 font-medium">
                        <Clock className="size-3" />
                        Pending Approval
                    </Badge>
                );
            case 'approved':
                return (
                    <Badge variant="outline" className="border-blue-400 bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 flex items-center gap-1 font-medium">
                        <CheckCircle2 className="size-3" />
                        Approved
                    </Badge>
                );
            case 'paid':
                return (
                    <Badge variant="outline" className="border-emerald-400 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 flex items-center gap-1 font-medium">
                        <DollarSign className="size-3" />
                        Paid
                    </Badge>
                );
            case 'rejected':
                return (
                    <Badge variant="outline" className="border-rose-400 bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 flex items-center gap-1 font-medium">
                        <XCircle className="size-3" />
                        Rejected
                    </Badge>
                );
            default:
                return <Badge variant="secondary">{status}</Badge>;
        }
    };

    return (
        <>
            <Head title="Rewards & Claims" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <Heading
                            title="Reward Claims & Approvals"
                            description="Reward eligible team members, track nomination claims, disburse incentives, and view complete audit history"
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <Link href="/reward-categories">
                            <Button variant="outline" className="gap-2">
                                <FolderTree className="size-4" />
                                Reward Categories
                            </Button>
                        </Link>
                        <Button
                            onClick={() => {
                                resetClaimForm();
                                setApplyDialogOpen(true);
                            }}
                            className="gap-2 shadow-sm"
                        >
                            <Plus className="size-4" />
                            Apply for Reward
                        </Button>
                    </div>
                </div>

                {/* Dashboard Summary Metric Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-border/60 bg-gradient-to-br from-card to-muted/20 shadow-sm transition-all hover:shadow">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Total Claims
                            </CardTitle>
                            <div className="rounded-xl bg-primary/10 p-2.5 text-primary">
                                <Award className="size-5" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold tracking-tight">
                                {summary.total_claims}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Total reward claims on record
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-emerald-200/50 bg-gradient-to-br from-emerald-500/5 to-emerald-500/10 shadow-sm transition-all hover:shadow dark:border-emerald-900/40">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-emerald-800 dark:text-emerald-400">
                                Paid This Month
                            </CardTitle>
                            <div className="rounded-xl bg-emerald-500/10 p-2.5 text-emerald-600 dark:text-emerald-400">
                                <IndianRupee className="size-5" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                                {formatCurrency(summary.paid_this_month)}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Disbursed in {new Date().toLocaleString('default', { month: 'long', year: 'numeric' })}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-amber-200/50 bg-gradient-to-br from-amber-500/5 to-amber-500/10 shadow-sm transition-all hover:shadow dark:border-amber-900/40">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-amber-800 dark:text-amber-400">
                                Pending Approvals
                            </CardTitle>
                            <div className="rounded-xl bg-amber-500/10 p-2.5 text-amber-600 dark:text-amber-400">
                                <Clock className="size-5" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-bold text-amber-700 dark:text-amber-300">
                                    {summary.pending_count}
                                </span>
                                <span className="text-xs font-semibold text-muted-foreground">
                                    ({formatCurrency(summary.pending_amount)})
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Claims awaiting manager review
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-blue-200/50 bg-gradient-to-br from-blue-500/5 to-blue-500/10 shadow-sm transition-all hover:shadow dark:border-blue-900/40">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-blue-800 dark:text-blue-400">
                                Approved Claims
                            </CardTitle>
                            <div className="rounded-xl bg-blue-500/10 p-2.5 text-blue-600 dark:text-blue-400">
                                <CheckCircle2 className="size-5" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-bold text-blue-700 dark:text-blue-300">
                                    {summary.approved_count}
                                </span>
                                <span className="text-xs font-semibold text-muted-foreground">
                                    ({formatCurrency(summary.approved_amount)})
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Approved & ready for payment
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filter and Search Toolbar */}
                <div className="flex flex-col gap-4 rounded-xl border border-border/60 bg-card p-4 shadow-sm">
                    {/* Status Tabs */}
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border/40 pb-3">
                        <div className="flex flex-wrap items-center gap-1.5">
                            {[
                                { key: 'all', label: 'All Claims' },
                                { key: 'pending', label: 'Pending Approval', count: summary.pending_count },
                                { key: 'approved', label: 'Approved', count: summary.approved_count },
                                { key: 'paid', label: 'Paid History' },
                                { key: 'rejected', label: 'Rejected' },
                            ].map((tab) => {
                                const active = statusFilter === tab.key;
                                return (
                                    <button
                                        key={tab.key}
                                        type="button"
                                        onClick={() => {
                                            setStatusFilter(tab.key);
                                            applyFilters({ status: tab.key });
                                        }}
                                        className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-all ${
                                            active
                                                ? 'bg-primary text-primary-foreground shadow-sm'
                                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                        }`}
                                    >
                                        <span>{tab.label}</span>
                                        {tab.count !== undefined && tab.count > 0 && (
                                            <span
                                                className={`rounded-full px-1.5 py-0.2 text-[10px] font-bold ${
                                                    active
                                                        ? 'bg-primary-foreground/20 text-primary-foreground'
                                                        : 'bg-muted-foreground/15 text-muted-foreground'
                                                }`}
                                            >
                                                {tab.count}
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>

                        {/* Date Filter Tabs */}
                        <div className="flex items-center gap-1.5 text-xs">
                            <Calendar className="size-3.5 text-muted-foreground" />
                            {[
                                { key: 'all', label: 'All Time' },
                                { key: 'today', label: 'Today' },
                                { key: 'this_month', label: 'This Month' },
                                { key: 'last_month', label: 'Last Month' },
                            ].map((dTab) => (
                                <button
                                    key={dTab.key}
                                    type="button"
                                    onClick={() => {
                                        setDateFilter(dTab.key);
                                        applyFilters({ date_filter: dTab.key });
                                    }}
                                    className={`rounded-md px-2.5 py-1 text-xs transition-colors ${
                                        dateFilter === dTab.key
                                            ? 'bg-muted font-semibold text-foreground'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    {dTab.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Secondary Filters: Search & Category */}
                    <div className="flex flex-col sm:flex-row items-center gap-3">
                        <form onSubmit={handleSearchSubmit} className="relative flex-1 w-full">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Search by claim #, employee name, or description..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-9 pr-8"
                            />
                            {searchTerm && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSearchTerm('');
                                        applyFilters({ search: '' });
                                    }}
                                    className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                >
                                    <X className="size-4" />
                                </button>
                            )}
                        </form>

                        <div className="w-full sm:w-64">
                            <Select
                                value={categoryFilter}
                                onValueChange={(val) => {
                                    setCategoryFilter(val);
                                    applyFilters({ category_id: val });
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All Categories" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Reward Categories</SelectItem>
                                    {categories.map((cat) => (
                                        <SelectItem key={cat.id} value={String(cat.id)}>
                                            {cat.reward_name} ({cat.short_code})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {(statusFilter !== 'all' || categoryFilter !== 'all' || dateFilter !== 'all' || searchTerm) && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setStatusFilter('all');
                                    setCategoryFilter('all');
                                    setDateFilter('all');
                                    setSearchTerm('');
                                    router.get('/rewards');
                                }}
                                className="text-xs text-muted-foreground hover:text-foreground"
                            >
                                Reset Filters
                            </Button>
                        )}
                    </div>
                </div>

                {/* Claims Data Table */}
                <div className="rounded-xl border border-border/60 bg-card shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border/60 bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-3.5 font-semibold">Claim No</th>
                                    <th className="px-5 py-3.5 font-semibold">Employee</th>
                                    <th className="px-5 py-3.5 font-semibold">Category</th>
                                    <th className="px-5 py-3.5 font-semibold text-right">Amount</th>
                                    <th className="px-5 py-3.5 font-semibold">Submitted Date</th>
                                    <th className="px-5 py-3.5 font-semibold">Status</th>
                                    <th className="px-5 py-3.5 font-semibold text-center">Attachment</th>
                                    <th className="px-5 py-3.5 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/40">
                                {rewards.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="py-12 text-center">
                                            <div className="mx-auto flex flex-col items-center justify-center text-muted-foreground">
                                                <Award className="size-10 stroke-[1.5] text-muted-foreground/50 mb-2" />
                                                <p className="text-base font-medium text-foreground">No reward claims found</p>
                                                <p className="text-xs text-muted-foreground max-w-sm mt-1">
                                                    There are no reward claims matching your current filter criteria.
                                                </p>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => setApplyDialogOpen(true)}
                                                    className="mt-4 gap-1.5"
                                                >
                                                    <Plus className="size-3.5" />
                                                    Submit First Claim
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    rewards.data.map((reward) => (
                                        <tr
                                            key={reward.id}
                                            className="transition-colors hover:bg-muted/30"
                                        >
                                            <td className="px-5 py-3.5 font-mono text-xs font-semibold text-primary">
                                                {reward.claim_no}
                                            </td>
                                            <td className="px-5 py-3.5">
                                                <div className="flex flex-col">
                                                    <span className="font-medium text-foreground">
                                                        {reward.employee?.user?.name || 'Self / Current User'}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {reward.employee?.emp_num ? `Emp ID: ${reward.employee.emp_num}` : ''}
                                                        {reward.employee?.designation ? ` • ${reward.employee.designation}` : ''}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-5 py-3.5">
                                                <div className="flex items-center gap-1.5">
                                                    <span className="font-medium">
                                                        {reward.category?.reward_name || 'N/A'}
                                                    </span>
                                                    {reward.category?.short_code && (
                                                        <Badge variant="secondary" className="text-[10px] uppercase font-mono px-1.5 py-0">
                                                            {reward.category.short_code}
                                                        </Badge>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-semibold text-foreground">
                                                {formatCurrency(reward.amount)}
                                            </td>
                                            <td className="px-5 py-3.5 text-xs text-muted-foreground">
                                                {reward.submitted_date}
                                            </td>
                                            <td className="px-5 py-3.5">
                                                {getStatusBadge(reward.status)}
                                            </td>
                                            <td className="px-5 py-3.5 text-center">
                                                {reward.document_path ? (
                                                    <a
                                                        href={`/rewards/${reward.id}/download-attachment`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex items-center gap-1 text-xs text-primary hover:underline font-medium"
                                                        title="Download supporting proof document"
                                                    >
                                                        <FileText className="size-3.5" />
                                                        Attachment
                                                    </a>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground/60">—</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setSelectedReward(reward);
                                                            setActionComment('');
                                                            setDetailDialogOpen(true);
                                                        }}
                                                        className="h-8 px-2 text-xs gap-1"
                                                        title="View details & approval history"
                                                    >
                                                        <Eye className="size-3.5" />
                                                        View
                                                    </Button>
                                                    <a
                                                        href={`/rewards/${reward.id}/download-form`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-8 px-2 text-xs gap-1 text-muted-foreground hover:text-foreground"
                                                            title="Printable / Downloadable Claim Form Voucher"
                                                        >
                                                            <Printer className="size-3.5" />
                                                            Voucher
                                                        </Button>
                                                    </a>
                                                    {reward.status === 'pending' && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDeleteReward(reward)}
                                                            className="h-8 px-2 text-xs text-destructive hover:bg-destructive/10"
                                                            title="Delete claim"
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {rewards.links && rewards.links.length > 3 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border/60 px-5 py-3 bg-muted/20">
                            <span className="text-xs text-muted-foreground">
                                Showing page {rewards.current_page} of {rewards.last_page} ({rewards.total} total claims)
                            </span>
                            <div className="flex flex-wrap gap-1.5">
                                {rewards.links.map((link, i) =>
                                    link.url ? (
                                        <Link
                                            key={i}
                                            href={link.url}
                                            className={`rounded-md border px-2.5 py-1 text-xs font-medium transition-colors ${
                                                link.active
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-border bg-card text-foreground hover:bg-muted'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <span
                                            key={i}
                                            className="rounded-md border border-border px-2.5 py-1 text-xs text-muted-foreground opacity-50"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    )
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal: Apply / Nominate for Reward */}
            <Dialog open={applyDialogOpen} onOpenChange={setApplyDialogOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Award className="size-5 text-primary" />
                            Apply for Reward Claim
                        </DialogTitle>
                        <DialogDescription>
                            Submit a reward claim for yourself or nominate an eligible team member.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleApplyClaim} className="space-y-4 pt-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="category_id">
                                Reward Category <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={formCategoryId}
                                onValueChange={setFormCategoryId}
                                required
                            >
                                <SelectTrigger id="category_id">
                                    <SelectValue placeholder="Select reward type / category" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((cat) => (
                                        <SelectItem key={cat.id} value={String(cat.id)}>
                                            {cat.reward_name} ({cat.short_code})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="submitted_by">Employee Nominated / Claiming</Label>
                            <Select
                                value={formSubmittedBy}
                                onValueChange={setFormSubmittedBy}
                            >
                                <SelectTrigger id="submitted_by">
                                    <SelectValue placeholder="Current Logged In Employee (Default)" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Current User</SelectItem>
                                    {employees.map((emp) => (
                                        <SelectItem key={emp.id} value={String(emp.id)}>
                                            {emp.user?.name || `Employee #${emp.emp_num}`}
                                            {emp.emp_num ? ` (${emp.emp_num})` : ''}
                                            {emp.designation ? ` - ${emp.designation}` : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="amount">
                                Reward Amount (₹) <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                placeholder="e.g. 5000"
                                value={formAmount}
                                onChange={(e) => setFormAmount(e.target.value)}
                                required
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="description">
                                Justification / Nomination Details <span className="text-destructive">*</span>
                            </Label>
                            <Textarea
                                id="description"
                                rows={3}
                                placeholder="Describe the achievement, project impact, milestone, or reason for this reward nomination..."
                                value={formDescription}
                                onChange={(e) => setFormDescription(e.target.value)}
                                required
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="document">Supporting Document / Proof (Optional)</Label>
                            <Input
                                id="document"
                                type="file"
                                accept=".pdf,.png,.jpg,.jpeg,.webp"
                                onChange={(e) => {
                                    if (e.target.files && e.target.files[0]) {
                                        setFormDocument(e.target.files[0]);
                                    }
                                }}
                            />
                            <p className="text-[11px] text-muted-foreground">
                                Upload certificates, client testimonials, emails, or scorecards (PDF, JPG, PNG up to 10MB).
                            </p>
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setApplyDialogOpen(false)}
                                disabled={isSubmitting}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={isSubmitting || !formCategoryId || !formAmount || !formDescription}>
                                {isSubmitting ? 'Submitting Claim...' : 'Submit Claim'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal: Claim Details & Approval Audit Trail */}
            <Dialog open={detailDialogOpen} onOpenChange={setDetailDialogOpen}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    {selectedReward && (
                        <>
                            <DialogHeader>
                                <div className="flex items-center justify-between gap-4">
                                    <div className="flex items-center gap-2">
                                        <Award className="size-5 text-primary" />
                                        <DialogTitle className="font-mono text-lg">
                                            {selectedReward.claim_no}
                                        </DialogTitle>
                                    </div>
                                    <div>{getStatusBadge(selectedReward.status)}</div>
                                </div>
                                <DialogDescription>
                                    Reward claim details, proof attachments, and complete approval status audit trail.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-5 pt-3">
                                {/* Claim Details Grid */}
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 rounded-lg bg-muted/30 p-4 border border-border/50">
                                    <div>
                                        <span className="text-xs text-muted-foreground uppercase font-semibold">Employee</span>
                                        <p className="text-sm font-medium mt-0.5">
                                            {selectedReward.employee?.user?.name || 'Self / Current User'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {selectedReward.employee?.emp_num ? `Emp ID: ${selectedReward.employee.emp_num}` : ''}
                                            {selectedReward.employee?.designation ? ` • ${selectedReward.employee.designation}` : ''}
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-xs text-muted-foreground uppercase font-semibold">Category</span>
                                        <p className="text-sm font-medium mt-0.5">
                                            {selectedReward.category?.reward_name} ({selectedReward.category?.short_code})
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-xs text-muted-foreground uppercase font-semibold">Amount</span>
                                        <p className="text-base font-bold text-primary mt-0.5">
                                            {formatCurrency(selectedReward.amount)}
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-xs text-muted-foreground uppercase font-semibold">Submitted Date</span>
                                        <p className="text-sm mt-0.5">{selectedReward.submitted_date}</p>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <span className="text-xs text-muted-foreground uppercase font-semibold">Description / Justification</span>
                                        <p className="text-sm text-foreground mt-0.5 whitespace-pre-line bg-card p-2.5 rounded border border-border/40">
                                            {selectedReward.description}
                                        </p>
                                    </div>
                                    {selectedReward.document_path && (
                                        <div className="sm:col-span-2 flex items-center justify-between bg-card p-3 rounded border border-border/40">
                                            <div className="flex items-center gap-2">
                                                <FileText className="size-4 text-primary" />
                                                <span className="text-xs font-medium">Supporting Proof Attached</span>
                                            </div>
                                            <a
                                                href={`/rewards/${selectedReward.id}/download-attachment`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                                            >
                                                <Download className="size-3.5" />
                                                Download Document
                                            </a>
                                        </div>
                                    )}
                                </div>

                                {/* Printable Claim Form Link */}
                                <div className="flex items-center justify-between rounded-lg border border-primary/20 bg-primary/5 p-3">
                                    <div className="flex items-center gap-2">
                                        <Printer className="size-4 text-primary" />
                                        <div>
                                            <p className="text-xs font-semibold text-foreground">Official Claim Form Voucher</p>
                                            <p className="text-[11px] text-muted-foreground">Download or print the formatted claim document with signature blocks</p>
                                        </div>
                                    </div>
                                    <a
                                        href={`/rewards/${selectedReward.id}/download-form`}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Button size="sm" variant="outline" className="gap-1.5 text-xs">
                                            <Printer className="size-3.5" />
                                            Print / Save Form
                                        </Button>
                                    </a>
                                </div>

                                {/* Approval Workflow Actions */}
                                {(selectedReward.status === 'pending' || selectedReward.status === 'approved') && (
                                    <div className="space-y-3 rounded-lg border border-border/60 bg-muted/20 p-4">
                                        <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Workflow Actions
                                        </h4>
                                        <div className="space-y-1.5">
                                            <Label htmlFor="action_comments" className="text-xs">
                                                Approval / Rejection Comments {selectedReward.status === 'pending' && <span className="text-muted-foreground">(Required if rejecting)</span>}
                                            </Label>
                                            <Input
                                                id="action_comments"
                                                placeholder="Enter review notes or disbursement comments..."
                                                value={actionComment}
                                                onChange={(e) => setActionComment(e.target.value)}
                                            />
                                        </div>

                                        <div className="flex flex-wrap items-center justify-end gap-2 pt-2">
                                            {selectedReward.status === 'pending' && (
                                                <>
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="sm"
                                                        disabled={isUpdatingStatus}
                                                        onClick={() => handleUpdateStatus('rejected')}
                                                        className="gap-1 text-xs"
                                                    >
                                                        <XCircle className="size-3.5" />
                                                        Reject Claim
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        disabled={isUpdatingStatus}
                                                        onClick={() => handleUpdateStatus('approved')}
                                                        className="gap-1 text-xs bg-blue-600 hover:bg-blue-700 text-white"
                                                    >
                                                        <CheckCircle2 className="size-3.5" />
                                                        Approve Claim
                                                    </Button>
                                                </>
                                            )}

                                            {selectedReward.status === 'approved' && (
                                                <>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={isUpdatingStatus}
                                                        onClick={() => handleUpdateStatus('rejected')}
                                                        className="gap-1 text-xs text-destructive hover:bg-destructive/10"
                                                    >
                                                        <XCircle className="size-3.5" />
                                                        Revoke / Reject
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        disabled={isUpdatingStatus}
                                                        onClick={() => handleUpdateStatus('paid')}
                                                        className="gap-1 text-xs bg-emerald-600 hover:bg-emerald-700 text-white"
                                                    >
                                                        <DollarSign className="size-3.5" />
                                                        Mark as Paid
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {/* Approval Status Audit History */}
                                <div className="space-y-3 pt-2">
                                    <div className="flex items-center gap-2">
                                        <History className="size-4 text-muted-foreground" />
                                        <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Reward Approval Status History
                                        </h4>
                                    </div>

                                    {selectedReward.approval_statuses && selectedReward.approval_statuses.length > 0 ? (
                                        <div className="relative pl-6 space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-border/60">
                                            {selectedReward.approval_statuses.map((historyItem) => (
                                                <div key={historyItem.id} className="relative">
                                                    <div className="absolute -left-[1.625rem] top-1 size-3 rounded-full border-2 border-background bg-primary" />
                                                    <div className="flex flex-col gap-1 rounded-lg border border-border/50 bg-card p-3 shadow-xs">
                                                        <div className="flex items-center justify-between gap-2">
                                                            <div className="flex items-center gap-2">
                                                                {getStatusBadge(historyItem.status)}
                                                                <span className="text-xs font-medium text-muted-foreground">
                                                                    by {historyItem.done_by_user?.name || 'System / Manager'}
                                                                </span>
                                                            </div>
                                                            <span className="text-[11px] text-muted-foreground">
                                                                {historyItem.done_on}
                                                            </span>
                                                        </div>
                                                        {historyItem.comments && (
                                                            <p className="text-xs text-foreground/80 mt-1 italic">
                                                                "{historyItem.comments}"
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-xs text-muted-foreground italic">
                                            No approval status transitions logged yet.
                                        </p>
                                    )}
                                </div>
                            </div>

                            <DialogFooter className="pt-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setDetailDialogOpen(false)}
                                >
                                    Close
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

RewardsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Rewards',
            href: '/rewards',
        },
    ],
};
