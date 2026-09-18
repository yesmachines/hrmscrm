import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Calendar as CalendarIcon,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Clock,
    ExternalLink,
    FileText,
    Filter,
    Layers,
    List,
    MapPin,
    Plus,
    Search,
    Sparkles,
    Trash2,
    X,
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
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';

interface EventTypeItem {
    id: number;
    event_code: string;
    event_name: string;
    event_source: 'system' | 'manual';
    priority: number;
}

interface OrganisationItem {
    id: number;
    org_name: string;
    short_name?: string;
}

interface EventItem {
    id: number;
    event_type_id: number;
    organisation_id: number | null;
    title: string;
    description: string | null;
    start_datetime: string;
    end_datetime: string | null;
    external_link: string | null;
    status: 'draft' | 'published' | 'cancelled';
    created_by: number | null;
    employee_id: number | null;
    file_path: string | null;
    show_dashboard: boolean;
    eventType?: EventTypeItem;
    organisation?: OrganisationItem | null;
    employee?: {
        id: number;
        user?: { name: string } | null;
    } | null;
    creator?: {
        id: number;
        name: string;
    } | null;
}

interface PaginatedEvents {
    data: EventItem[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    events: PaginatedEvents;
    eventTypes: EventTypeItem[];
    organisations: OrganisationItem[];
    filters: {
        search?: string;
        event_type_id?: string;
        organisation_id?: string;
        status?: string;
        date_filter?: string;
        month?: string;
        year?: string;
        view?: 'calendar' | 'list';
    };
}

export default function EventsIndex({
    events,
    eventTypes,
    organisations,
    filters,
}: Props) {
    const [viewMode, setViewMode] = useState<'calendar' | 'list'>(
        filters.view === 'list' ? 'list' : 'calendar'
    );
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [typeFilter, setTypeFilter] = useState(filters.event_type_id || 'all');
    const [dateFilter, setDateFilter] = useState(filters.date_filter || 'all');

    // Calendar state
    const today = new Date();
    const [currentMonth, setCurrentMonth] = useState(
        filters.month ? parseInt(filters.month, 10) - 1 : today.getMonth()
    );
    const [currentYear, setCurrentYear] = useState(
        filters.year ? parseInt(filters.year, 10) : today.getFullYear()
    );

    // Dialog state
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [selectedEvent, setSelectedEvent] = useState<EventItem | null>(null);
    const [detailDialogOpen, setDetailDialogOpen] = useState(false);

    // Form state
    const [formTitle, setFormTitle] = useState('');
    const [formTypeId, setFormTypeId] = useState<string>('');
    const [formOrgId, setFormOrgId] = useState<string>('none');
    const [formStart, setFormStart] = useState('');
    const [formEnd, setFormEnd] = useState('');
    const [formLink, setFormLink] = useState('');
    const [formStatus, setFormStatus] = useState<'draft' | 'published' | 'cancelled'>('published');
    const [formShowDashboard, setFormShowDashboard] = useState(true);
    const [formDescription, setFormDescription] = useState('');
    const [formFile, setFormFile] = useState<File | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const applyFilters = (newFilters: Record<string, string>) => {
        const query: Record<string, string> = {
            view: viewMode,
            search: searchTerm,
            event_type_id: typeFilter === 'all' ? '' : typeFilter,
            date_filter: dateFilter === 'all' ? '' : dateFilter,
            ...newFilters,
        };

        // clean empty keys
        Object.keys(query).forEach((key) => {
            if (!query[key]) delete query[key];
        });

        router.get('/events', query, { preserveState: true, replace: true });
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters({ search: searchTerm });
    };

    const handlePrevMonth = () => {
        if (currentMonth === 0) {
            setCurrentMonth(11);
            setCurrentYear((y) => y - 1);
        } else {
            setCurrentMonth((m) => m - 1);
        }
    };

    const handleNextMonth = () => {
        if (currentMonth === 11) {
            setCurrentMonth(0);
            setCurrentYear((y) => y + 1);
        } else {
            setCurrentMonth((m) => m + 1);
        }
    };

    const handleToday = () => {
        setCurrentMonth(today.getMonth());
        setCurrentYear(today.getFullYear());
    };

    const resetForm = () => {
        setFormTitle('');
        setFormTypeId(eventTypes[0]?.id.toString() || '');
        setFormOrgId('none');
        setFormStart('');
        setFormEnd('');
        setFormLink('');
        setFormStatus('published');
        setFormShowDashboard(true);
        setFormDescription('');
        setFormFile(null);
    };

    const handleOpenCreate = (prefillDate?: string) => {
        resetForm();
        if (prefillDate) {
            setFormStart(`${prefillDate}T09:00`);
            setFormEnd(`${prefillDate}T10:00`);
        }
        setCreateDialogOpen(true);
    };

    const handleCreateEvent = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        const formData = new FormData();
        formData.append('title', formTitle);
        formData.append('event_type_id', formTypeId);
        if (formOrgId && formOrgId !== 'none') {
            formData.append('organisation_id', formOrgId);
        }
        formData.append('start_datetime', formStart);
        if (formEnd) {
            formData.append('end_datetime', formEnd);
        }
        if (formLink) {
            formData.append('external_link', formLink);
        }
        formData.append('status', formStatus);
        formData.append('show_dashboard', formShowDashboard ? '1' : '0');
        if (formDescription) {
            formData.append('description', formDescription);
        }
        if (formFile) {
            formData.append('file', formFile);
        }

        router.post('/events', formData, {
            onSuccess: () => {
                setIsSubmitting(false);
                setCreateDialogOpen(false);
                resetForm();
            },
            onError: () => {
                setIsSubmitting(false);
            },
        });
    };

    const handleDeleteEvent = (id: number) => {
        if (confirm('Are you sure you want to delete this event?')) {
            router.delete(`/events/${id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    if (detailDialogOpen) setDetailDialogOpen(false);
                },
            });
        }
    };

    // Calendar generation helpers
    const firstDayIndex = new Date(currentYear, currentMonth, 1).getDay();
    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
    const daysInPrevMonth = new Date(currentYear, currentMonth, 0).getDate();

    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    const getEventsForDay = (day: number) => {
        const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        return events.data.filter((ev) => {
            const evDate = ev.start_datetime.substring(0, 10);
            return evDate === dateStr;
        });
    };

    const formatTime = (iso: string) => {
        try {
            const d = new Date(iso);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch {
            return '';
        }
    };

    const formatDate = (iso: string) => {
        try {
            const d = new Date(iso);
            return d.toLocaleDateString([], {
                weekday: 'short',
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            });
        } catch {
            return iso;
        }
    };

    return (
        <>
            <Head title="Events Calendar" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <Heading
                            title="Events & Company Calendar"
                            description="Manage meetings, trainings, corporate workshops, company announcements, and expos"
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="inline-flex rounded-xl bg-muted/60 p-1 border border-border/40">
                            <button
                                type="button"
                                onClick={() => {
                                    setViewMode('calendar');
                                    applyFilters({ view: 'calendar' });
                                }}
                                className={`flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${
                                    viewMode === 'calendar'
                                        ? 'bg-card text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                <CalendarIcon className="size-3.5" />
                                Calendar
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    setViewMode('list');
                                    applyFilters({ view: 'list' });
                                }}
                                className={`flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${
                                    viewMode === 'list'
                                        ? 'bg-card text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                <List className="size-3.5" />
                                List
                            </button>
                        </div>

                        <Button
                            onClick={() => handleOpenCreate()}
                            className="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold shadow-sm"
                        >
                            <Plus className="mr-1.5 size-4" />
                            Create Event
                        </Button>
                    </div>
                </div>

                {/* Filter Bar */}
                <Card className="p-4 border-border/50 bg-card/60 backdrop-blur-sm shadow-sm">
                    <div className="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
                        {/* Search Input */}
                        <form onSubmit={handleSearchSubmit} className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Search events by title, description or type..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-9 bg-background/80"
                            />
                        </form>

                        <div className="flex flex-wrap items-center gap-3">
                            {/* Date Filter Tabs */}
                            <div className="flex items-center gap-1 rounded-lg bg-muted/60 p-1 border border-border/40 text-xs">
                                {['all', 'today', 'upcoming', 'past'].map((tab) => (
                                    <button
                                        key={tab}
                                        type="button"
                                        onClick={() => {
                                            setDateFilter(tab);
                                            applyFilters({ date_filter: tab });
                                        }}
                                        className={`rounded-md px-2.5 py-1 font-medium capitalize transition-all ${
                                            dateFilter === tab
                                                ? 'bg-card text-foreground shadow-sm'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                    >
                                        {tab}
                                    </button>
                                ))}
                            </div>

                            {/* Event Type Filter */}
                            <Select
                                value={typeFilter}
                                onValueChange={(val) => {
                                    setTypeFilter(val);
                                    applyFilters({ event_type_id: val });
                                }}
                            >
                                <SelectTrigger className="w-[180px] bg-background/80 text-xs">
                                    <Filter className="size-3.5 mr-1.5 text-muted-foreground" />
                                    <SelectValue placeholder="All Event Types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Event Types</SelectItem>
                                    {eventTypes.map((t) => (
                                        <SelectItem key={t.id} value={t.id.toString()}>
                                            {t.event_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </Card>

                {/* Main View: Calendar or List */}
                {viewMode === 'calendar' ? (
                    <Card className="p-6 border-border/50 bg-card/80 backdrop-blur-sm shadow-sm">
                        {/* Month Navigation */}
                        <div className="flex items-center justify-between mb-6">
                            <div className="flex items-center gap-2">
                                <h2 className="text-xl font-bold tracking-tight text-foreground">
                                    {monthNames[currentMonth]} {currentYear}
                                </h2>
                                <Badge variant="outline" className="text-xs bg-primary/5 text-primary border-primary/20">
                                    {events.data.length} Events Total
                                </Badge>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleToday}
                                    className="text-xs font-semibold"
                                >
                                    Today
                                </Button>
                                <div className="flex items-center border border-border/50 rounded-lg overflow-hidden">
                                    <button
                                        type="button"
                                        onClick={handlePrevMonth}
                                        className="p-1.5 hover:bg-muted/70 transition-colors text-muted-foreground hover:text-foreground"
                                    >
                                        <ChevronLeft className="size-4" />
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleNextMonth}
                                        className="p-1.5 hover:bg-muted/70 transition-colors text-muted-foreground hover:text-foreground border-l border-border/50"
                                    >
                                        <ChevronRight className="size-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        {/* Calendar Grid */}
                        <div className="grid grid-cols-7 gap-px rounded-xl bg-border/40 overflow-hidden border border-border/40">
                            {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((d) => (
                                <div
                                    key={d}
                                    className="bg-muted/40 p-2.5 text-center text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                                >
                                    {d}
                                </div>
                            ))}

                            {/* Leading empty days from prev month */}
                            {Array.from({ length: firstDayIndex }).map((_, idx) => {
                                const prevDate = daysInPrevMonth - firstDayIndex + idx + 1;
                                return (
                                    <div
                                        key={`prev-${idx}`}
                                        className="min-h-[110px] bg-card/30 p-2 text-muted-foreground/40 text-xs"
                                    >
                                        <span>{prevDate}</span>
                                    </div>
                                );
                            })}

                            {/* Days of the month */}
                            {Array.from({ length: daysInMonth }).map((_, idx) => {
                                const dayNum = idx + 1;
                                const isToday =
                                    dayNum === today.getDate() &&
                                    currentMonth === today.getMonth() &&
                                    currentYear === today.getFullYear();
                                const dayEvents = getEventsForDay(dayNum);
                                const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;

                                return (
                                    <div
                                        key={`day-${dayNum}`}
                                        className={`group relative min-h-[110px] bg-card p-2 text-xs transition-colors hover:bg-muted/20 ${
                                            isToday ? 'bg-primary/[0.03] ring-1 ring-inset ring-primary/40' : ''
                                        }`}
                                    >
                                        <div className="flex items-center justify-between mb-1.5">
                                            <span
                                                className={`inline-flex size-6 items-center justify-center rounded-full text-xs font-semibold ${
                                                    isToday
                                                        ? 'bg-primary text-primary-foreground font-bold shadow-sm'
                                                        : 'text-foreground group-hover:text-primary'
                                                }`}
                                            >
                                                {dayNum}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => handleOpenCreate(dateStr)}
                                                className="opacity-0 group-hover:opacity-100 p-0.5 rounded text-muted-foreground hover:text-primary transition-all"
                                                title="Add event for this day"
                                            >
                                                <Plus className="size-3.5" />
                                            </button>
                                        </div>

                                        <div className="flex flex-col gap-1 overflow-y-auto max-h-[85px] no-scrollbar">
                                            {dayEvents.map((ev) => (
                                                <button
                                                    key={ev.id}
                                                    type="button"
                                                    onClick={() => {
                                                        setSelectedEvent(ev);
                                                        setDetailDialogOpen(true);
                                                    }}
                                                    className="flex items-center gap-1 w-full text-left truncate rounded px-1.5 py-0.5 text-[11px] font-medium bg-primary/10 hover:bg-primary/20 text-primary border border-primary/20 transition-colors"
                                                >
                                                    <span className="size-1.5 rounded-full bg-primary shrink-0" />
                                                    <span className="truncate">{ev.title}</span>
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </Card>
                ) : (
                    /* List View */
                    <div className="flex flex-col gap-3">
                        {events.data.length === 0 ? (
                            <Card className="flex flex-col items-center justify-center p-12 text-center border-dashed border-border/60 bg-card/40">
                                <CalendarDays className="size-10 text-muted-foreground/60 mb-3" />
                                <h3 className="text-base font-semibold text-foreground">No events found</h3>
                                <p className="text-sm text-muted-foreground max-w-sm mt-1">
                                    There are no events matching your selected criteria. Try adjusting your filters or create a new event.
                                </p>
                                <Button
                                    onClick={() => handleOpenCreate()}
                                    className="mt-4 bg-primary hover:bg-primary/90 text-primary-foreground text-xs"
                                >
                                    <Plus className="mr-1 size-3.5" />
                                    Create First Event
                                </Button>
                            </Card>
                        ) : (
                            events.data.map((ev) => (
                                <Card
                                    key={ev.id}
                                    className="p-5 border-border/50 bg-card/80 hover:bg-card hover:border-primary/30 transition-all duration-200 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4"
                                >
                                    <div className="flex items-start gap-4">
                                        <div className="flex size-12 flex-col items-center justify-center rounded-xl bg-primary/10 text-primary shrink-0 border border-primary/20">
                                            <span className="text-[10px] uppercase font-bold tracking-wider">
                                                {new Date(ev.start_datetime).toLocaleDateString([], { month: 'short' })}
                                            </span>
                                            <span className="text-lg font-extrabold leading-none">
                                                {new Date(ev.start_datetime).getDate()}
                                            </span>
                                        </div>

                                        <div className="space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="text-base font-semibold text-foreground tracking-tight">
                                                    {ev.title}
                                                </h3>
                                                {ev.eventType && (
                                                    <Badge variant="outline" className="text-[11px] bg-muted/60 text-muted-foreground">
                                                        {ev.eventType.event_name}
                                                    </Badge>
                                                )}
                                                <Badge
                                                    variant={
                                                        ev.status === 'published'
                                                            ? 'default'
                                                            : ev.status === 'draft'
                                                            ? 'secondary'
                                                            : 'destructive'
                                                    }
                                                    className="text-[10px] capitalize font-medium"
                                                >
                                                    {ev.status}
                                                </Badge>
                                            </div>

                                            {ev.description && (
                                                <p className="text-sm text-muted-foreground line-clamp-1">
                                                    {ev.description}
                                                </p>
                                            )}

                                            <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground pt-1">
                                                <span className="inline-flex items-center gap-1">
                                                    <Clock className="size-3.5 text-primary" />
                                                    {formatTime(ev.start_datetime)}
                                                    {ev.end_datetime && ` - ${formatTime(ev.end_datetime)}`}
                                                </span>
                                                {ev.organisation && (
                                                    <span className="inline-flex items-center gap-1">
                                                        <MapPin className="size-3.5 text-secondary" />
                                                        {ev.organisation.org_name || ev.organisation.short_name}
                                                    </span>
                                                )}
                                                {ev.show_dashboard && (
                                                    <span className="inline-flex items-center gap-1 text-primary">
                                                        <Sparkles className="size-3" />
                                                        On Dashboard
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 self-end md:self-center">
                                        {ev.external_link && (
                                            <a
                                                href={ev.external_link}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center gap-1 rounded-lg border border-border/50 px-2.5 py-1.5 text-xs font-medium hover:bg-muted transition-colors"
                                            >
                                                <ExternalLink className="size-3.5" />
                                                Link
                                            </a>
                                        )}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setSelectedEvent(ev);
                                                setDetailDialogOpen(true);
                                            }}
                                            className="text-xs"
                                        >
                                            View Details
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleDeleteEvent(ev.id)}
                                            className="text-destructive hover:text-destructive hover:bg-destructive/10"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </Button>
                                    </div>
                                </Card>
                            ))
                        )}
                    </div>
                )}
            </div>

            {/* Create Event Dialog */}
            <Dialog open={createDialogOpen} onOpenChange={setCreateDialogOpen}>
                <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold flex items-center gap-2">
                            <CalendarDays className="size-5 text-primary" />
                            Create New Event
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Fill in event details, schedule timing, flyer attachment, and visibility settings.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleCreateEvent} className="space-y-4 pt-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="event-title" className="text-xs font-semibold">
                                Event Title *
                            </Label>
                            <Input
                                id="event-title"
                                placeholder="e.g., Friday Lunch, Alex from Davi, Cobot Training"
                                value={formTitle}
                                onChange={(e) => setFormTitle(e.target.value)}
                                required
                            />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label className="text-xs font-semibold">Event Type *</Label>
                                <Select value={formTypeId} onValueChange={setFormTypeId} required>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {eventTypes.map((t) => (
                                            <SelectItem key={t.id} value={t.id.toString()}>
                                                {t.event_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label className="text-xs font-semibold">Organisation (Optional)</Label>
                                <Select value={formOrgId} onValueChange={setFormOrgId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select organisation" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">All Organisations</SelectItem>
                                        {organisations.map((org) => (
                                            <SelectItem key={org.id} value={org.id.toString()}>
                                                {org.org_name || org.short_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="event-start" className="text-xs font-semibold">
                                    Start Date & Time *
                                </Label>
                                <Input
                                    id="event-start"
                                    type="datetime-local"
                                    value={formStart}
                                    onChange={(e) => setFormStart(e.target.value)}
                                    required
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="event-end" className="text-xs font-semibold">
                                    End Date & Time
                                </Label>
                                <Input
                                    id="event-end"
                                    type="datetime-local"
                                    value={formEnd}
                                    onChange={(e) => setFormEnd(e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="event-link" className="text-xs font-semibold">
                                    External URL / Meeting Link
                                </Label>
                                <Input
                                    id="event-link"
                                    type="url"
                                    placeholder="https://..."
                                    value={formLink}
                                    onChange={(e) => setFormLink(e.target.value)}
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label className="text-xs font-semibold">Publication Status</Label>
                                <Select
                                    value={formStatus}
                                    onValueChange={(v: 'draft' | 'published' | 'cancelled') => setFormStatus(v)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="published">Published</SelectItem>
                                        <SelectItem value="draft">Draft</SelectItem>
                                        <SelectItem value="cancelled">Cancelled</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="event-desc" className="text-xs font-semibold">
                                Description
                            </Label>
                            <Textarea
                                id="event-desc"
                                placeholder="Add event agenda, details, or guidelines..."
                                rows={3}
                                value={formDescription}
                                onChange={(e) => setFormDescription(e.target.value)}
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="event-file" className="text-xs font-semibold">
                                Flyer / Attachment (PDF or Image, max 10MB)
                            </Label>
                            <Input
                                id="event-file"
                                type="file"
                                accept=".pdf,image/*"
                                onChange={(e) => {
                                    if (e.target.files && e.target.files[0]) {
                                        setFormFile(e.target.files[0]);
                                    }
                                }}
                            />
                        </div>

                        <div className="flex items-center gap-2 pt-1">
                            <input
                                type="checkbox"
                                id="event-dashboard"
                                checked={formShowDashboard}
                                onChange={(e) => setFormShowDashboard(e.target.checked)}
                                className="size-4 rounded border-border text-primary focus:ring-primary"
                            />
                            <Label htmlFor="event-dashboard" className="text-xs font-medium cursor-pointer">
                                Display on Today’s Events / Dashboard widget
                            </Label>
                        </div>

                        <DialogFooter className="pt-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCreateDialogOpen(false)}
                                disabled={isSubmitting}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={isSubmitting} className="bg-primary text-primary-foreground font-semibold">
                                {isSubmitting ? 'Saving...' : 'Create Event'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Event Detail Dialog */}
            <Dialog open={detailDialogOpen} onOpenChange={setDetailDialogOpen}>
                <DialogContent className="max-w-lg">
                    {selectedEvent && (
                        <>
                            <DialogHeader>
                                <div className="flex items-center gap-2">
                                    {selectedEvent.eventType && (
                                        <Badge variant="outline" className="text-[10px] bg-primary/10 text-primary border-primary/20">
                                            {selectedEvent.eventType.event_name}
                                        </Badge>
                                    )}
                                    <Badge
                                        variant={
                                            selectedEvent.status === 'published'
                                                ? 'default'
                                                : selectedEvent.status === 'draft'
                                                ? 'secondary'
                                                : 'destructive'
                                        }
                                        className="text-[10px] capitalize"
                                    >
                                        {selectedEvent.status}
                                    </Badge>
                                </div>
                                <DialogTitle className="text-lg font-bold mt-1 text-foreground">
                                    {selectedEvent.title}
                                </DialogTitle>
                            </DialogHeader>

                            <div className="space-y-4 pt-2 text-sm">
                                <div className="space-y-1.5 rounded-xl bg-muted/40 p-3 border border-border/50 text-xs">
                                    <div className="flex items-center gap-2 text-foreground font-medium">
                                        <CalendarIcon className="size-3.5 text-primary" />
                                        <span>{formatDate(selectedEvent.start_datetime)}</span>
                                    </div>
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <Clock className="size-3.5 text-primary" />
                                        <span>
                                            {formatTime(selectedEvent.start_datetime)}
                                            {selectedEvent.end_datetime && ` - ${formatTime(selectedEvent.end_datetime)}`}
                                        </span>
                                    </div>
                                    {selectedEvent.organisation && (
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <MapPin className="size-3.5 text-secondary" />
                                            <span>{selectedEvent.organisation.org_name || selectedEvent.organisation.short_name}</span>
                                        </div>
                                    )}
                                </div>

                                {selectedEvent.description && (
                                    <div>
                                        <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                            Description / Agenda
                                        </h4>
                                        <p className="text-xs leading-relaxed text-foreground whitespace-pre-line bg-card/60 p-3 rounded-lg border border-border/40">
                                            {selectedEvent.description}
                                        </p>
                                    </div>
                                )}

                                {selectedEvent.file_path && (
                                    <div>
                                        <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                            Attachment
                                        </h4>
                                        <a
                                            href={`/storage/${selectedEvent.file_path}`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-2 rounded-lg border border-border/60 bg-muted/40 px-3 py-2 text-xs font-semibold text-primary hover:bg-muted transition-colors"
                                        >
                                            <FileText className="size-4" />
                                            View Flyer / Document
                                        </a>
                                    </div>
                                )}

                                {selectedEvent.external_link && (
                                    <div>
                                        <a
                                            href={selectedEvent.external_link}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-2 rounded-lg bg-primary/10 text-primary px-3 py-2 text-xs font-semibold hover:bg-primary/20 transition-colors w-full justify-center"
                                        >
                                            <ExternalLink className="size-4" />
                                            Open External Link / Meeting
                                        </a>
                                    </div>
                                )}
                            </div>

                            <DialogFooter className="pt-4 flex justify-between sm:justify-between items-center">
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => handleDeleteEvent(selectedEvent.id)}
                                    className="text-xs"
                                >
                                    <Trash2 className="size-3.5 mr-1" />
                                    Delete
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setDetailDialogOpen(false)}
                                    className="text-xs"
                                >
                                    Close
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

EventsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Events',
            href: '/events',
        },
    ],
};
