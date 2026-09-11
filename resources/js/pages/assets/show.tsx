import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    FileText,
    History,
    Laptop,
    RotateCcw,
    Shield,
    User,
    UserCheck,
    X,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';

interface AssignmentRow {
    id: number;
    assigned_date: string;
    returned_date: string | null;
    note: string | null;
    status: 'Assigned' | 'Returned';
    assignee?: {
        id: number;
        emp_num: string;
        employee_code: string | null;
        designation: string | null;
        user?: {
            name: string;
            email: string;
        } | null;
    } | null;
}

interface RequestRow {
    id: number;
    request_no: string;
    request_type: string;
    description: string;
    priority: string;
    status: string;
    created_at: string;
    requester?: {
        user?: {
            name: string;
        } | null;
    } | null;
}

interface AssetDetail {
    id: number;
    referenceno: string;
    asset_name: string;
    details: string | null;
    condition: string;
    status: string;
    attachment: string | null;
    created_at: string;
    category?: {
        id: number;
        category: string;
        shortcode: string;
    } | null;
    assignments?: AssignmentRow[];
    requests?: RequestRow[];
}

interface EmployeeOption {
    id: number;
    emp_num: string;
    employee_code: string | null;
    designation: string | null;
    user?: {
        name: string;
    } | null;
}

interface Props {
    asset: AssetDetail;
    employees: EmployeeOption[];
}

export default function AssetsShow({ asset, employees }: Props) {
    const [showAssignModal, setShowAssignModal] = useState(false);
    const [showReturnModal, setShowReturnModal] = useState(false);

    const currentAssignment = asset.assignments?.find((a) => a.status === 'Assigned') ?? null;

    const assignForm = useForm({
        assigned_to: '',
        assigned_date: new Date().toISOString().split('T')[0],
        note: '',
    });

    const returnForm = useForm({
        returned_date: new Date().toISOString().split('T')[0],
        return_condition: asset.condition,
        note: '',
    });

    const handleAssign = (e: React.FormEvent) => {
        e.preventDefault();
        assignForm.post(`/assets/${asset.id}/assign`, {
            onSuccess: () => {
                setShowAssignModal(false);
                assignForm.reset();
            },
        });
    };

    const handleReturn = (e: React.FormEvent) => {
        e.preventDefault();
        if (!currentAssignment) return;
        returnForm.post(`/assets-assigned/${currentAssignment.id}/return`, {
            onSuccess: () => {
                setShowReturnModal(false);
                returnForm.reset();
            },
        });
    };

    return (
        <>
            <Head title={`Asset - ${asset.asset_name}`} />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1400px] flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <Link href="/assets">
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <Heading
                                title={asset.asset_name}
                                description={`Reference ID: ${asset.referenceno} • Registered ${new Date(asset.created_at).toLocaleDateString()}`}
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {currentAssignment ? (
                            <Button
                                variant="outline"
                                onClick={() => setShowReturnModal(true)}
                                className="border-amber-200 text-amber-700 hover:bg-amber-50"
                            >
                                <RotateCcw className="mr-1.5 size-4" />
                                Return Asset
                            </Button>
                        ) : (
                            <Button onClick={() => setShowAssignModal(true)}>
                                <UserCheck className="mr-1.5 size-4" />
                                Assign to Employee
                            </Button>
                        )}
                    </div>
                </div>

                {/* Assign Modal */}
                {showAssignModal && (
                    <div className="rounded-2xl border border-primary/30 bg-primary/5 p-6 shadow-sm">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-base font-semibold text-foreground flex items-center gap-2">
                                <UserCheck className="size-5 text-primary" />
                                Assign Asset to Employee
                            </h3>
                            <Button variant="ghost" size="sm" onClick={() => setShowAssignModal(false)}>
                                <X className="size-4" />
                            </Button>
                        </div>
                        <form onSubmit={handleAssign} className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="assigned_to">Select Employee *</Label>
                                    <select
                                        id="assigned_to"
                                        value={assignForm.data.assigned_to}
                                        onChange={(e) => assignForm.setData('assigned_to', e.target.value)}
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                        required
                                    >
                                        <option value="">-- Select Employee --</option>
                                        {employees.map((emp) => (
                                            <option key={emp.id} value={emp.id}>
                                                {emp.user?.name} ({emp.employee_code || emp.emp_num})
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="assigned_date">Assigned Date *</Label>
                                    <Input
                                        id="assigned_date"
                                        type="date"
                                        value={assignForm.data.assigned_date}
                                        onChange={(e) => assignForm.setData('assigned_date', e.target.value)}
                                        required
                                    />
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="assign_note">Handover Condition & Notes</Label>
                                    <Input
                                        id="assign_note"
                                        placeholder="Note on accessories, charger, condition during assign..."
                                        value={assignForm.data.note}
                                        onChange={(e) => assignForm.setData('note', e.target.value)}
                                    />
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <Button variant="outline" type="button" onClick={() => setShowAssignModal(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={assignForm.processing}>
                                    Confirm Assignment
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Return Modal */}
                {showReturnModal && currentAssignment && (
                    <div className="rounded-2xl border border-amber-200 bg-amber-50/60 p-6 shadow-sm">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-base font-semibold text-amber-950 flex items-center gap-2">
                                <RotateCcw className="size-5 text-amber-700" />
                                Return Asset from {currentAssignment.assignee?.user?.name}
                            </h3>
                            <Button variant="ghost" size="sm" onClick={() => setShowReturnModal(false)}>
                                <X className="size-4" />
                            </Button>
                        </div>
                        <form onSubmit={handleReturn} className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="returned_date" className="text-amber-950">Return Date *</Label>
                                    <Input
                                        id="returned_date"
                                        type="date"
                                        value={returnForm.data.returned_date}
                                        onChange={(e) => returnForm.setData('returned_date', e.target.value)}
                                        className="bg-white"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="return_condition" className="text-amber-950">Condition Upon Return *</Label>
                                    <select
                                        id="return_condition"
                                        value={returnForm.data.return_condition}
                                        onChange={(e) => returnForm.setData('return_condition', e.target.value)}
                                        className="flex h-10 w-full rounded-md border border-input bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    >
                                        <option value="New">New</option>
                                        <option value="Excellent">Excellent</option>
                                        <option value="Good">Good</option>
                                        <option value="Fair">Fair</option>
                                        <option value="Damaged">Damaged</option>
                                        <option value="Needs Repair">Needs Repair</option>
                                    </select>
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="return_note" className="text-amber-950">Inspection / Return Notes</Label>
                                    <Textarea
                                        id="return_note"
                                        rows={2}
                                        placeholder="Any scratches, missing power cords, or wear and tear observed..."
                                        value={returnForm.data.note}
                                        onChange={(e) => returnForm.setData('note', e.target.value)}
                                        className="bg-white"
                                    />
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <Button variant="outline" type="button" onClick={() => setShowReturnModal(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={returnForm.processing} className="bg-amber-600 hover:bg-amber-700 text-white">
                                    Confirm Return to Stock
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Main Details */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Current Status Card */}
                        <div className="rounded-2xl border border-border bg-white p-6 shadow-sm">
                            <h3 className="text-base font-semibold text-foreground flex items-center gap-2 mb-4">
                                <Laptop className="size-5 text-primary" />
                                Asset Information
                            </h3>
                            <div className="grid grid-cols-2 gap-6 sm:grid-cols-3">
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">Category</p>
                                    <p className="text-sm font-semibold text-foreground mt-0.5">{asset.category?.category}</p>
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">Reference / Barcode</p>
                                    <p className="text-sm font-mono font-semibold text-foreground mt-0.5">{asset.referenceno}</p>
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">Condition</p>
                                    <p className="text-sm font-semibold text-foreground mt-0.5">{asset.condition}</p>
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">Status</p>
                                    <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold text-foreground mt-0.5">
                                        {asset.status}
                                    </span>
                                </div>
                                <div className="sm:col-span-2">
                                    <p className="text-xs font-medium text-muted-foreground">Assignment State</p>
                                    <p className="text-sm font-semibold text-foreground mt-0.5">
                                        {currentAssignment ? (
                                            <span className="text-emerald-700">Currently assigned to {currentAssignment.assignee?.user?.name}</span>
                                        ) : (
                                            <span className="text-muted-foreground italic">Available in inventory</span>
                                        )}
                                    </p>
                                </div>
                                {asset.details && (
                                    <div className="col-span-2 sm:col-span-3">
                                        <p className="text-xs font-medium text-muted-foreground">Details & Specs</p>
                                        <p className="text-sm text-foreground bg-muted/20 p-3 rounded-lg mt-1 whitespace-pre-wrap">
                                            {asset.details}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Assignment History */}
                        <div className="rounded-2xl border border-border bg-white p-6 shadow-sm">
                            <h3 className="text-base font-semibold text-foreground flex items-center gap-2 mb-4">
                                <History className="size-5 text-primary" />
                                Custody & Assignment History
                            </h3>
                            <div className="overflow-hidden rounded-xl border border-border">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b border-border bg-muted/40 text-muted-foreground text-xs uppercase">
                                        <tr>
                                            <th className="px-4 py-2.5">Employee</th>
                                            <th className="px-4 py-2.5">Assigned Date</th>
                                            <th className="px-4 py-2.5">Returned Date</th>
                                            <th className="px-4 py-2.5">Notes</th>
                                            <th className="px-4 py-2.5">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {!asset.assignments || asset.assignments.length === 0 ? (
                                            <tr>
                                                <td colSpan={5} className="px-4 py-6 text-center text-xs text-muted-foreground">
                                                    No assignment history recorded yet.
                                                </td>
                                            </tr>
                                        ) : (
                                            asset.assignments.map((assign) => (
                                                <tr key={assign.id} className="hover:bg-muted/10">
                                                    <td className="px-4 py-2.5 font-medium text-foreground">
                                                        {assign.assignee?.user?.name ?? 'Unknown'}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-xs text-muted-foreground">
                                                        {assign.assigned_date}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-xs text-muted-foreground">
                                                        {assign.returned_date || <span className="text-emerald-700 font-semibold">Active</span>}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-xs text-muted-foreground max-w-xs truncate">
                                                        {assign.note || '-'}
                                                    </td>
                                                    <td className="px-4 py-2.5">
                                                        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                            assign.status === 'Assigned' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'
                                                        }`}>
                                                            {assign.status}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* Requests History */}
                        {asset.requests && asset.requests.length > 0 && (
                            <div className="rounded-2xl border border-border bg-white p-6 shadow-sm">
                                <h3 className="text-base font-semibold text-foreground flex items-center gap-2 mb-4">
                                    <Shield className="size-5 text-primary" />
                                    Related Asset Requests
                                </h3>
                                <div className="space-y-3">
                                    {asset.requests.map((req) => (
                                        <div key={req.id} className="flex items-center justify-between rounded-xl border border-border p-3 text-xs">
                                            <div>
                                                <span className="font-mono font-semibold text-primary">{req.request_no}</span>
                                                <span className="text-muted-foreground mx-1.5">•</span>
                                                <span className="font-medium text-foreground">{req.request_type} Request</span>
                                                <p className="text-muted-foreground mt-0.5">{req.description}</p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-foreground">
                                                    {req.status}
                                                </span>
                                                <Button variant="ghost" size="sm" asChild>
                                                    <Link href={`/asset-requests/${req.id}`}>View</Link>
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Current Assignee Card */}
                        <div className="rounded-2xl border border-border bg-white p-5 shadow-sm">
                            <h3 className="text-sm font-semibold text-foreground flex items-center gap-2 mb-3">
                                <User className="size-4 text-primary" />
                                Current Custody
                            </h3>
                            {currentAssignment ? (
                                <div className="space-y-2 text-sm">
                                    <div>
                                        <p className="text-xs text-muted-foreground">Assigned Employee</p>
                                        <p className="font-medium text-foreground">{currentAssignment.assignee?.user?.name}</p>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2 text-xs">
                                        <div>
                                            <p className="text-muted-foreground">Code</p>
                                            <p className="font-medium text-foreground">{currentAssignment.assignee?.employee_code || currentAssignment.assignee?.emp_num}</p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground">Designation</p>
                                            <p className="font-medium text-foreground">{currentAssignment.assignee?.designation || 'N/A'}</p>
                                        </div>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Assigned Since</p>
                                        <p className="font-medium text-foreground">{currentAssignment.assigned_date}</p>
                                    </div>
                                    {currentAssignment.note && (
                                        <div>
                                            <p className="text-xs text-muted-foreground">Handover Notes</p>
                                            <p className="text-xs text-muted-foreground bg-muted/30 p-2 rounded mt-0.5">{currentAssignment.note}</p>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="rounded-xl bg-muted/20 p-4 text-center">
                                    <CheckCircle2 className="size-6 text-emerald-600 mx-auto mb-1" />
                                    <p className="text-xs text-muted-foreground">In Stock & Ready for Assignment</p>
                                </div>
                            )}
                        </div>

                        {/* Image / Attachment Card */}
                        {asset.attachment && (
                            <div className="rounded-2xl border border-border bg-white p-5 shadow-sm">
                                <h3 className="text-sm font-semibold text-foreground flex items-center gap-2 mb-3">
                                    <FileText className="size-4 text-primary" />
                                    Attachment / Photo
                                </h3>
                                <a
                                    href={`/storage/${asset.attachment}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="block overflow-hidden rounded-xl border border-border hover:opacity-90 transition-opacity"
                                >
                                    <img
                                        src={`/storage/${asset.attachment}`}
                                        alt={asset.asset_name}
                                        className="h-48 w-full object-cover"
                                    />
                                </a>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

AssetsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Assets', href: '/assets' },
        { title: 'Asset Details' },
    ],
};
