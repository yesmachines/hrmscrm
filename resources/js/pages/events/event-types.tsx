import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Calendar,
    Layers,
    Pencil,
    Plus,
    Shield,
    Sparkles,
    Tag,
    Trash2,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import { dashboard } from '@/routes';

interface EventTypeRow {
    id: number;
    event_code: string;
    event_name: string;
    event_source: 'system' | 'manual';
    priority: number;
    icon_path: string | null;
    status: number;
    events_count?: number;
    created_at?: string;
}

interface Props {
    eventTypes: EventTypeRow[];
}

export default function EventTypesIndex({ eventTypes }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingType, setEditingType] = useState<EventTypeRow | null>(null);

    const [formCode, setFormCode] = useState('');
    const [formName, setFormName] = useState('');
    const [formSource, setFormSource] = useState<'system' | 'manual'>('manual');
    const [formPriority, setFormPriority] = useState('10');
    const [formStatus, setFormStatus] = useState<'1' | '0'>('1');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleOpenCreate = () => {
        setEditingType(null);
        setFormCode('');
        setFormName('');
        setFormSource('manual');
        setFormPriority('10');
        setFormStatus('1');
        setDialogOpen(true);
    };

    const handleOpenEdit = (t: EventTypeRow) => {
        setEditingType(t);
        setFormCode(t.event_code);
        setFormName(t.event_name);
        setFormSource(t.event_source);
        setFormPriority(t.priority.toString());
        setFormStatus(t.status.toString() as '1' | '0');
        setDialogOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        const payload = {
            event_code: formCode,
            event_name: formName,
            event_source: formSource,
            priority: parseInt(formPriority, 10) || 0,
            status: parseInt(formStatus, 10),
        };

        if (editingType) {
            router.put(`/event-types/${editingType.id}`, payload, {
                onSuccess: () => {
                    setIsSubmitting(false);
                    setDialogOpen(false);
                },
                onError: () => setIsSubmitting(false),
            });
        } else {
            router.post('/event-types', payload, {
                onSuccess: () => {
                    setIsSubmitting(false);
                    setDialogOpen(false);
                },
                onError: () => setIsSubmitting(false),
            });
        }
    };

    const handleDelete = (t: EventTypeRow) => {
        if (t.event_source === 'system') {
            alert('System event types cannot be deleted.');
            return;
        }

        if (t.events_count && t.events_count > 0) {
            alert('Cannot delete an event type that has attached events.');
            return;
        }

        if (confirm(`Delete event type "${t.event_name}"?`)) {
            router.delete(`/event-types/${t.id}`);
        }
    };

    return (
        <>
            <Head title="Event Types" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <Heading
                        title="Event Types"
                        description="Configure system generated and manual event categories for the corporate calendar"
                    />
                    <div className="flex items-center gap-3">
                        <Button
                            onClick={() => router.get('/events')}
                            variant="outline"
                            className="text-xs"
                        >
                            <Calendar className="mr-1.5 size-3.5" />
                            Events Calendar
                        </Button>
                        <Button
                            onClick={handleOpenCreate}
                            className="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold text-xs shadow-sm"
                        >
                            <Plus className="mr-1.5 size-4" />
                            Add Event Type
                        </Button>
                    </div>
                </div>

                {/* Table Card */}
                <Card className="border-border/50 bg-card/80 backdrop-blur-sm shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border/60 bg-muted/30 text-xs uppercase font-semibold text-muted-foreground">
                                <tr>
                                    <th className="px-6 py-4">Event Type</th>
                                    <th className="px-6 py-4">Code</th>
                                    <th className="px-6 py-4">Source</th>
                                    <th className="px-6 py-4">Priority</th>
                                    <th className="px-6 py-4">Events</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/40">
                                {eventTypes.map((t) => (
                                    <tr key={t.id} className="hover:bg-muted/20 transition-colors">
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-3">
                                                <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                    <Tag className="size-4" />
                                                </div>
                                                <span className="font-semibold text-foreground">
                                                    {t.event_name}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 font-mono text-xs text-muted-foreground">
                                            {t.event_code}
                                        </td>
                                        <td className="px-6 py-4">
                                            <Badge
                                                variant={t.event_source === 'system' ? 'secondary' : 'outline'}
                                                className="text-[11px] capitalize"
                                            >
                                                {t.event_source === 'system' && (
                                                    <Shield className="mr-1 size-3 text-secondary" />
                                                )}
                                                {t.event_source}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-4 text-xs text-muted-foreground">
                                            {t.priority}
                                        </td>
                                        <td className="px-6 py-4">
                                            <Badge variant="outline" className="text-xs font-semibold">
                                                {t.events_count ?? 0}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-4">
                                            <Badge
                                                variant={t.status === 1 ? 'default' : 'outline'}
                                                className="text-[11px]"
                                            >
                                                {t.status === 1 ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleOpenEdit(t)}
                                                    className="size-8 p-0"
                                                >
                                                    <Pencil className="size-3.5 text-muted-foreground hover:text-foreground" />
                                                </Button>
                                                {t.event_source !== 'system' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDelete(t)}
                                                        className="size-8 p-0 text-destructive hover:bg-destructive/10"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            {/* Dialog for Add / Edit */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold flex items-center gap-2">
                            <Tag className="size-4 text-primary" />
                            {editingType ? 'Edit Event Type' : 'Add Event Type'}
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Configure event classification attributes and prioritization.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="code" className="text-xs font-semibold">
                                Event Code *
                            </Label>
                            <Input
                                id="code"
                                placeholder="e.g. MEETING, WORKSHOP, EXPO"
                                value={formCode}
                                onChange={(e) => setFormCode(e.target.value.toUpperCase())}
                                required
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="name" className="text-xs font-semibold">
                                Event Name *
                            </Label>
                            <Input
                                id="name"
                                placeholder="e.g. Workshops & Training"
                                value={formName}
                                onChange={(e) => setFormName(e.target.value)}
                                required
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label className="text-xs font-semibold">Source</Label>
                                <Select
                                    value={formSource}
                                    onValueChange={(val: 'system' | 'manual') => setFormSource(val)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="manual">Manual</SelectItem>
                                        <SelectItem value="system">System Generated</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="priority" className="text-xs font-semibold">
                                    Priority
                                </Label>
                                <Input
                                    id="priority"
                                    type="number"
                                    min="0"
                                    value={formPriority}
                                    onChange={(e) => setFormPriority(e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label className="text-xs font-semibold">Status</Label>
                            <Select
                                value={formStatus}
                                onValueChange={(val: '1' | '0') => setFormStatus(val)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="0">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <DialogFooter className="pt-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDialogOpen(false)}
                                disabled={isSubmitting}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={isSubmitting}
                                className="bg-primary text-primary-foreground font-semibold"
                            >
                                {isSubmitting ? 'Saving...' : editingType ? 'Update' : 'Create'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

EventTypesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Events',
            href: '/events',
        },
        {
            title: 'Event Types',
            href: '/event-types',
        },
    ],
};
