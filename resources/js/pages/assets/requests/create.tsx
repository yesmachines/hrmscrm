import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Inbox, Laptop, Send } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';

interface AssignedAsset {
    id: number;
    asset_id: number;
    asset_name: string;
    referenceno: string;
    category_id: number;
    category: string;
    condition: string;
    label: string;
}

interface EmployeeItem {
    id: number;
    name: string;
    code: string;
    assigned_assets: AssignedAsset[];
}

interface CategoryItem {
    id: number;
    category: string;
}

interface Props {
    employees: EmployeeItem[];
    categories: CategoryItem[];
}

export default function AssetRequestCreate({ employees, categories }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        requested_by: employees.length > 0 ? String(employees[0].id) : '',
        request_type: 'New' as 'New' | 'Repair' | 'Replacement' | 'Lost' | 'Damage',
        asset_id: '',
        category_id: categories.length > 0 ? String(categories[0].id) : '',
        priority: 'Normal' as 'Low' | 'Normal' | 'High' | 'Urgent',
        description: '',
    });

    const selectedEmployee = employees.find((e) => String(e.id) === String(data.requested_by));
    const assignedAssets = selectedEmployee?.assigned_assets || [];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/asset-requests');
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: dashboard() },
                { title: 'Asset Requests', href: '/asset-requests' },
                { title: 'New Request', href: '/asset-requests/create' },
            ]}
        >
            <Head title="Submit Asset Request" />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-8">
                <div>
                    <Link
                        href="/asset-requests"
                        className="inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300 mb-2"
                    >
                        <ArrowLeft className="h-4 w-4" /> Back to Requests
                    </Link>
                    <Heading
                        title="Submit Asset Request"
                        description="Request new equipment or report repair, replacement, loss, or damage for assigned assets."
                    />
                </div>

                <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-950">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Employee Selection */}
                        <div className="space-y-2">
                            <Label htmlFor="requested_by">Requesting Employee *</Label>
                            <select
                                id="requested_by"
                                value={data.requested_by}
                                onChange={(e) => {
                                    setData((prev) => ({
                                        ...prev,
                                        requested_by: e.target.value,
                                        asset_id: '', // Reset asset selection when employee changes
                                    }));
                                }}
                                className="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-primary dark:border-neutral-700 dark:bg-neutral-900"
                                required
                            >
                                {employees.map((emp) => (
                                    <option key={emp.id} value={emp.id}>
                                        {emp.name} ({emp.code}) — {emp.assigned_assets.length} active asset(s)
                                    </option>
                                ))}
                            </select>
                            {errors.requested_by && (
                                <p className="text-xs text-red-500">{errors.requested_by}</p>
                            )}
                        </div>

                        {/* Request Type */}
                        <div className="space-y-2">
                            <Label htmlFor="request_type">Request Type *</Label>
                            <select
                                id="request_type"
                                value={data.request_type}
                                onChange={(e) => {
                                    const type = e.target.value as typeof data.request_type;
                                    setData((prev) => ({
                                        ...prev,
                                        request_type: type,
                                        asset_id: type === 'New' ? '' : prev.asset_id,
                                    }));
                                }}
                                className="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-primary dark:border-neutral-700 dark:bg-neutral-900"
                                required
                            >
                                <option value="New">New Asset (Request newly needed equipment)</option>
                                <option value="Repair">Repair (Equipment malfunction or issue)</option>
                                <option value="Replacement">Replacement (Swap faulty or outdated device)</option>
                                <option value="Damage">Damage Report (Accidental damage)</option>
                                <option value="Lost">Lost / Stolen Report</option>
                            </select>
                            {errors.request_type && (
                                <p className="text-xs text-red-500">{errors.request_type}</p>
                            )}
                        </div>

                        {/* Conditional: Category Selection (If New) */}
                        {data.request_type === 'New' ? (
                            <div className="space-y-2">
                                <Label htmlFor="category_id">Asset Category *</Label>
                                <select
                                    id="category_id"
                                    value={data.category_id}
                                    onChange={(e) => setData('category_id', e.target.value)}
                                    className="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-primary dark:border-neutral-700 dark:bg-neutral-900"
                                    required
                                >
                                    <option value="">Select Category</option>
                                    {categories.map((cat) => (
                                        <option key={cat.id} value={cat.id}>
                                            {cat.category}
                                        </option>
                                    ))}
                                </select>
                                {errors.category_id && (
                                    <p className="text-xs text-red-500">{errors.category_id}</p>
                                )}
                            </div>
                        ) : (
                            /* Conditional: Assigned Asset Dropdown (If Repair, Replacement, Lost, Damage) */
                            <div className="space-y-2 rounded-lg border border-primary/20 bg-primary/5 p-4 dark:border-primary/30 dark:bg-primary/10">
                                <Label htmlFor="asset_id" className="font-semibold text-primary">
                                    Select From Assigned Assets (Assets He/She Has) *
                                </Label>
                                {assignedAssets.length > 0 ? (
                                    <>
                                        <select
                                            id="asset_id"
                                            value={data.asset_id}
                                            onChange={(e) => {
                                                const selectedId = e.target.value;
                                                const assetObj = assignedAssets.find(
                                                    (a) => String(a.id) === String(selectedId),
                                                );
                                                setData((prev) => ({
                                                    ...prev,
                                                    asset_id: selectedId,
                                                    category_id: assetObj
                                                        ? String(assetObj.category_id)
                                                        : prev.category_id,
                                                }));
                                            }}
                                            className="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-primary dark:border-neutral-700 dark:bg-neutral-900"
                                            required
                                        >
                                            <option value="">-- Select Assigned Asset --</option>
                                            {assignedAssets.map((asset) => (
                                                <option key={asset.id} value={asset.id}>
                                                    {asset.asset_name} ({asset.referenceno}) — Category: {asset.category} [{asset.condition}]
                                                </option>
                                            ))}
                                        </select>
                                        <p className="text-xs text-neutral-500">
                                            Only assets currently assigned to this employee can be selected for repair, replacement, or damage reporting.
                                        </p>
                                    </>
                                ) : (
                                    <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200">
                                        This employee currently has no assigned assets in the inventory.
                                    </div>
                                )}
                                {errors.asset_id && (
                                    <p className="text-xs text-red-500">{errors.asset_id}</p>
                                )}
                            </div>
                        )}

                        {/* Priority */}
                        <div className="space-y-2">
                            <Label htmlFor="priority">Priority *</Label>
                            <select
                                id="priority"
                                value={data.priority}
                                onChange={(e) =>
                                    setData('priority', e.target.value as typeof data.priority)
                                }
                                className="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-primary dark:border-neutral-700 dark:bg-neutral-900"
                                required
                            >
                                <option value="Low">Low</option>
                                <option value="Normal">Normal</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                            {errors.priority && (
                                <p className="text-xs text-red-500">{errors.priority}</p>
                            )}
                        </div>

                        {/* Description */}
                        <div className="space-y-2">
                            <Label htmlFor="description">Detailed Description / Reason *</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Explain why the asset is needed, what issue occurred, or describe incident for lost/damaged equipment..."
                                rows={4}
                                required
                            />
                            {errors.description && (
                                <p className="text-xs text-red-500">{errors.description}</p>
                            )}
                        </div>

                        {/* Submit Button */}
                        <div className="flex items-center justify-end gap-3 pt-4 border-t border-neutral-200 dark:border-neutral-800">
                            <Link href="/asset-requests">
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </Link>
                            <Button
                                type="submit"
                                disabled={
                                    processing ||
                                    (data.request_type !== 'New' && assignedAssets.length === 0)
                                }
                            >
                                <Send className="mr-2 h-4 w-4" />
                                {processing ? 'Submitting...' : 'Submit Request'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
