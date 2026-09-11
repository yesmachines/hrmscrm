import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Calendar,
    Check,
    Clock,
    FileText,
    Mail,
    MapPin,
    Phone,
    Send,
    Shield,
    User,
    Users,
    X,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';

interface VisitorDetail {
    name: string;
    designation?: string;
}

interface VisitDetail {
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
    required_approvals: string | null;
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
            email: string;
        } | null;
    } | null;
    approvals?: {
        id: number;
        instructions: string | null;
        rejected_reason: string | null;
        created_at: string;
        approver?: {
            id: number;
            name: string;
        } | null;
    }[];
}

interface Props {
    visit: VisitDetail;
}

export default function VisitsShow({ visit }: Props) {
    const [activeAction, setActiveAction] = useState<'approve' | 'reject' | null>(null);

    const approveForm = useForm({
        instructions: '',
    });

    const rejectForm = useForm({
        rejected_reason: '',
    });

    const handleApprove = (e: React.FormEvent) => {
        e.preventDefault();
        approveForm.post(`/visits/${visit.id}/approve`, {
            onSuccess: () => setActiveAction(null),
        });
    };

    const handleReject = (e: React.FormEvent) => {
        e.preventDefault();
        rejectForm.post(`/visits/${visit.id}/reject`, {
            onSuccess: () => setActiveAction(null),
        });
    };

    const getStatusBadge = (status: VisitDetail['status']) => {
        switch (status) {
            case 'approved':
                return <span className="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Approved</span>;
            case 'rejected':
                return <span className="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800">Rejected</span>;
            case 'completed':
                return <span className="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">Completed</span>;
            case 'cancelled':
                return <span className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">Cancelled</span>;
            default:
                return <span className="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Pending Review</span>;
        }
    };

    return (
        <>
            <Head title={`Visit #${visit.id} - ${visit.company}`} />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1200px] flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <Link href="/visits">
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <Heading
                                title={`Visit Request #${visit.id}`}
                                description={`Requested on ${new Date(visit.created_at).toLocaleDateString()}`}
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        {getStatusBadge(visit.status)}

                        {visit.status === 'pending' && !activeAction && (
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => setActiveAction('reject')}
                                    className="border-rose-200 text-rose-700 hover:bg-rose-50"
                                >
                                    <X className="mr-1.5 size-4" />
                                    Reject
                                </Button>
                                <Button
                                    onClick={() => setActiveAction('approve')}
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white"
                                >
                                    <Check className="mr-1.5 size-4" />
                                    Approve Request
                                </Button>
                            </div>
                        )}
                    </div>
                </div>

                {/* Approve Action Box */}
                {activeAction === 'approve' && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-6 shadow-sm">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-base font-semibold text-emerald-950 flex items-center gap-2">
                                <Check className="size-5 text-emerald-600" />
                                Approve Visit Request
                            </h3>
                            <Button variant="ghost" size="sm" onClick={() => setActiveAction(null)}>
                                <X className="size-4" />
                            </Button>
                        </div>
                        <form onSubmit={handleApprove} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="instructions" className="text-emerald-950">
                                    Notes & Instructions (Optional)
                                </Label>
                                <Textarea
                                    id="instructions"
                                    rows={3}
                                    placeholder="Add instructions for reception, visitor passes, security escort, or host..."
                                    value={approveForm.data.instructions}
                                    onChange={(e) => approveForm.setData('instructions', e.target.value)}
                                    className="bg-white"
                                />
                                {approveForm.errors.instructions && (
                                    <p className="text-sm text-destructive">{approveForm.errors.instructions}</p>
                                )}
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button variant="outline" type="button" onClick={() => setActiveAction(null)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={approveForm.processing} className="bg-emerald-600 hover:bg-emerald-700 text-white">
                                    <Send className="mr-2 size-4" />
                                    {approveForm.processing ? 'Approving...' : 'Confirm Approval'}
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Reject Action Box */}
                {activeAction === 'reject' && (
                    <div className="rounded-2xl border border-rose-200 bg-rose-50/50 p-6 shadow-sm">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-base font-semibold text-rose-950 flex items-center gap-2">
                                <X className="size-5 text-rose-600" />
                                Reject Visit Request
                            </h3>
                            <Button variant="ghost" size="sm" onClick={() => setActiveAction(null)}>
                                <X className="size-4" />
                            </Button>
                        </div>
                        <form onSubmit={handleReject} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="rejected_reason" className="text-rose-950">
                                    Rejection Reason <span className="text-destructive">*</span>
                                </Label>
                                <Textarea
                                    id="rejected_reason"
                                    rows={3}
                                    placeholder="State why this visit request cannot be approved..."
                                    value={rejectForm.data.rejected_reason}
                                    onChange={(e) => rejectForm.setData('rejected_reason', e.target.value)}
                                    required
                                    className="bg-white"
                                />
                                {rejectForm.errors.rejected_reason && (
                                    <p className="text-sm text-destructive">{rejectForm.errors.rejected_reason}</p>
                                )}
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button variant="outline" type="button" onClick={() => setActiveAction(null)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={rejectForm.processing} variant="destructive">
                                    <X className="mr-2 size-4" />
                                    {rejectForm.processing ? 'Rejecting...' : 'Confirm Rejection'}
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Main Information */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Visitor Roster */}
                        <div className="rounded-2xl border border-border bg-white p-6 shadow-sm">
                            <h3 className="text-base font-semibold text-foreground flex items-center gap-2 mb-4">
                                <Users className="size-5 text-primary" />
                                Visitor Details ({visit.total_visitors} {visit.total_visitors === 1 ? 'Person' : 'Persons'})
                            </h3>
                            <div className="overflow-hidden rounded-xl border border-border">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b border-border bg-muted/40 text-muted-foreground text-xs uppercase">
                                        <tr>
                                            <th className="px-4 py-2.5">#</th>
                                            <th className="px-4 py-2.5">Visitor Name</th>
                                            <th className="px-4 py-2.5">Designation</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {visit.visitor_details?.map((v, idx) => (
                                            <tr key={idx} className="hover:bg-muted/10">
                                                <td className="px-4 py-2.5 text-xs text-muted-foreground">{idx + 1}</td>
                                                <td className="px-4 py-2.5 font-medium text-foreground">{v.name}</td>
                                                <td className="px-4 py-2.5 text-muted-foreground">{v.designation || 'N/A'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* Meeting & Visit Information */}
                        <div className="rounded-2xl border border-border bg-white p-6 shadow-sm">
                            <h3 className="text-base font-semibold text-foreground flex items-center gap-2 mb-4">
                                <FileText className="size-5 text-primary" />
                                Meeting & Location Information
                            </h3>
                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">Location / Meeting Room</p>
                                    <p className="text-sm font-semibold text-foreground flex items-center gap-1.5 mt-1">
                                        <MapPin className="size-4 text-muted-foreground" />
                                        {visit.location}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">Expected Start</p>
                                    <p className="text-sm font-semibold text-foreground flex items-center gap-1.5 mt-1">
                                        <Calendar className="size-4 text-muted-foreground" />
                                        {new Date(visit.expected_start_date).toLocaleString([], {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        })}
                                    </p>
                                </div>
                                {visit.expected_end_date && (
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Expected End</p>
                                        <p className="text-sm font-semibold text-foreground flex items-center gap-1.5 mt-1">
                                            <Calendar className="size-4 text-muted-foreground" />
                                            {new Date(visit.expected_end_date).toLocaleString([], {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            })}
                                        </p>
                                    </div>
                                )}
                                <div className="sm:col-span-2">
                                    <p className="text-xs font-medium text-muted-foreground">Purpose of Visit</p>
                                    <p className="text-sm text-foreground bg-muted/30 p-3 rounded-lg mt-1 leading-relaxed whitespace-pre-wrap">
                                        {visit.purpose}
                                    </p>
                                </div>
                                {visit.required_approvals && (
                                    <div className="sm:col-span-2">
                                        <p className="text-xs font-medium text-muted-foreground">Required Manager / Employee Approvals Mentioned</p>
                                        <p className="text-sm text-foreground bg-amber-50/70 border border-amber-200/60 p-3 rounded-lg mt-1 whitespace-pre-wrap">
                                            {visit.required_approvals}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Approvals and HR Review History */}
                        {visit.approvals && visit.approvals.length > 0 && (
                            <div className="rounded-2xl border border-border bg-white p-6 shadow-sm">
                                <h3 className="text-base font-semibold text-foreground flex items-center gap-2 mb-4">
                                    <Shield className="size-5 text-primary" />
                                    Review & Approval Log
                                </h3>
                                <div className="space-y-4">
                                    {visit.approvals.map((app) => (
                                        <div key={app.id} className="rounded-xl border border-border p-4 bg-muted/20 text-sm">
                                            <div className="flex items-center justify-between mb-2">
                                                <div className="font-semibold text-foreground flex items-center gap-2">
                                                    <span>Reviewed by {app.approver?.name ?? 'HR'}</span>
                                                </div>
                                                <span className="text-xs text-muted-foreground">
                                                    {new Date(app.created_at).toLocaleString([], {
                                                        dateStyle: 'medium',
                                                        timeStyle: 'short',
                                                    })}
                                                </span>
                                            </div>
                                            {app.instructions && (
                                                <div className="mt-2 text-xs text-emerald-900 bg-emerald-50 p-3 rounded-lg border border-emerald-200">
                                                    <p className="font-semibold mb-1">HR Instructions:</p>
                                                    <p className="whitespace-pre-wrap leading-relaxed">{app.instructions}</p>
                                                </div>
                                            )}
                                            {app.rejected_reason && (
                                                <div className="mt-2 text-xs text-rose-900 bg-rose-50 p-3 rounded-lg border border-rose-200">
                                                    <p className="font-semibold mb-1">Rejection Reason:</p>
                                                    <p className="whitespace-pre-wrap leading-relaxed">{app.rejected_reason}</p>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Sidebar Information */}
                    <div className="space-y-6">
                        {/* Company & Contact Card */}
                        <div className="rounded-2xl border border-border bg-white p-5 shadow-sm">
                            <h3 className="text-sm font-semibold text-foreground flex items-center gap-2 mb-3">
                                <Building2 className="size-4 text-primary" />
                                Visitor Organization
                            </h3>
                            <div className="space-y-3 text-sm">
                                <div>
                                    <p className="text-xs text-muted-foreground">Company</p>
                                    <p className="font-medium text-foreground">{visit.company}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Contact No</p>
                                    <p className="font-medium text-foreground flex items-center gap-1.5 mt-0.5">
                                        <Phone className="size-3.5 text-muted-foreground" />
                                        {visit.contact_no}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Email</p>
                                    <p className="font-medium text-foreground flex items-center gap-1.5 mt-0.5">
                                        <Mail className="size-3.5 text-muted-foreground" />
                                        {visit.email}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Host / Assigned Employee Card */}
                        <div className="rounded-2xl border border-border bg-white p-5 shadow-sm">
                            <h3 className="text-sm font-semibold text-foreground flex items-center gap-2 mb-3">
                                <User className="size-4 text-primary" />
                                Host / Created Employee
                            </h3>
                            <div className="space-y-2 text-sm">
                                <div>
                                    <p className="text-xs text-muted-foreground">Employee Name</p>
                                    <p className="font-medium text-foreground">{visit.creator?.user?.name ?? 'Employee'}</p>
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <p className="text-xs text-muted-foreground">Code</p>
                                        <p className="font-medium text-foreground">{visit.creator?.employee_code || visit.creator?.emp_num || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Designation</p>
                                        <p className="font-medium text-foreground">{visit.creator?.designation || 'N/A'}</p>
                                    </div>
                                </div>
                                {visit.creator?.user?.email && (
                                    <div>
                                        <p className="text-xs text-muted-foreground">Email</p>
                                        <p className="text-xs font-medium text-foreground">{visit.creator.user.email}</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

VisitsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Visits', href: '/visits' },
        { title: 'Visit Details' },
    ],
};
