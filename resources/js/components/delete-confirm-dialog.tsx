import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    /** Spreads from Wayfinder `Controller.destroy.form(id)` */
    form: {
        action: string;
        method: string;
    };
    title?: string;
    description?: string;
    confirmLabel?: string;
    triggerLabel?: string;
    trigger?: ReactNode;
};

export default function DeleteConfirmDialog({
    form,
    title = 'Are you sure?',
    description = 'This action cannot be undone. This will permanently delete the record.',
    confirmLabel = 'Delete',
    triggerLabel = 'Delete',
    trigger,
}: Props) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button variant="destructive" type="button">
                        {triggerLabel}
                    </Button>
                )}
            </DialogTrigger>

            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>

                <Form
                    action={form.action}
                    method={form.method as 'get' | 'post' | 'put' | 'patch' | 'delete'}
                    className="space-y-6"
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    disabled={processing}
                                >
                                    Cancel
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {confirmLabel}
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
