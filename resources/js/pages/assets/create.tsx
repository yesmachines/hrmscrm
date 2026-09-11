import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    FileUp,
    Laptop,
    Save,
    UserCheck,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';

interface CategoryOption {
    id: number;
    category: string;
    shortcode: string;
}

interface EmployeeOption {
    id: number;
    user_id: number;
    emp_num: string;
    employee_code: string | null;
    designation: string | null;
    user?: {
        name: string;
    } | null;
}

interface Props {
    categories: CategoryOption[];
    employees: EmployeeOption[];
}

export default function AssetsCreate({ categories, employees }: Props) {
    const [directAssign, setDirectAssign] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        category_id: '',
        referenceno: '',
        asset_name: '',
        details: '',
        condition: 'New',
        status: 'Active',
        attachment: null as File | null,
        assigned_to: '',
        assigned_date: new Date().toISOString().split('T')[0],
        assignment_note: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/assets');
    };

    const handleCategoryChange = (catId: string) => {
        setData('category_id', catId);
        const cat = categories.find((c) => String(c.id) === catId);
        if (cat && !data.referenceno) {
            const randomCode = Math.floor(1000 + Math.random() * 9000);
            setData('referenceno', `${cat.shortcode}-${new Date().getFullYear()}-${randomCode}`);
        }
    };

    return (
        <>
            <Head title="Add New Asset" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1200px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href="/assets">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <Heading
                        title="Add Asset to Inventory"
                        description="Register a new hardware item, device, key, or equipment into the company registry"
                    />
                </div>

                <div className="rounded-2xl border border-border bg-white p-6 shadow-sm">
                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            {/* Category */}
                            <div className="space-y-2">
                                <Label htmlFor="category_id">Asset Category *</Label>
                                <select
                                    id="category_id"
                                    value={data.category_id}
                                    onChange={(e) => handleCategoryChange(e.target.value)}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    required
                                >
                                    <option value="">-- Select Category --</option>
                                    {categories.map((cat) => (
                                        <option key={cat.id} value={cat.id}>
                                            {cat.category} ({cat.shortcode})
                                        </option>
                                    ))}
                                </select>
                                {errors.category_id && (
                                    <p className="text-sm text-destructive">{errors.category_id}</p>
                                )}
                            </div>

                            {/* Reference Number */}
                            <div className="space-y-2">
                                <Label htmlFor="referenceno">Asset Unique Number / Barcode *</Label>
                                <Input
                                    id="referenceno"
                                    placeholder="e.g. LPT-2026-8742 or TAG-00123"
                                    value={data.referenceno}
                                    onChange={(e) => setData('referenceno', e.target.value)}
                                    required
                                />
                                {errors.referenceno && (
                                    <p className="text-sm text-destructive">{errors.referenceno}</p>
                                )}
                            </div>

                            {/* Asset Name */}
                            <div className="space-y-2">
                                <Label htmlFor="asset_name">Asset Name / Brand & Model *</Label>
                                <Input
                                    id="asset_name"
                                    placeholder="e.g. MacBook Pro 14 M3, Dell UltraSharp 27 Monitor"
                                    value={data.asset_name}
                                    onChange={(e) => setData('asset_name', e.target.value)}
                                    required
                                />
                                {errors.asset_name && (
                                    <p className="text-sm text-destructive">{errors.asset_name}</p>
                                )}
                            </div>

                            {/* Condition */}
                            <div className="space-y-2">
                                <Label htmlFor="condition">Asset Condition *</Label>
                                <select
                                    id="condition"
                                    value={data.condition}
                                    onChange={(e) => setData('condition', e.target.value)}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option value="New">New</option>
                                    <option value="Excellent">Excellent</option>
                                    <option value="Good">Good</option>
                                    <option value="Fair">Fair</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Needs Repair">Needs Repair</option>
                                </select>
                            </div>

                            {/* Status */}
                            <div className="space-y-2">
                                <Label htmlFor="status">Initial Status *</Label>
                                <select
                                    id="status"
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option value="Active">Active / Ready</option>
                                    <option value="Returned">Returned / In Stock</option>
                                    <option value="Under Maintenance">Under Maintenance</option>
                                    <option value="Retired">Retired</option>
                                    <option value="Lost">Lost</option>
                                </select>
                            </div>

                            {/* Attachment */}
                            <div className="space-y-2">
                                <Label htmlFor="attachment" className="flex items-center gap-1.5">
                                    <FileUp className="size-4 text-muted-foreground" />
                                    Photo / Invoice / Specs Document
                                </Label>
                                <Input
                                    id="attachment"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    onChange={(e) => setData('attachment', e.target.files?.[0] || null)}
                                />
                                {errors.attachment && (
                                    <p className="text-sm text-destructive">{errors.attachment}</p>
                                )}
                            </div>

                            {/* Details */}
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="details">Specifications & Serial Number Notes</Label>
                                <Textarea
                                    id="details"
                                    rows={3}
                                    placeholder="Serial number, IMEI, MAC address, RAM/storage specs, accessories included..."
                                    value={data.details}
                                    onChange={(e) => setData('details', e.target.value)}
                                />
                            </div>
                        </div>

                        {/* Direct Assignment Option */}
                        <div className="border-t border-border pt-6">
                            <div className="flex items-center gap-2 mb-4">
                                <input
                                    type="checkbox"
                                    id="directAssignToggle"
                                    checked={directAssign}
                                    onChange={(e) => {
                                        setDirectAssign(e.target.checked);
                                        if (!e.target.checked) {
                                            setData('assigned_to', '');
                                        }
                                    }}
                                    className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                />
                                <Label htmlFor="directAssignToggle" className="cursor-pointer font-semibold flex items-center gap-1.5">
                                    <UserCheck className="size-4 text-primary" />
                                    Assign directly to an employee right now
                                </Label>
                            </div>

                            {directAssign && (
                                <div className="rounded-xl border border-primary/20 bg-primary/5 p-4 space-y-4">
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="assigned_to">Assign to Employee *</Label>
                                            <select
                                                id="assigned_to"
                                                value={data.assigned_to}
                                                onChange={(e) => setData('assigned_to', e.target.value)}
                                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                                required={directAssign}
                                            >
                                                <option value="">-- Select Employee --</option>
                                                {employees.map((emp) => (
                                                    <option key={emp.id} value={emp.id}>
                                                        {emp.user?.name} ({emp.employee_code || emp.emp_num})
                                                        {emp.designation ? ` - ${emp.designation}` : ''}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="assigned_date">Assigned Date *</Label>
                                            <Input
                                                id="assigned_date"
                                                type="date"
                                                value={data.assigned_date}
                                                onChange={(e) => setData('assigned_date', e.target.value)}
                                                required={directAssign}
                                            />
                                        </div>

                                        <div className="space-y-2 sm:col-span-2">
                                            <Label htmlFor="assignment_note">Condition & Handover Notes</Label>
                                            <Input
                                                id="assignment_note"
                                                placeholder="e.g. Handed over with charger, power cable, and case in pristine condition."
                                                value={data.assignment_note}
                                                onChange={(e) => setData('assignment_note', e.target.value)}
                                            />
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Actions */}
                        <div className="flex items-center justify-end gap-3 pt-4 border-t border-border">
                            <Button variant="outline" type="button" asChild>
                                <Link href="/assets">Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={processing} className="min-w-[140px]">
                                <Save className="mr-2 size-4" />
                                {processing ? 'Saving...' : 'Save Asset'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

AssetsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Assets', href: '/assets' },
        { title: 'Add Asset' },
    ],
};
