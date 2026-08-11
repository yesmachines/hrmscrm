import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, X, FileText, Calendar, Building, Clock, FileBadge2 } from 'lucide-react';
import { index, update } from '@/actions/App/Http/Controllers/Leave/LeaveRequestController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type LeaveRequestDetail = {
    id: number;
    start_date: string;
    end_date: string;
    total_days: number;
    status: string;
    remarks: string | null;
    created_at: string;
    leave_type: {
        id: number;
        leave_name: string;
        is_paid: boolean;
    };
    employee: {
        id: number;
        user: { name: string; email: string } | null;
        employee_code: string | null;
        designation: string | null;
        image_url: string | null;
        department: { name: string } | null;
    } | null;
    files: {
        id: number;
        file_path: string;
        original_name: string;
    }[];
    histories: {
        id: number;
        action_type: string;
        remarks: string | null;
        action_on: string;
        done_by: { name: string } | null;
    }[];
};

export default function LeaveRequestShow({ leave_request }: { leave_request: LeaveRequestDetail }) {
    const handleStatusUpdate = (status: string) => {
        router.put(update.url(leave_request.id), { status }, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Leave Request Details" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1200px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <Link href={index.url()}>
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <Heading
                            title="Leave Request Details"
                            description={`Submitted on ${new Date(leave_request.created_at).toLocaleDateString()}`}
                        />
                    </div>
                    {leave_request.status === 'applied' && (
                        <div className="flex items-center gap-2">
                            <Button variant="outline" onClick={() => handleStatusUpdate('rejected')}>
                                <X className="mr-2 size-4" />
                                Reject
                            </Button>
                            <Button onClick={() => handleStatusUpdate('approved')}>
                                <Check className="mr-2 size-4" />
                                Approve
                            </Button>
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div className="col-span-1 md:col-span-2 space-y-6">
                        {/* Request Details */}
                        <div className="rounded-xl border border-border bg-white p-6 shadow-sm">
                            <h3 className="mb-4 text-lg font-semibold text-foreground flex items-center gap-2">
                                <Calendar className="size-5 text-muted-foreground" />
                                Leave Information
                            </h3>
                            <div className="grid grid-cols-2 gap-6">
                                <div>
                                    <p className="text-sm text-muted-foreground">Leave Type</p>
                                    <p className="font-medium text-foreground">{leave_request.leave_type?.leave_name}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Status</p>
                                    <p>
                                        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                            leave_request.status === 'approved' ? 'bg-green-100 text-green-700' :
                                            leave_request.status === 'rejected' ? 'bg-red-100 text-red-700' :
                                            leave_request.status === 'cancelled' ? 'bg-gray-100 text-gray-700' :
                                            'bg-blue-100 text-blue-700'
                                        }`}>
                                            {leave_request.status.charAt(0).toUpperCase() + leave_request.status.slice(1)}
                                        </span>
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Start Date</p>
                                    <p className="font-medium text-foreground">{new Date(leave_request.start_date).toLocaleDateString()}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">End Date</p>
                                    <p className="font-medium text-foreground">{new Date(leave_request.end_date).toLocaleDateString()}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Total Days</p>
                                    <p className="font-medium text-foreground">{leave_request.total_days}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Is Paid</p>
                                    <p className="font-medium text-foreground">{leave_request.leave_type?.is_paid ? 'Yes' : 'No'}</p>
                                </div>
                            </div>
                            
                            {leave_request.remarks && (
                                <div className="mt-6 border-t pt-4">
                                    <p className="text-sm text-muted-foreground mb-1">Remarks / Reason</p>
                                    <p className="text-foreground">{leave_request.remarks}</p>
                                </div>
                            )}
                        </div>

                        {/* Certificates / Files */}
                        {leave_request.files && leave_request.files.length > 0 && (
                            <div className="rounded-xl border border-border bg-white p-6 shadow-sm">
                                <h3 className="mb-4 text-lg font-semibold text-foreground flex items-center gap-2">
                                    <FileBadge2 className="size-5 text-muted-foreground" />
                                    Attached Certificates
                                </h3>
                                <ul className="space-y-3">
                                    {leave_request.files.map(file => (
                                        <li key={file.id} className="flex items-center justify-between rounded-md border p-3">
                                            <div className="flex items-center gap-3">
                                                <FileText className="size-8 text-primary" />
                                                <span className="font-medium text-sm text-foreground">{file.original_name}</span>
                                            </div>
                                            <Button variant="outline" size="sm" asChild>
                                                <a href={`/storage/${file.file_path}`} target="_blank" rel="noopener noreferrer">
                                                    View
                                                </a>
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>

                    <div className="col-span-1 space-y-6">
                        {/* Employee Details */}
                        <div className="rounded-xl border border-border bg-white p-6 shadow-sm">
                            <h3 className="mb-4 text-lg font-semibold text-foreground">Employee Profile</h3>
                            <div className="flex items-center gap-4 mb-4">
                                <div className="size-12 shrink-0 overflow-hidden rounded-full bg-muted">
                                    {leave_request.employee?.image_url ? (
                                        <img src={leave_request.employee.image_url} alt="" className="size-full object-cover" />
                                    ) : (
                                        <div className="flex size-full items-center justify-center text-lg font-bold text-muted-foreground">
                                            {leave_request.employee?.user?.name?.charAt(0)}
                                        </div>
                                    )}
                                </div>
                                <div>
                                    <h4 className="font-semibold text-foreground">{leave_request.employee?.user?.name}</h4>
                                    <p className="text-sm text-muted-foreground">{leave_request.employee?.designation}</p>
                                </div>
                            </div>
                            <div className="space-y-3 text-sm">
                                <div className="flex items-center gap-2 text-muted-foreground">
                                    <Building className="size-4" />
                                    <span>{leave_request.employee?.department?.name ?? 'No Department'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Employee Code</span>
                                    <span className="font-medium text-foreground">{leave_request.employee?.employee_code}</span>
                                </div>
                            </div>
                        </div>

                        {/* History Timeline */}
                        {leave_request.histories && leave_request.histories.length > 0 && (
                            <div className="rounded-xl border border-border bg-white p-6 shadow-sm">
                                <h3 className="mb-4 text-lg font-semibold text-foreground flex items-center gap-2">
                                    <Clock className="size-5 text-muted-foreground" />
                                    Tracking History
                                </h3>
                                <div className="space-y-6">
                                    {leave_request.histories.map((history, idx) => (
                                        <div key={history.id} className="relative flex gap-4">
                                            <div className="flex flex-col items-center">
                                                <div className="size-2.5 rounded-full bg-primary ring-4 ring-primary/20"></div>
                                                {idx !== leave_request.histories.length - 1 && (
                                                    <div className="h-full w-px bg-border my-2"></div>
                                                )}
                                            </div>
                                            <div className="pb-6 last:pb-0">
                                                <p className="font-medium text-foreground text-sm capitalize">
                                                    {history.action_type}
                                                </p>
                                                {history.done_by && (
                                                    <p className="text-xs text-muted-foreground mt-0.5">By: {history.done_by.name}</p>
                                                )}
                                                <p className="text-xs text-muted-foreground mt-0.5">
                                                    {new Date(history.action_on).toLocaleString()}
                                                </p>
                                                {history.remarks && (
                                                    <p className="text-sm text-foreground mt-2 bg-muted p-2 rounded-md">
                                                        "{history.remarks}"
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

LeaveRequestShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave Requests', href: index.url() },
    ],
};
