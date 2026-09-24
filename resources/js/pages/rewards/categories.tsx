import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    Award,
    CheckCircle2,
    Edit,
    FolderTree,
    Plus,
    Search,
    Trash2,
    X,
    XCircle,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';

interface RewardCategoryItem {
    id: number;
    reward_name: string;
    short_code: string;
    status: number;
    details: string | null;
    rewards_count?: number;
    created_at?: string;
    updated_at?: string;
}

interface Props {
    categories: RewardCategoryItem[];
}

export default function RewardCategoriesIndex({ categories }: Props) {
    const [searchTerm, setSearchTerm] = useState('');
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [editingCategory, setEditingCategory] = useState<RewardCategoryItem | null>(null);

    const createForm = useForm({
        reward_name: '',
        short_code: '',
        status: 1,
        details: '',
    });

    const editForm = useForm({
        reward_name: '',
        short_code: '',
        status: 1,
        details: '',
    });

    const filteredCategories = categories.filter((cat) => {
        if (!searchTerm) return true;
        const q = searchTerm.toLowerCase();
        return (
            cat.reward_name.toLowerCase().includes(q) ||
            cat.short_code.toLowerCase().includes(q) ||
            (cat.details && cat.details.toLowerCase().includes(q))
        );
    });

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/reward-categories', {
            onSuccess: () => {
                setCreateDialogOpen(false);
                createForm.reset();
            },
        });
    };

    const handleOpenEdit = (category: RewardCategoryItem) => {
        setEditingCategory(category);
        editForm.setData({
            reward_name: category.reward_name,
            short_code: category.short_code,
            status: category.status,
            details: category.details || '',
        });
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingCategory) return;

        editForm.put(`/reward-categories/${editingCategory.id}`, {
            onSuccess: () => {
                setEditingCategory(null);
                editForm.reset();
            },
        });
    };

    const handleDelete = (category: RewardCategoryItem) => {
        if (
            confirm(
                `Are you sure you want to delete category "${category.reward_name}"?`
            )
        ) {
            editForm.delete(`/reward-categories/${category.id}`);
        }
    };

    return (
        <>
            <Head title="Reward Categories" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1400px] flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <Heading
                            title="Reward Categories & Types"
                            description="Define employee reward categories, incentive codes, criteria, and active statuses"
                        />
                    </div>
                    <div className="flex items-center gap-3">
                        <Link href="/rewards">
                            <Button variant="outline" className="gap-2">
                                <Award className="size-4" />
                                Back to Claims
                            </Button>
                        </Link>
                        <Button
                            onClick={() => {
                                createForm.reset();
                                setCreateDialogOpen(true);
                            }}
                            className="gap-2"
                        >
                            <Plus className="size-4" />
                            Add Category
                        </Button>
                    </div>
                </div>

                {/* Search Bar */}
                <div className="flex items-center gap-3">
                    <div className="relative flex-1 max-w-md">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="text"
                            placeholder="Filter categories by name or code..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="pl-9 pr-8"
                        />
                        {searchTerm && (
                            <button
                                type="button"
                                onClick={() => setSearchTerm('')}
                                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X className="size-4" />
                            </button>
                        )}
                    </div>
                </div>

                {/* Categories Table */}
                <div className="rounded-xl border border-border/60 bg-card shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border/60 bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-3.5 font-semibold">Category Name</th>
                                    <th className="px-5 py-3.5 font-semibold">Short Code</th>
                                    <th className="px-5 py-3.5 font-semibold">Details / Criteria</th>
                                    <th className="px-5 py-3.5 font-semibold text-center">Claims Count</th>
                                    <th className="px-5 py-3.5 font-semibold">Status</th>
                                    <th className="px-5 py-3.5 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/40">
                                {filteredCategories.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-12 text-center">
                                            <div className="mx-auto flex flex-col items-center justify-center text-muted-foreground">
                                                <FolderTree className="size-10 stroke-[1.5] text-muted-foreground/50 mb-2" />
                                                <p className="text-base font-medium text-foreground">
                                                    No categories found
                                                </p>
                                                <p className="text-xs text-muted-foreground max-w-sm mt-1">
                                                    Create your first reward category like Employee of the Month, Spot Award, or Innovation.
                                                </p>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => setCreateDialogOpen(true)}
                                                    className="mt-4 gap-1.5"
                                                >
                                                    <Plus className="size-3.5" />
                                                    Add First Category
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    filteredCategories.map((cat) => (
                                        <tr
                                            key={cat.id}
                                            className="transition-colors hover:bg-muted/30"
                                        >
                                            <td className="px-5 py-3.5">
                                                <div className="flex items-center gap-2">
                                                    <Award className="size-4 text-primary" />
                                                    <span className="font-semibold text-foreground">
                                                        {cat.reward_name}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-5 py-3.5">
                                                <Badge
                                                    variant="secondary"
                                                    className="font-mono text-xs uppercase"
                                                >
                                                    {cat.short_code}
                                                </Badge>
                                            </td>
                                            <td className="px-5 py-3.5 text-xs text-muted-foreground max-w-md truncate">
                                                {cat.details || '—'}
                                            </td>
                                            <td className="px-5 py-3.5 text-center font-medium">
                                                <Badge variant="outline" className="text-xs">
                                                    {cat.rewards_count ?? 0} claims
                                                </Badge>
                                            </td>
                                            <td className="px-5 py-3.5">
                                                {cat.status === 1 ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-emerald-400 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 font-medium"
                                                    >
                                                        Active
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-muted bg-muted text-muted-foreground font-medium"
                                                    >
                                                        Inactive
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleOpenEdit(cat)}
                                                        className="h-8 px-2 text-xs"
                                                    >
                                                        <Edit className="size-3.5" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDelete(cat)}
                                                        disabled={cat.rewards_count ? cat.rewards_count > 0 : false}
                                                        className="h-8 px-2 text-xs text-destructive hover:bg-destructive/10 disabled:opacity-30"
                                                        title={
                                                            cat.rewards_count && cat.rewards_count > 0
                                                                ? 'Cannot delete category with associated claims'
                                                                : 'Delete category'
                                                        }
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Modal: Create Reward Category */}
            <Dialog open={createDialogOpen} onOpenChange={setCreateDialogOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Plus className="size-5 text-primary" />
                            Add Reward Category
                        </DialogTitle>
                        <DialogDescription>
                            Configure a new reward category for employee nominations and claims.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleCreateSubmit} className="space-y-4 pt-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="create_reward_name">
                                Category Name <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="create_reward_name"
                                placeholder="e.g. Employee of the Month"
                                value={createForm.data.reward_name}
                                onChange={(e) => createForm.setData('reward_name', e.target.value)}
                                required
                            />
                            {createForm.errors.reward_name && (
                                <p className="text-xs text-destructive">{createForm.errors.reward_name}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="create_short_code">
                                Short Code <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="create_short_code"
                                placeholder="e.g. EOM, SPOT, INNOV"
                                value={createForm.data.short_code}
                                onChange={(e) =>
                                    createForm.setData('short_code', e.target.value.toUpperCase())
                                }
                                required
                            />
                            {createForm.errors.short_code && (
                                <p className="text-xs text-destructive">{createForm.errors.short_code}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="create_status">Status</Label>
                            <Select
                                value={String(createForm.data.status)}
                                onValueChange={(val) => createForm.setData('status', Number(val))}
                            >
                                <SelectTrigger id="create_status">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="0">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="create_details">Details / Eligibility Criteria</Label>
                            <Textarea
                                id="create_details"
                                rows={3}
                                placeholder="Describe the criteria, nomination guidelines, or reward amount limits..."
                                value={createForm.data.details}
                                onChange={(e) => createForm.setData('details', e.target.value)}
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCreateDialogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createForm.processing}>
                                {createForm.processing ? 'Creating...' : 'Create Category'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal: Edit Reward Category */}
            <Dialog
                open={!!editingCategory}
                onOpenChange={(open) => {
                    if (!open) setEditingCategory(null);
                }}
            >
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Edit className="size-5 text-primary" />
                            Edit Reward Category
                        </DialogTitle>
                        <DialogDescription>
                            Modify reward category details, code, or active status.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleEditSubmit} className="space-y-4 pt-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="edit_reward_name">
                                Category Name <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="edit_reward_name"
                                value={editForm.data.reward_name}
                                onChange={(e) => editForm.setData('reward_name', e.target.value)}
                                required
                            />
                            {editForm.errors.reward_name && (
                                <p className="text-xs text-destructive">{editForm.errors.reward_name}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="edit_short_code">
                                Short Code <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="edit_short_code"
                                value={editForm.data.short_code}
                                onChange={(e) =>
                                    editForm.setData('short_code', e.target.value.toUpperCase())
                                }
                                required
                            />
                            {editForm.errors.short_code && (
                                <p className="text-xs text-destructive">{editForm.errors.short_code}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="edit_status">Status</Label>
                            <Select
                                value={String(editForm.data.status)}
                                onValueChange={(val) => editForm.setData('status', Number(val))}
                            >
                                <SelectTrigger id="edit_status">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="0">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="edit_details">Details / Eligibility Criteria</Label>
                            <Textarea
                                id="edit_details"
                                rows={3}
                                value={editForm.data.details}
                                onChange={(e) => editForm.setData('details', e.target.value)}
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditingCategory(null)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={editForm.processing}>
                                {editForm.processing ? 'Saving...' : 'Save Changes'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

RewardCategoriesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Rewards',
            href: '/rewards',
        },
        {
            title: 'Categories',
            href: '/reward-categories',
        },
    ],
};
