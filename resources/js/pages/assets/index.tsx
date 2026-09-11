import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Building2,
    Eye,
    FolderTree,
    Inbox,
    Laptop,
    Plus,
    Search,
    User,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

interface AssetRow {
    id: number;
    referenceno: string;
    asset_name: string;
    condition: string;
    status: string;
    attachment: string | null;
    category?: {
        id: number;
        category: string;
        shortcode: string;
    } | null;
    current_assignment?: {
        id: number;
        assigned_date: string;
        assignee?: {
            id: number;
            user?: {
                name: string;
            } | null;
        } | null;
    } | null;
}

interface CategoryOption {
    id: number;
    category: string;
}

interface PaginatedAssets {
    data: AssetRow[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Props {
    assets: PaginatedAssets;
    categories: CategoryOption[];
    filters: {
        category_id: string;
        status: string;
        condition: string;
        search: string;
    };
}

export default function AssetsIndex({ assets, categories, filters }: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [categoryId, setCategoryId] = useState(filters.category_id || '');
    const [status, setStatus] = useState(filters.status || '');
    const [condition, setCondition] = useState(filters.condition || '');

    const applyFilters = (newFilters: Partial<typeof filters>) => {
        router.get(
            '/assets',
            {
                search: searchTerm,
                category_id: categoryId,
                status: status,
                condition: condition,
                ...newFilters,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters({ search: searchTerm });
    };

    const getStatusBadge = (assetStatus: string) => {
        switch (assetStatus) {
            case 'Active':
                return <span className="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Active / In Use</span>;
            case 'Returned':
                return <span className="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">Returned / In Stock</span>;
            case 'Under Maintenance':
                return <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Under Maintenance</span>;
            case 'Retired':
                return <span className="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">Retired</span>;
            case 'Lost':
                return <span className="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800">Lost</span>;
            default:
                return <span className="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">{assetStatus}</span>;
        }
    };

    const getConditionBadge = (cond: string) => {
        switch (cond) {
            case 'New':
            case 'Excellent':
                return <span className="text-emerald-700 font-medium">{cond}</span>;
            case 'Good':
            case 'Fair':
                return <span className="text-foreground font-medium">{cond}</span>;
            case 'Damaged':
            case 'Needs Repair':
                return <span className="text-rose-700 font-semibold">{cond}</span>;
            default:
                return <span>{cond}</span>;
        }
    };

    return (
        <>
            <Head title="Assets Inventory" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Header */}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Assets Inventory"
                        description="Track and assign company hardware, devices, keys, equipment, and accessories"
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/asset-requests">
                                <Inbox className="mr-2 size-4" />
                                Review Requests
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/asset-categories">
                                <FolderTree className="mr-2 size-4" />
                                Categories
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href="/assets/create">
                                <Plus className="mr-2 size-4" />
                                Add New Asset
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Filters */}
                <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border bg-white p-4 shadow-sm">
                    <form onSubmit={handleSearch} className="flex flex-1 items-center gap-2 max-w-md">
                        <div className="relative w-full">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Search by asset name, reference no, details..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit" variant="secondary" size="sm">
                            Search
                        </Button>
                    </form>

                    <div className="flex flex-wrap items-center gap-3">
                        <select
                            value={categoryId}
                            onChange={(e) => {
                                setCategoryId(e.target.value);
                                applyFilters({ category_id: e.target.value });
                            }}
                            className="h-9 rounded-md border border-input bg-background px-3 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="">All Categories</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.category}
                                </option>
                            ))}
                        </select>

                        <select
                            value={status}
                            onChange={(e) => {
                                setStatus(e.target.value);
                                applyFilters({ status: e.target.value });
                            }}
                            className="h-9 rounded-md border border-input bg-background px-3 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="">All Statuses</option>
                            <option value="Active">Active / In Use</option>
                            <option value="Returned">Returned / In Stock</option>
                            <option value="Under Maintenance">Under Maintenance</option>
                            <option value="Retired">Retired</option>
                            <option value="Lost">Lost</option>
                        </select>

                        <select
                            value={condition}
                            onChange={(e) => {
                                setCondition(e.target.value);
                                applyFilters({ condition: e.target.value });
                            }}
                            className="h-9 rounded-md border border-input bg-background px-3 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="">All Conditions</option>
                            <option value="New">New</option>
                            <option value="Excellent">Excellent</option>
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Damaged">Damaged</option>
                            <option value="Needs Repair">Needs Repair</option>
                        </select>
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border bg-muted/40 text-muted-foreground text-xs uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Ref No & Asset Name</th>
                                    <th className="px-4 py-3 font-medium">Category</th>
                                    <th className="px-4 py-3 font-medium">Condition</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="px-4 py-3 font-medium">Assigned To</th>
                                    <th className="px-4 py-3 text-right font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {assets.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-12 text-center text-muted-foreground">
                                            No assets found in inventory.
                                        </td>
                                    </tr>
                                ) : (
                                    assets.data.map((asset) => (
                                        <tr key={asset.id} className="hover:bg-muted/10 transition-colors">
                                            <td className="px-4 py-3.5">
                                                <div className="font-semibold text-foreground flex items-center gap-2">
                                                    <Laptop className="size-4 text-primary shrink-0" />
                                                    <span>{asset.asset_name}</span>
                                                </div>
                                                <div className="text-xs font-mono text-muted-foreground mt-0.5">
                                                    {asset.referenceno}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5 font-medium text-foreground">
                                                {asset.category?.category}
                                            </td>
                                            <td className="px-4 py-3.5 text-xs">
                                                {getConditionBadge(asset.condition)}
                                            </td>
                                            <td className="px-4 py-3.5 whitespace-nowrap">
                                                {getStatusBadge(asset.status)}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {asset.current_assignment?.assignee?.user?.name ? (
                                                    <div className="flex items-center gap-1.5 text-xs font-medium text-foreground">
                                                        <User className="size-3.5 text-muted-foreground shrink-0" />
                                                        <span>{asset.current_assignment.assignee.user.name}</span>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground italic">Unassigned (In Stock)</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 text-right whitespace-nowrap">
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={`/assets/${asset.id}`}>
                                                        <Eye className="mr-1.5 size-3.5" />
                                                        Manage
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pagination */}
                {assets.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {assets.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={`rounded-md border px-3 py-1.5 text-sm ${
                                        link.active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-white text-foreground hover:bg-muted'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={i}
                                    className="rounded-md border border-border px-3 py-1.5 text-sm text-muted-foreground opacity-50"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            )
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

AssetsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Assets' },
    ],
};
