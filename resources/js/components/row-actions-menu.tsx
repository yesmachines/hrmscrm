import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type Props = {
    viewHref: NonNullable<InertiaLinkProps['href']>;
    editHref: NonNullable<InertiaLinkProps['href']>;
    destroyForm: {
        action: string;
        method: string;
    };
    deleteTitle?: string;
    deleteDescription?: string;
    deleteConfirmLabel?: string;
};

export default function RowActionsMenu({
    viewHref,
    editHref,
    destroyForm,
    deleteTitle,
    deleteDescription,
    deleteConfirmLabel,
}: Props) {
    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <div className="flex justify-end">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        type="button"
                        className="size-8"
                    >
                        <MoreHorizontal className="size-4" />
                        <span className="sr-only">Open actions</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-40">
                    <DropdownMenuItem asChild>
                        <Link href={viewHref} prefetch>
                            View
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href={editHref} prefetch>
                            Edit
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => setDeleteOpen(true)}
                    >
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <DeleteConfirmDialog
                form={destroyForm}
                title={deleteTitle}
                description={deleteDescription}
                confirmLabel={deleteConfirmLabel}
                trigger={null}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </div>
    );
}
