import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    DollarSign,
    FileCheck,
    FileText,
    History,
    Landmark,
    Mail,
    Printer,
    ShieldAlert,
    User,
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type LetterDetail = {
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
    salary_amount: string | null;
    bank_iban: string | null;
    status: 'draft' | 'submitted' | 'under_review' | 'approved' | 'rejected' | 'cancelled' | 'archived' | 'expired';
    remarks: string | null;
    applied_date: string;
    approved_date: string | null;
    rendered_html?: string | null;
    histories: {
        id: number;
        action_type: string;
        remarks: string | null;
        action_on: string;
    }[];
};

type Props = {
    request: LetterDetail;
    employee: {
        id: number;
        emp_num: string;
        designation: string;
        joining_date?: string;
        user?: { name: string; email: string };
        department?: { name: string };
        organisation?: { name: string };
    } | null;
};

export default function LetterRequestShow({ request, employee }: Props) {
    const [approveOpen, setApproveOpen] = useState(false);
    const [rejectOpen, setRejectOpen] = useState(false);
    const [salaryAmount, setSalaryAmount] = useState(request.salary_amount || '15,000 AED');
    const [bankIban, setBankIban] = useState(request.bank_iban || 'AE070331234567890123456');
    const [remarks, setRemarks] = useState(request.remarks || '');
    const [rejectRemarks, setRejectRemarks] = useState('');
    const [processing, setProcessing] = useState(false);

    const isPending = request.status === 'submitted' || request.status === 'under_review';
    const isSalaryLetter = request.document_code === 'salary_certificate' || request.document_code === 'salary_transfer_letter';

    const handleApprove = () => {
        setProcessing(true);
        router.post(
            `/letter-requests/${request.id}/approve`,
            {
                salary_amount: salaryAmount,
                bank_iban: bankIban,
                remarks,
            },
            {
                onFinish: () => {
                    setProcessing(false);
                    setApproveOpen(false);
                },
            }
        );
    };

    const handleReject = () => {
        if (!rejectRemarks.trim()) return;
        setProcessing(true);
        router.post(
            `/letter-requests/${request.id}/reject`,
            { remarks: rejectRemarks },
            {
                onFinish: () => {
                    setProcessing(false);
                    setRejectOpen(false);
                },
            }
        );
    };

    return (
        <>
            <Head title={`${request.document_name} Request`} />

            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="outline" size="icon" className="size-9 rounded-xl">
                            <Link href="/letter-requests">
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-2.5">
                                <h1 className="text-xl font-bold tracking-tight text-foreground">
                                    {request.document_name} Request
                                </h1>
                                <Badge
                                    variant="outline"
                                    className={`capitalize ${
                                        request.status === 'approved'
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                            : request.status === 'rejected'
                                            ? 'bg-rose-50 text-rose-700 border-rose-200'
                                            : 'bg-amber-50 text-amber-700 border-amber-200'
                                    }`}
                                >
                                    {request.status}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground mt-0.5">
                                Requested by {request.employee_name} on {request.applied_date}
                            </p>
                        </div>
                    </div>

                    {/* Review Actions */}
                    {isPending && (
                        <div className="flex items-center gap-2.5">
                            <Dialog open={rejectOpen} onOpenChange={setRejectOpen}>
                                <DialogTrigger asChild>
                                    <Button variant="outline" className="border-rose-200 text-rose-700 hover:bg-rose-50 gap-1.5">
                                        <XCircle className="size-4" />
                                        Reject
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="sm:max-w-md">
                                    <DialogHeader>
                                        <DialogTitle className="text-rose-600 flex items-center gap-2">
                                            <ShieldAlert className="size-5" />
                                            Reject Letter Request
                                        </DialogTitle>
                                        <DialogDescription>
                                            Provide the reason for rejecting this application.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="space-y-3 py-2">
                                        <Label htmlFor="rej_remarks">Reason *</Label>
                                        <textarea
                                            id="rej_remarks"
                                            rows={3}
                                            required
                                            value={rejectRemarks}
                                            onChange={(e) => setRejectRemarks(e.target.value)}
                                            placeholder="Reason for rejection..."
                                            className="w-full rounded-lg border border-input bg-background p-2.5 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        />
                                    </div>
                                    <DialogFooter>
                                        <Button variant="outline" onClick={() => setRejectOpen(false)}>
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

                            <Dialog open={approveOpen} onOpenChange={setApproveOpen}>
                                <DialogTrigger asChild>
                                    <Button className="bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5 shadow-sm">
                                        <CheckCircle2 className="size-4" />
                                        Approve & Generate Letter
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="sm:max-w-lg">
                                    <DialogHeader>
                                        <DialogTitle className="text-emerald-700 flex items-center gap-2">
                                            <FileCheck className="size-5" />
                                            Approve Letter & Provide HR Details
                                        </DialogTitle>
                                        <DialogDescription>
                                            Enter the official verification details below to embed into the generated document.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="space-y-4 py-2">
                                        {isSalaryLetter && (
                                            <>
                                                <div className="space-y-1.5">
                                                    <Label htmlFor="salary_amount" className="flex items-center gap-1.5">
                                                        <DollarSign className="size-3.5 text-muted-foreground" />
                                                        Gross / Net Monthly Salary *
                                                    </Label>
                                                    <Input
                                                        id="salary_amount"
                                                        value={salaryAmount}
                                                        onChange={(e) => setSalaryAmount(e.target.value)}
                                                        placeholder="e.g. AED 18,500"
                                                    />
                                                </div>

                                                {request.document_code === 'salary_transfer_letter' && (
                                                    <div className="space-y-1.5">
                                                        <Label htmlFor="bank_iban" className="flex items-center gap-1.5">
                                                            <Landmark className="size-3.5 text-muted-foreground" />
                                                            Employee Bank IBAN *
                                                        </Label>
                                                        <Input
                                                            id="bank_iban"
                                                            value={bankIban}
                                                            onChange={(e) => setBankIban(e.target.value)}
                                                            placeholder="e.g. AE070331234567890123456"
                                                        />
                                                    </div>
                                                )}
                                            </>
                                        )}

                                        <div className="space-y-1.5">
                                            <Label htmlFor="app_remarks">HR Endorsement Remarks</Label>
                                            <textarea
                                                id="app_remarks"
                                                rows={2}
                                                value={remarks}
                                                onChange={(e) => setRemarks(e.target.value)}
                                                placeholder="e.g. Verified by HR. Issued for Embassy Visa purposes."
                                                className="w-full rounded-lg border border-input bg-background p-2.5 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            />
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button variant="outline" onClick={() => setApproveOpen(false)}>
                                            Cancel
                                        </Button>
                                        <Button
                                            className="bg-emerald-600 hover:bg-emerald-700 text-white"
                                            disabled={processing}
                                            onClick={handleApprove}
                                        >
                                            Approve & Issue Certificate
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    )}
                </div>

                {/* Details Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {/* Left 2 Cols: Request Content & Generated Document Preview */}
                    <div className="md:col-span-2 space-y-6">
                        {/* Generated Letter Preview Card */}
                        {request.rendered_html && (
                            <div className="rounded-2xl border border-border bg-white p-6 shadow-sm">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-base font-semibold flex items-center gap-2">
                                        <FileText className="size-4 text-primary" />
                                        Generated Official Letter Document
                                    </h3>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="gap-1.5"
                                        onClick={() => {
                                            const printWindow = window.open('', '_blank');
                                            if (printWindow) {
                                                printWindow.document.write(`
                                                    <html>
                                                        <head><title>${request.document_name} - ${request.employee_name}</title></head>
                                                        <body style="margin: 40px;">${request.rendered_html}</body>
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
                                </div>

                                <div className="rounded-xl border border-border/80 bg-slate-50/60 p-6 overflow-x-auto">
                                    <div
                                        className="bg-white shadow-md rounded-lg p-8 mx-auto max-w-[850px] border border-slate-200"
                                        dangerouslySetInnerHTML={{ __html: request.rendered_html }}
                                    />
                                </div>
                            </div>
                        )}

                        <div className="rounded-2xl border border-border bg-white p-6 shadow-sm space-y-5">
                            <h3 className="text-base font-semibold border-b border-border pb-3">Request Information</h3>

                            <div className="space-y-4">
                                <div>
                                    <span className="text-xs font-bold text-muted-foreground tracking-wider uppercase block mb-1">
                                        Purpose
                                    </span>
                                    <p className="text-base font-medium text-foreground bg-neutral-50 p-3 rounded-xl border border-border/50">
                                        {request.purpose}
                                    </p>
                                </div>

                                {request.details && (
                                    <div>
                                        <span className="text-xs font-bold text-muted-foreground tracking-wider uppercase block mb-1">
                                            Details & Specific Notes
                                        </span>
                                        <p className="text-sm text-foreground bg-neutral-50 p-3.5 rounded-xl border border-border/50 leading-relaxed">
                                            {request.details}
                                        </p>
                                    </div>
                                )}

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                    {request.to_address && (
                                        <div className="bg-neutral-50 p-3.5 rounded-xl border border-border/50">
                                            <span className="text-xs font-bold text-muted-foreground tracking-wider uppercase block mb-0.5">
                                                Addressed To (Bank / Embassy)
                                            </span>
                                            <p className="text-sm font-semibold text-foreground">{request.to_address}</p>
                                        </div>
                                    )}

                                    {request.visa_designation && (
                                        <div className="bg-neutral-50 p-3.5 rounded-xl border border-border/50">
                                            <span className="text-xs font-bold text-muted-foreground tracking-wider uppercase block mb-0.5">
                                                Visa Designation
                                            </span>
                                            <p className="text-sm font-semibold text-foreground">{request.visa_designation}</p>
                                        </div>
                                    )}
                                </div>

                                {/* Approved Financial Meta (if approved) */}
                                {(request.salary_amount || request.bank_iban) && (
                                    <div className="mt-4 p-4 rounded-xl bg-emerald-50/50 border border-emerald-200/80 space-y-2">
                                        <h4 className="text-xs font-bold text-emerald-800 tracking-wider uppercase">Approved HR Financial Data</h4>
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                            {request.salary_amount && (
                                                <div>
                                                    <span className="text-xs text-muted-foreground block">Salary Certified:</span>
                                                    <span className="font-semibold text-emerald-950">{request.salary_amount}</span>
                                                </div>
                                            )}
                                            {request.bank_iban && (
                                                <div>
                                                    <span className="text-xs text-muted-foreground block">IBAN Number:</span>
                                                    <span className="font-semibold text-emerald-950">{request.bank_iban}</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Right 1 Col: Employee & History */}
                    <div className="space-y-6">
                        {/* Auto-Fetched Employee Meta */}
                        <div className="rounded-2xl border border-border bg-white p-5 shadow-sm space-y-4">
                            <h3 className="text-xs font-bold tracking-wider uppercase text-muted-foreground flex items-center gap-1.5">
                                <User className="size-4 text-primary" />
                                Employee Profile Data
                            </h3>

                            <div className="space-y-2.5 text-sm">
                                <div>
                                    <span className="text-xs text-muted-foreground block">Full Name</span>
                                    <span className="font-semibold text-foreground">{employee?.user?.name || request.employee_name}</span>
                                </div>
                                <div>
                                    <span className="text-xs text-muted-foreground block">Employee Code</span>
                                    <span className="font-semibold text-foreground">{employee?.emp_num || request.emp_num}</span>
                                </div>
                                <div>
                                    <span className="text-xs text-muted-foreground block">Designation</span>
                                    <span className="font-medium text-foreground">{employee?.designation || 'Staff'}</span>
                                </div>
                                <div>
                                    <span className="text-xs text-muted-foreground block">Company / Dept</span>
                                    <span className="font-medium text-foreground">{employee?.organisation?.name || 'Main Org'} • {employee?.department?.name || 'General'}</span>
                                </div>
                            </div>
                        </div>

                        {/* Audit Log */}
                        <div className="rounded-2xl border border-border bg-white p-5 shadow-sm space-y-4">
                            <h3 className="text-xs font-bold tracking-wider uppercase text-muted-foreground flex items-center gap-1.5">
                                <History className="size-4 text-primary" />
                                Request History
                            </h3>

                            <div className="space-y-3">
                                {request.histories.map((hist, idx) => (
                                    <div key={idx} className="text-xs border-l-2 border-primary pl-3 py-0.5 space-y-0.5">
                                        <div className="font-semibold capitalize text-foreground">{hist.action_type}</div>
                                        <div className="text-muted-foreground">{hist.action_on}</div>
                                        {hist.remarks && <div className="text-muted-foreground italic">"{hist.remarks}"</div>}
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

LetterRequestShow.layout = {
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
