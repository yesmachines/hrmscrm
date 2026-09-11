import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    Edit,
    FolderTree,
    Laptop,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

interface CategoryItem {
    id: number;
    category: string;
    shortcode: string;
    status: number;
    assets_count: number;
}

interface PaginatedCategories {
    data: CategoryItem[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Props {
    categories: PaginatedCategories;
    filters: {
        search: string;
    };
}

export default function AssetCategoriesIndex({ categories, filters }: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [editingCategory, setEditingCategory] = useState<CategoryItem | null>(null);
    const [showCreateModal, setShowCreateModal] = useState(false);

    const createForm = useForm({
        category: '',
        shortcode: '',
        status: '1',
    });

    const editForm = useForm({
        category: '',
        shortcode: '',
        status: '1',
    });

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/asset-categories', {
            onSuccess: () => {
                createForm.reset();
                setShowCreateModal(false);
            },
        });
    };

    const handleEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingCategory) return;
        editForm.put(`/asset-categories/${editingCategory.id}`, {
            onSuccess: () => {
                setEditingCategory(null);
            },
        });
    };

    const openEdit = (cat: CategoryItem) => {
        setEditingCategory(cat);
        editForm.setData({
            category: cat.category,
            shortcode: cat.shortcode,
            status: String(cat.status),
        });
    };

    return (
        <>
            <Head title="Asset Categories" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1400px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Asset Categories"
                        description="Manage hardware, device, key, and accessories classification categories"
                    />
                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/assets">
                                <Laptop className="mr-2 size-4" />
                                Assets Inventory
                            </Link>
                        </Button>
                        <Button onClick={() => setShowCreateModal(true)}>
                            <Plus className="mr-2 size-4" />
                            Add Category
                        </Button>
                    </div>
                </div>

                {/* Create Category Modal */}
                {showCreateModal && (
                    <div className="rounded-2xl border border-border bg-white p-6 shadow-sm">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-base font-semibold text-foreground flex items-center gap-2">
                                <FolderTree className="size-5 text-primary" />
                                Add Asset Category
                            </h3>
                            <Button variant="ghost" size="sm" onClick={() => setShowCreateModal(false)}>
                                <X className="size-4" />
                            </Button>
                        </div>
                        <form onSubmit={handleCreate} className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="create_category">Category Name *</Label>
                                    <Input
                                        id="create_category"
                                        placeholder="e.g. Laptop, Keyboard, Safety Shoes"
                                        value={createForm.data.category}
                                        onChange={(e) => createForm.setData('category', e.target.value)}
                                        required
                                    />
                                    {createForm.errors.category && (
                                        <p className="text-sm text-destructive">{createForm.errors.category}</p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="create_shortcode">Shortcode *</Label>
                                    <Input
                                        id="create_shortcode"
                                        placeholder="e.g. LPT, KBD, PPE"
                                        value={createForm.data.shortcode}
                                        onChange={(e) => createForm.setData('shortcode', e.target.value.toUpperCase())}
                                        required
                                    />
                                    {createForm.errors.shortcode && (
                                        <p className="text-sm text-destructive">{createForm.errors.shortcode}</p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="create_status">Status</Label>
                                    <select
                                        id="create_status"
                                        value={createForm.data.status}
                                        onChange={(e) => createForm.setData('status', e.target.value)}
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <Button variant="outline" type="button" onClick={() => setShowCreateModal(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={createForm.processing}>
                                    Save Category
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Edit Category Modal */}
                {editingCategory && (
                    <div className="rounded-2xl border border-primary/30 bg-primary/5 p-6 shadow-sm">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-base font-semibold text-foreground flex items-center gap-2">
                                <Edit className="size-5 text-primary" />
                                Edit Category: {editingCategory.category}
                            </h3>
                            <Button variant="ghost" size="sm" onClick={() => setEditingCategory(null)}>
                                <X className="size-4" />
                            </Button>
                        </div>
                        <form onSubmit={handleEdit} className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="edit_category">Category Name *</Label>
                                    <Input
                                        id="edit_category"
                                        value={editForm.data.category}
                                        onChange={(e) => editForm.setData('category', e.target.value)}
                                        required
                                    />
                                    {editForm.errors.category && (
                                        <p className="text-sm text-destructive">{editForm.errors.category}</p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="edit_shortcode">Shortcode *</Label>
                                    <Input
                                        id="edit_shortcode"
                                        value={editForm.data.shortcode}
                                        onChange={(e) => editForm.setData('shortcode', e.target.value.toUpperCase())}
                                        required
                                    />
                                    {editForm.errors.shortcode && (
                                        <p className="text-sm text-destructive">{editForm.errors.shortcode}</p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="edit_status">Status</Label>
                                    <select
                                        id="edit_status"
                                        value={editForm.data.status}
                                        onChange={(e) => editForm.setData('status', e.target.value)}
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <Button variant="outline" type="button" onClick={() => setEditingCategory(null)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={editForm.processing}>
                                    Update Category
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Table */}
                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Category Name</th>
                                <th className="px-4 py-3 font-medium">Shortcode</th>
                                <th className="px-4 py-3 font-medium">Total Assets</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {categories.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-12 text-center text-muted-foreground">
                                        No asset categories found.
                                    </td>
                                </tr>
                            ) : (
                                categories.data.map((cat) => (
                                    <tr key={cat.id} className="hover:bg-muted/10 transition-colors">
                                        <td className="px-4 py-3 font-semibold text-foreground">
                                            {cat.category}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="font-mono text-xs bg-muted px-2 py-0.5 rounded font-semibold text-foreground">
                                                {cat.shortcode}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {cat.assets_count} items
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                                cat.status === 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'
                                            }`}>
                                                {cat.status === 1 ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button variant="ghost" size="sm" onClick={() => openEdit(cat)}>
                                                <Edit className="size-3.5 mr-1" />
                                                Edit
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {categories.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {categories.links.map((link, i) =>
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

AssetCategoriesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Assets', href: '/assets' },
        { title: 'Categories' },
    ],
};
