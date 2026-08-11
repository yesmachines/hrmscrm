import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { index, update } from '@/actions/App/Http/Controllers/Leave/LeaveHistoryController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

export default function LeaveHistoriesEdit({ leave_history, leaveRequests, users }: { leave_history: any, leaveRequests: any[], users: any[] }) {
    const { data, setData, put, processing, errors } = useForm({
        leave_request_id: leave_history.leave_request_id,
        action_type: leave_history.action_type,
        remarks: leave_history.remarks || '',
        done_by: leave_history.done_by || '',
        action_on: leave_history.action_on ? new Date(leave_history.action_on).toISOString().slice(0, 16) : '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(update.url(leave_history.id));
    };

    return (
        <>
            <Head title="Edit Leave History" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1200px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <Heading
                        title="Edit Leave History Record"
                        description="Modify an existing audit log entry"
                    />
                </div>

                <div className="rounded-2xl border border-border bg-white p-6 shadow-sm">
                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="leave_request_id">Leave Request</Label>
                                <select
                                    id="leave_request_id"
                                    value={data.leave_request_id}
                                    onChange={(e) => setData('leave_request_id', e.target.value)}
                                    className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">Select Leave Request</option>
                                    {leaveRequests.map((req) => (
                                        <option key={req.id} value={req.id}>
                                            Request #{req.id} ({new Date(req.start_date).toLocaleDateString()} - {new Date(req.end_date).toLocaleDateString()}) - {req.status}
                                        </option>
                                    ))}
                                </select>
                                {errors.leave_request_id && <p className="text-sm text-destructive">{errors.leave_request_id}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="action_type">Action Type</Label>
                                <select
                                    id="action_type"
                                    value={data.action_type}
                                    onChange={(e) => setData('action_type', e.target.value)}
                                    className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="applied">Applied</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                {errors.action_type && <p className="text-sm text-destructive">{errors.action_type}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="done_by">Done By (User)</Label>
                                <select
                                    id="done_by"
                                    value={data.done_by}
                                    onChange={(e) => setData('done_by', e.target.value)}
                                    className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">System / None</option>
                                    {users.map((user) => (
                                        <option key={user.id} value={user.id}>
                                            {user.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.done_by && <p className="text-sm text-destructive">{errors.done_by}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="action_on">Action Date & Time</Label>
                                <Input
                                    id="action_on"
                                    type="datetime-local"
                                    value={data.action_on}
                                    onChange={(e) => setData('action_on', e.target.value)}
                                />
                                {errors.action_on && <p className="text-sm text-destructive">{errors.action_on}</p>}
                            </div>

                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="remarks">Remarks</Label>
                                <Input
                                    id="remarks"
                                    type="text"
                                    value={data.remarks}
                                    onChange={(e) => setData('remarks', e.target.value)}
                                    placeholder="Any comments regarding this action..."
                                />
                                {errors.remarks && <p className="text-sm text-destructive">{errors.remarks}</p>}
                            </div>
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing}>
                                <Save className="mr-2 size-4" />
                                Update Record
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

LeaveHistoriesEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Leave Histories', href: index.url() },
        { title: 'Edit Record' },
    ],
};
