import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Calendar,
    CheckCircle2,
    Clock,
    FolderTree,
    Inbox,
    Laptop,
    Tag,
    User,
    XCircle,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';

interface AssetRequestDetail {
    id: number;
    request_no: string;
    request_type: 'New' | 'Repair' | 'Replacement' | 'Lost' | 'Damage';
    description: string;
    priority: 'Low' | 'Normal' | 'High' | 'Urgent';
    status:
        | 'Pending'
        | 'Under Review'
        | 'Approved'
        | 'Rejected'
        | 'In Progress'
        | 'Completed'
        | 'Cancelled';
    requested_date: string;
    required_by_date: string | null;
    rejection_reason: string | null;
    admin_notes: string | null;
    reviewed_at: string | null;
    created_at: string;
    requester?: {
        emp_num: string;
        employee_code: string | null;
        user?: {
            name: string;
            email: string;
        } | null;
    } | null;
    reviewer?: {
        emp_num: string;
        user?: {
            name: string;
        } | null;
    } | null;
    asset?: {
        id: number;
        referenceno: string;
        asset_name: string;
        condition: string;
        status: string;
        image_url: string | null;
        category?: {
            category: string;
        } | null;
    } | null;
    category?: {
        id: number;
        category: string;
    } | null;
}

interface Props {
    assetRequest: AssetRequestDetail;
}

export default function AssetRequestShow({ assetRequest }: Props) {
    const [action, setAction] = useState<'idle' | 'approve' | 'reject' | 'status'>('idle');
    const [adminNotes, setAdminNotes] = useState(assetRequest.admin_notes || '');
    const [rejectionReason, setRejectionReason] = useState('');
    const [newStatus, setNewStatus] = useState<string>(assetRequest.status);
    const [processing, setProcessing] = useState(false);

    const handleApprove = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        router.post(
            `/asset-requests/${assetRequest.id}/approve`,
            { admin_notes: adminNotes },
            {
                onFinish: () => {
                    setProcessing(false);
                    setAction('idle');
                },
            },
        );
    };

    const handleReject = (e: React.FormEvent) => {
        e.preventDefault();
        if (!rejectionReason.trim()) return;
        setProcessing(true);
        router.post(
            `/asset-requests/${assetRequest.id}/reject`,
            { rejection_reason: rejectionReason },
            {
                onFinish: () => {
                    setProcessing(false);
                    setAction('idle');
                },
            },
        );
    };

    const handleUpdateStatus = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        router.post(
            `/asset-requests/${assetRequest.id}/status`,
            { status: newStatus, admin_notes: adminNotes },
            {
                onFinish: () => {
                    setProcessing(false);
                    setAction('idle');
                },
            },
        );
    };

    const getStatusBadge = (status: string) => {
        const map: Record<string, string> = {
            Pending: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 border-amber-300',
            'Under Review': 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300 border-blue-300',
            Approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-300',
            Rejected: 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300 border-red-300',
            'In Progress': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-300 border-indigo-300',
            Completed: 'bg-teal-100 text-teal-800 dark:bg-teal-950/50 dark:text-teal-300 border-teal-300',
            Cancelled: 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300 border-neutral-300',
        };
        return map[status] || 'bg-neutral-100 text-neutral-800 border-neutral-300';
    };

    const getPriorityBadge = (priority: string) => {
        const map: Record<string, string> = {
            Urgent: 'bg-red-500 text-white',
            High: 'bg-orange-500 text-white',
            Normal: 'bg-blue-500 text-white',
            Low: 'bg-neutral-400 text-white',
        };
        return map[priority] || 'bg-neutral-400 text-white';
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: dashboard() },
                { title: 'Asset Requests', href: '/asset-requests' },
                { title: assetRequest.request_no, href: `/asset-requests/${assetRequest.id}` },
            ]}
        >
            <Head title={`Asset Request ${assetRequest.request_no}`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <Link
                            href="/asset-requests"
                            className="inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300 mb-2"
                        >
                            <ArrowLeft className="h-4 w-4" /> Back to Requests
                        </Link>
                        <div className="flex items-center gap-3">
                            <Heading
                                title={assetRequest.request_no}
                                description={`Submitted by ${assetRequest.requester?.user?.name || 'Employee'} on ${assetRequest.requested_date}`}
                            />
                            <span className={`inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold ${getStatusBadge(assetRequest.status)}`}>
                                {assetRequest.status}
                            </span>
                            <span className={`inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${getPriorityBadge(assetRequest.priority)}`}>
                                {assetRequest.priority} Priority
                            </span>
                        </div>
                    </div>

                    {/* Action buttons */}
                    <div className="flex flex-wrap items-center gap-2">
                        {['Pending', 'Under Review'].includes(assetRequest.status) && (
                            <>
                                <Button
                                    onClick={() => setAction('approve')}
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white"
                                >
                                    <CheckCircle2 className="mr-1.5 h-4 w-4" />
                                    Approve Request
                                </Button>
                                <Button
                                    onClick={() => setAction('reject')}
                                    variant="destructive"
                                >
                                    <XCircle className="mr-1.5 h-4 w-4" />
                                    Reject Request
                                </Button>
                            </>
                        )}
                        <Button
                            onClick={() => setAction('status')}
                            variant="outline"
                        >
                            Update Status / Notes
                        </Button>
                    </div>
                </div>

                {/* Modals / Action Panels */}
                {action === 'approve' && (
                    <div className="rounded-xl border border-emerald-300 bg-emerald-50/50 p-6 dark:border-emerald-800 dark:bg-emerald-950/20">
                        <h3 className="text-base font-semibold text-emerald-900 dark:text-emerald-200 mb-2">
                            Approve Asset Request
                        </h3>
                        <p className="text-sm text-emerald-700 dark:text-emerald-300 mb-4">
                            You are approving request {assetRequest.request_no}. You can optionally include instructions or notes for the requester.
                        </p>
                        <form onSubmit={handleApprove} className="space-y-4">
                            <div>
                                <Label htmlFor="notes">Instructions / Admin Notes (Optional)</Label>
                                <Textarea
                                    id="notes"
                                    value={adminNotes}
                                    onChange={(e) => setAdminNotes(e.target.value)}
                                    placeholder="e.g. Please collect the requested equipment from IT Room 204 on Monday."
                                    rows={3}
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white"
                                >
                                    {processing ? 'Approving...' : 'Confirm Approval'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setAction('idle')}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {action === 'reject' && (
                    <div className="rounded-xl border border-red-300 bg-red-50/50 p-6 dark:border-red-800 dark:bg-red-950/20">
                        <h3 className="text-base font-semibold text-red-900 dark:text-red-200 mb-2">
                            Reject Asset Request
                        </h3>
                        <p className="text-sm text-red-700 dark:text-red-300 mb-4">
                            Please provide a clear reason for rejecting this request. This will be visible to the employee.
                        </p>
                        <form onSubmit={handleReject} className="space-y-4">
                            <div>
                                <Label htmlFor="reason">Rejection Reason *</Label>
                                <Textarea
                                    id="reason"
                                    required
                                    value={rejectionReason}
                                    onChange={(e) => setRejectionReason(e.target.value)}
                                    placeholder="e.g. Out of stock; will be reordered next quarter."
                                    rows={3}
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={processing || !rejectionReason.trim()}
                                    variant="destructive"
                                >
                                    {processing ? 'Rejecting...' : 'Confirm Rejection'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setAction('idle')}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {action === 'status' && (
                    <div className="rounded-xl border border-neutral-300 bg-neutral-50/50 p-6 dark:border-neutral-700 dark:bg-neutral-900/50">
                        <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
                            Update Request Status & Notes
                        </h3>
                        <form onSubmit={handleUpdateStatus} className="space-y-4">
                            <div>
                                <Label htmlFor="statusSelect">Status</Label>
                                <select
                                    id="statusSelect"
                                    value={newStatus}
                                    onChange={(e) => setNewStatus(e.target.value)}
                                    className="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                                >
                                    <option value="Pending">Pending</option>
                                    <option value="Under Review">Under Review</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="statusNotes">Admin Notes</Label>
                                <Textarea
                                    id="statusNotes"
                                    value={adminNotes}
                                    onChange={(e) => setAdminNotes(e.target.value)}
                                    placeholder="Internal or employee-visible notes regarding this request."
                                    rows={3}
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                >
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setAction('idle')}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Details Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Left 2 cols: Request info */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Core Details */}
                        <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-950">
                            <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-4">
                                Request Details
                            </h3>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-xs text-neutral-500">Request Type</p>
                                    <p className="text-sm font-semibold text-neutral-900 dark:text-neutral-100 mt-1">
                                        {assetRequest.request_type} Asset Request
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-neutral-500">Category</p>
                                    <p className="text-sm font-semibold text-neutral-900 dark:text-neutral-100 mt-1">
                                        {assetRequest.category?.category || assetRequest.asset?.category?.category || 'General'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-neutral-500">Date Requested</p>
                                    <p className="text-sm font-semibold text-neutral-900 dark:text-neutral-100 mt-1">
                                        {assetRequest.requested_date}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-neutral-500">Required By</p>
                                    <p className="text-sm font-semibold text-neutral-900 dark:text-neutral-100 mt-1">
                                        {assetRequest.required_by_date || 'Not specified'}
                                    </p>
                                </div>
                            </div>

                            <div className="mt-6 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                                <p className="text-xs text-neutral-500 mb-1">Reason / Description</p>
                                <div className="rounded-lg bg-neutral-50 p-4 text-sm text-neutral-800 whitespace-pre-wrap dark:bg-neutral-900 dark:text-neutral-200">
                                    {assetRequest.description}
                                </div>
                            </div>

                            {/* Rejection notice */}
                            {assetRequest.rejection_reason && (
                                <div className="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-950/20">
                                    <p className="text-xs font-medium text-red-700 dark:text-red-300">Rejection Reason</p>
                                    <p className="text-sm text-red-900 dark:text-red-200 mt-1">
                                        {assetRequest.rejection_reason}
                                    </p>
                                </div>
                            )}

                            {/* Admin notes */}
                            {assetRequest.admin_notes && (
                                <div className="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/50 dark:bg-blue-950/20">
                                    <p className="text-xs font-medium text-blue-700 dark:text-blue-300">Admin Notes / Instructions</p>
                                    <p className="text-sm text-blue-900 dark:text-blue-200 mt-1">
                                        {assetRequest.admin_notes}
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Associated Asset Info (if Repair / Replacement / Lost / Damage) */}
                        {assetRequest.asset && (
                            <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-950">
                                <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-4 flex items-center justify-between">
                                    <span>Subject Asset Information</span>
                                    <Link
                                        href={`/assets/${assetRequest.asset.id}`}
                                        className="text-xs text-primary hover:underline"
                                    >
                                        View Asset Page &rarr;
                                    </Link>
                                </h3>

                                <div className="flex flex-col sm:flex-row items-start gap-4">
                                    {assetRequest.asset.image_url && (
                                        <img
                                            src={assetRequest.asset.image_url}
                                            alt={assetRequest.asset.asset_name}
                                            className="h-20 w-20 rounded-lg object-cover border border-neutral-200 dark:border-neutral-800"
                                        />
                                    )}
                                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 flex-1">
                                        <div>
                                            <p className="text-xs text-neutral-500">Asset Name</p>
                                            <p className="text-sm font-semibold">{assetRequest.asset.asset_name}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-neutral-500">Reference No</p>
                                            <p className="text-sm font-mono font-medium">{assetRequest.asset.referenceno}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-neutral-500">Condition</p>
                                            <p className="text-sm">{assetRequest.asset.condition}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-neutral-500">Current Status</p>
                                            <p className="text-sm">{assetRequest.asset.status}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Right col: Employee & Reviewer Info */}
                    <div className="space-y-6">
                        {/* Employee Card */}
                        <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-950">
                            <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-4 flex items-center gap-2">
                                <User className="h-4 w-4 text-neutral-500" />
                                Requested By
                            </h3>
                            <div className="space-y-3">
                                <div>
                                    <p className="text-xs text-neutral-500">Employee Name</p>
                                    <p className="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                        {assetRequest.requester?.user?.name || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-neutral-500">Employee Code / Number</p>
                                    <p className="text-sm font-mono text-neutral-700 dark:text-neutral-300">
                                        {assetRequest.requester?.employee_code || assetRequest.requester?.emp_num || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-neutral-500">Email</p>
                                    <p className="text-sm text-neutral-700 dark:text-neutral-300">
                                        {assetRequest.requester?.user?.email || 'N/A'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Review info */}
                        <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-950">
                            <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-4 flex items-center gap-2">
                                <Clock className="h-4 w-4 text-neutral-500" />
                                Audit & Review
                            </h3>
                            <div className="space-y-3 text-sm">
                                <div>
                                    <p className="text-xs text-neutral-500">Reviewed By</p>
                                    <p className="text-neutral-900 dark:text-neutral-100 font-medium">
                                        {assetRequest.reviewer?.user?.name || 'Pending Review'}
                                    </p>
                                </div>
                                {assetRequest.reviewed_at && (
                                    <div>
                                        <p className="text-xs text-neutral-500">Reviewed At</p>
                                        <p className="text-neutral-700 dark:text-neutral-300">
                                            {new Date(assetRequest.reviewed_at).toLocaleString()}
                                        </p>
                                    </div>
                                )}
                                <div>
                                    <p className="text-xs text-neutral-500">Created At</p>
                                    <p className="text-neutral-700 dark:text-neutral-300">
                                        {new Date(assetRequest.created_at).toLocaleString()}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
