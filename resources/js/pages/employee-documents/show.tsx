import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    CheckCircle2,
    Clock,
    Download,
    Eye,
    FileCheck,
    FileText,
    History,
    Printer,
    ShieldAlert,
    UserCheck,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type DocumentDetail = {
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
    remarks: string | null;
    current_version: string;
    status: 'draft' | 'submitted' | 'under_review' | 'approved' | 'rejected' | 'cancelled' | 'archived' | 'expired';
    created_at: string;
    file_url: string | null;
    rendered_html?: string | null;
    template?: { id: number; template_name: string } | null;
    files: {
        id: number;
        version_no: string;
        file_path: string;
        file_url: string;
        uploaded_date: string;
        change_notes: string | null;
    }[];
    histories: {
        id: number;
        action_type: string;
        remarks: string | null;
        action_on: string;
    }[];
    request_details: {
        field_name: string;
        field_key: string;
        field_value: string;
    }[];
};

type Props = {
    document: DocumentDetail;
    employee: {
        id: number;
        emp_num: string;
        designation: string;
        user?: { name: string; email: string };
        department?: { name: string };
    } | null;
};

export default function EmployeeDocumentShow({ document, employee }: Props) {
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [approveDialogOpen, setApproveDialogOpen] = useState(false);
    const [rejectRemarks, setRejectRemarks] = useState('');
    const [approveRemarks, setApproveRemarks] = useState('Approved by HR');
    const [processing, setProcessing] = useState(false);

    const handleApprove = () => {
        setProcessing(true);
        router.post(
            `/employee-documents/${document.id}/approve`,
            { remarks: approveRemarks },
            {
                onFinish: () => {
                    setProcessing(false);
                    setApproveDialogOpen(false);
                },
            }
        );
    };

    const handleReject = () => {
        if (!rejectRemarks.trim()) return;
        setProcessing(true);
        router.post(
            `/employee-documents/${document.id}/reject`,
            { remarks: rejectRemarks },
            {
                onFinish: () => {
                    setProcessing(false);
                    setRejectDialogOpen(false);
                },
            }
        );
    };

    const isPending = document.status === 'submitted' || document.status === 'under_review';

    return (
        <>
            <Head title={`Document: ${document.document_title || document.document_name}`} />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Back Link & Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="outline" size="icon" className="size-9 rounded-xl">
                            <Link href="/employee-documents">
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-2.5">
                                <h1 className="text-xl font-bold tracking-tight text-foreground">
                                    {document.document_title || document.document_name}
                                </h1>
                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-primary/10 text-primary">
                                    v{document.current_version}
                                </span>
                            </div>
                            <p className="text-sm text-muted-foreground mt-0.5">
                                {document.category_name} • {document.employee_name} ({employee?.emp_num})
                            </p>
                        </div>
                    </div>

                    {/* Review Actions */}
                    {isPending && (
                        <div className="flex items-center gap-2.5">
                            <Dialog open={rejectDialogOpen} onOpenChange={setRejectDialogOpen}>
                                <DialogTrigger asChild>
                                    <Button variant="outline" className="border-rose-200 text-rose-700 hover:bg-rose-50 gap-1.5">
                                        <XCircle className="size-4 text-rose-600" />
                                        Reject
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="sm:max-w-md">
                                    <DialogHeader>
                                        <DialogTitle className="text-rose-600 flex items-center gap-2">
                                            <ShieldAlert className="size-5" />
                                            Reject Document
                                        </DialogTitle>
                                        <DialogDescription>
                                            Please provide a clear reason for rejecting this document so the employee can correct and resubmit it.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="space-y-3 py-2">
                                        <Label htmlFor="reject_remarks">Reason / Review Remarks *</Label>
                                        <textarea
                                            id="reject_remarks"
                                            rows={3}
                                            required
                                            value={rejectRemarks}
                                            onChange={(e) => setRejectRemarks(e.target.value)}
                                            placeholder="e.g. Blurry scan, expiry date mismatch, please upload original copy..."
                                            className="w-full rounded-lg border border-input bg-background p-2.5 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        />
                                    </div>
                                    <DialogFooter>
                                        <Button variant="outline" onClick={() => setRejectDialogOpen(false)}>
                                            Cancel
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            disabled={processing || !rejectRemarks.trim()}
                                            onClick={handleReject}
                                        >
                                            Confirm Rejection
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>

                            <Dialog open={approveDialogOpen} onOpenChange={setApproveDialogOpen}>
                                <DialogTrigger asChild>
                                    <Button className="bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5 shadow-sm">
                                        <CheckCircle2 className="size-4" />
                                        Approve Document
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="sm:max-w-md">
                                    <DialogHeader>
                                        <DialogTitle className="text-emerald-700 flex items-center gap-2">
                                            <FileCheck className="size-5" />
                                            Approve Document
                                        </DialogTitle>
                                        <DialogDescription>
                                            Approving will mark this document verified and automatically synchronize verified numbers & expiries with the employee profile.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="space-y-3 py-2">
                                        <Label htmlFor="approve_remarks">Approval Remarks (Optional)</Label>
                                        <textarea
                                            id="approve_remarks"
                                            rows={2}
                                            value={approveRemarks}
                                            onChange={(e) => setApproveRemarks(e.target.value)}
                                            className="w-full rounded-lg border border-input bg-background p-2.5 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        />
                                    </div>
                                    <DialogFooter>
                                        <Button variant="outline" onClick={() => setApproveDialogOpen(false)}>
                                            Cancel
                                        </Button>
                                        <Button
                                            className="bg-emerald-600 hover:bg-emerald-700 text-white"
                                            disabled={processing}
                                            onClick={handleApprove}
                                        >
                                            Approve & Verify
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    )}
                </div>

                {/* Main Content Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left 2 Cols: Preview & Metadata */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* File / Letter Preview Card */}
                        <div className="rounded-2xl border border-border bg-white p-6 shadow-sm">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-base font-semibold flex items-center gap-2">
                                    <FileText className="size-4 text-primary" />
                                    {document.rendered_html && !document.file_url ? 'Generated Letter Document' : `Document File (Version ${document.current_version})`}
                                </h3>
                                <div className="flex items-center gap-2">
                                    {document.rendered_html && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="gap-1.5"
                                            onClick={() => {
                                                const printWindow = window.open('', '_blank');
                                                if (printWindow) {
                                                    printWindow.document.write(`
                                                        <html>
                                                            <head><title>${document.document_title || document.document_name}</title></head>
                                                            <body style="margin: 40px;">${document.rendered_html}</body>
                                                        </html>
                                                    `);
                                                    printWindow.document.close();
                                                    printWindow.focus();
                                                    setTimeout(() => printWindow.print(), 250);
                                                }
                                            }}
                                        >
                                            <Printer className="size-3.5" />
                                            Print Letter / PDF
                                        </Button>
                                    )}
                                    {document.file_url && (
                                        <Button asChild variant="outline" size="sm" className="gap-1.5">
                                            <a href={document.file_url} target="_blank" rel="noopener noreferrer" download>
                                                <Download className="size-3.5" />
                                                Download File
                                            </a>
                                        </Button>
                                    )}
                                </div>
                            </div>

                            {document.file_url ? (
                                <div className="rounded-xl border border-border/80 bg-neutral-50 overflow-hidden min-h-[400px] flex items-center justify-center">
                                    {document.file_url.endsWith('.pdf') ? (
                                        <iframe
                                            src={document.file_url}
                                            className="w-full h-[600px] border-none rounded-xl"
                                            title="PDF Preview"
                                        />
                                    ) : (
                                        <img
                                            src={document.file_url}
                                            alt={document.document_title}
                                            className="max-h-[550px] object-contain rounded-lg p-2"
                                        />
                                    )}
                                </div>
                            ) : document.rendered_html ? (
                                <div className="rounded-xl border border-border/80 bg-slate-50/60 p-6 overflow-x-auto">
                                    <div
                                        className="bg-white shadow-md rounded-lg p-8 mx-auto max-w-[850px] border border-slate-200"
                                        dangerouslySetInnerHTML={{ __html: document.rendered_html }}
                                    />
                                </div>
                            ) : (
                                <div className="p-12 text-center text-muted-foreground border-2 border-dashed border-border rounded-xl">
                                    <FileText className="size-12 mx-auto text-muted-foreground/40 mb-2" />
                                    <p className="font-medium">No file attached to this document record.</p>
                                </div>
                            )}
                        </div>

                        {/* Request Details (if letter request) */}
                        {document.request_details.length > 0 && (
                            <div className="rounded-2xl border border-border bg-white p-6 shadow-sm">
                                <h3 className="text-base font-semibold mb-4">Custom Request Details</h3>
                                <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    {document.request_details.map((detail) => (
                                        <div key={detail.field_key} className="space-y-1 bg-neutral-50 p-3.5 rounded-xl border border-border/50">
                                            <dt className="text-xs font-semibold text-muted-foreground uppercase">{detail.field_name}</dt>
                                            <dd className="text-sm font-medium text-foreground">{detail.field_value}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>
                        )}
                    </div>

                    {/* Right 1 Col: Metadata & Version History */}
                    <div className="space-y-6">
                        {/* Status & Key Meta Card */}
                        <div className="rounded-2xl border border-border bg-white p-5 shadow-sm space-y-4">
                            <h3 className="text-sm font-semibold tracking-tight uppercase text-muted-foreground">Document Details</h3>

                            <div className="space-y-3 divide-y divide-border/60">
                                <div className="pt-2 flex items-center justify-between">
                                    <span className="text-xs text-muted-foreground">Status</span>
                                    <span className="text-sm font-semibold capitalize">{document.status}</span>
                                </div>
                                <div className="pt-3 flex items-center justify-between">
                                    <span className="text-xs text-muted-foreground">Document Number</span>
                                    <span className="text-sm font-medium text-foreground">{document.document_number || '—'}</span>
                                </div>
                                <div className="pt-3 flex items-center justify-between">
                                    <span className="text-xs text-muted-foreground">Issue Date</span>
                                    <span className="text-sm font-medium text-foreground">{document.issue_date_display || '—'}</span>
                                </div>
                                <div className="pt-3 flex items-center justify-between">
                                    <span className="text-xs text-muted-foreground">Expiry Date</span>
                                    <span className="text-sm font-medium text-foreground">{document.expiry_date_display || '—'}</span>
                                </div>
                                <div className="pt-3 flex items-center justify-between">
                                    <span className="text-xs text-muted-foreground">Uploaded On</span>
                                    <span className="text-sm font-medium text-foreground">{document.created_at}</span>
                                </div>
                                {document.remarks && (
                                    <div className="pt-3">
                                        <span className="text-xs text-muted-foreground block mb-1">Remarks</span>
                                        <p className="text-sm text-foreground bg-neutral-50 p-2 rounded-lg border border-border/60">{document.remarks}</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Employee Profile Snippet */}
                        {employee && (
                            <div className="rounded-2xl border border-border bg-white p-5 shadow-sm space-y-3">
                                <h3 className="text-sm font-semibold tracking-tight uppercase text-muted-foreground flex items-center gap-1.5">
                                    <UserCheck className="size-4 text-primary" />
                                    Employee Info
                                </h3>
                                <div className="text-sm font-medium">{employee.user?.name}</div>
                                <div className="text-xs text-muted-foreground">
                                    Emp #: <span className="text-foreground font-medium">{employee.emp_num}</span> • Dept: <span className="text-foreground font-medium">{employee.department?.name || 'General'}</span>
                                </div>
                                <div className="pt-2">
                                    <Button asChild variant="outline" size="sm" className="w-full text-xs">
                                        <Link href={`/employees/${employee.id}`}>View Full Employee Profile</Link>
                                    </Button>
                                </div>
                            </div>
                        )}

                        {/* Audit Trail & Version History */}
                        <div className="rounded-2xl border border-border bg-white p-5 shadow-sm space-y-4">
                            <h3 className="text-sm font-semibold tracking-tight uppercase text-muted-foreground flex items-center gap-1.5">
                                <History className="size-4 text-primary" />
                                Audit & Version Log
                            </h3>

                            <div className="space-y-4 relative before:absolute before:inset-0 before:left-2.5 before:w-0.5 before:bg-border/60">
                                {document.histories.map((hist, idx) => (
                                    <div key={idx} className="relative flex items-start gap-3 pl-6">
                                        <div className="absolute left-1.5 top-1 size-2 rounded-full bg-primary" />
                                        <div>
                                            <div className="text-xs font-semibold capitalize text-foreground">{hist.action_type}</div>
                                            <div className="text-xs text-muted-foreground">{hist.action_on}</div>
                                            {hist.remarks && <p className="text-xs text-muted-foreground mt-0.5">{hist.remarks}</p>}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

EmployeeDocumentShow.layout = {
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
