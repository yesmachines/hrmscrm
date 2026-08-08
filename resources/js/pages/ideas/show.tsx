import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Clock, Image as ImageIcon, Check, X, ThumbsUp } from 'lucide-react';
import IdeaController from '@/actions/App/Http/Controllers/IdeaController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/input-error';

type Track = {
    id: number;
    action_type: string;
    remarks: string | null;
    action_on: string;
    done_by: { id: number; name: string } | null;
};

type IdeaData = {
    id: number;
    title: string;
    description: string;
    status: string;
    review_comment: string | null;
    idea_files: string[] | null;
    created_at: string;
    employee: {
        id: number;
        user: { name: string } | null;
        employee_code: string | null;
        designation: string | null;
        department: { name: string } | null;
        image_url: string | null;
    } | null;
    tracks: Track[];
};

export default function IdeaShow({ idea }: { idea: IdeaData }) {
    const { data, setData, post, processing, errors } = useForm({
        status: idea.status,
        remarks: '',
        review_comment: idea.review_comment ?? '',
    });

    const submitStatus = (e: React.FormEvent) => {
        e.preventDefault();
        post(IdeaController.updateStatus.url(idea.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={`Idea: ${idea.title}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 p-6 md:p-10">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <Button variant="ghost" size="sm" asChild className="-ml-3 mb-2">
                            <Link href={IdeaController.index.url()}>
                                <ArrowLeft className="mr-2 size-4" />
                                Back to Ideas
                            </Link>
                        </Button>
                        <Heading
                            title={idea.title}
                            description={`Submitted by ${idea.employee?.user?.name ?? 'Unknown'} on ${new Date(idea.created_at).toLocaleDateString()}`}
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary">
                            Status: {idea.status.charAt(0).toUpperCase() + idea.status.slice(1)}
                        </span>
                    </div>
                </div>

                <div className="grid gap-8 lg:grid-cols-3">
                    {/* Left Column: Idea Details */}
                    <div className="space-y-6 lg:col-span-2">
                        <section className="flex flex-col gap-5 rounded-[1.25rem] border border-border/50 bg-card/80 p-6 shadow-sm backdrop-blur-sm md:p-8">
                            <h3 className="text-lg font-semibold tracking-tight text-foreground">
                                Idea Description
                            </h3>
                            <div className="whitespace-pre-wrap text-sm leading-relaxed text-muted-foreground">
                                {idea.description}
                            </div>
                        </section>

                        {idea.idea_files && idea.idea_files.length > 0 && (
                            <section className="flex flex-col gap-5 rounded-[1.25rem] border border-border/50 bg-card/80 p-6 shadow-sm backdrop-blur-sm md:p-8">
                                <h3 className="text-lg font-semibold tracking-tight text-foreground">
                                    Attachments
                                </h3>
                                <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                                    {idea.idea_files.map((file, idx) => (
                                        <a
                                            key={idx}
                                            href={`/storage/${file}`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="group flex aspect-square flex-col items-center justify-center rounded-xl border border-border bg-muted/30 transition-colors hover:bg-muted/50"
                                        >
                                            <ImageIcon className="size-8 text-muted-foreground/50 group-hover:text-primary" />
                                            <span className="mt-2 text-xs text-muted-foreground">
                                                View Attachment
                                            </span>
                                        </a>
                                    ))}
                                </div>
                            </section>
                        )}

                        <section className="flex flex-col gap-5 rounded-[1.25rem] border border-border/50 bg-card/80 p-6 shadow-sm backdrop-blur-sm md:p-8">
                            <h3 className="text-lg font-semibold tracking-tight text-foreground">
                                Update Status
                            </h3>
                            <form onSubmit={submitStatus} className="space-y-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="status">New Status</Label>
                                    <select
                                        id="status"
                                        value={data.status}
                                        onChange={(e) => setData('status', e.target.value)}
                                        className="h-10 w-full rounded-lg border border-input bg-white px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                    >
                                        <option value="submitted">Submitted</option>
                                        <option value="accepted">Accepted for Review</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                        <option value="implemented">Implemented</option>
                                    </select>
                                    <InputError message={errors.status} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="review_comment">Official Review Comment (Visible on Idea)</Label>
                                    <textarea
                                        id="review_comment"
                                        rows={3}
                                        value={data.review_comment}
                                        onChange={(e) => setData('review_comment', e.target.value)}
                                        className="w-full rounded-lg border border-input bg-white p-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                        placeholder="Add an official comment..."
                                    />
                                    <InputError message={errors.review_comment} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="remarks">Action Remarks (Internal tracking log)</Label>
                                    <Input
                                        id="remarks"
                                        value={data.remarks}
                                        onChange={(e) => setData('remarks', e.target.value)}
                                        placeholder="Why are you changing this status?"
                                    />
                                    <InputError message={errors.remarks} />
                                </div>

                                <div className="flex justify-end pt-2">
                                    <Button type="submit" disabled={processing}>
                                        Update Status
                                    </Button>
                                </div>
                            </form>
                        </section>
                    </div>

                    {/* Right Column: Tracking Timeline */}
                    <div className="space-y-6">
                        <section className="flex flex-col gap-5 rounded-[1.25rem] border border-border/50 bg-card/80 p-6 shadow-sm backdrop-blur-sm md:p-8">
                            <h3 className="text-base font-semibold tracking-tight text-foreground">
                                Employee Profile
                            </h3>
                            {idea.employee ? (
                                <div className="flex items-center gap-4">
                                    <div className="size-12 shrink-0 overflow-hidden rounded-full bg-muted">
                                        {idea.employee.image_url ? (
                                            <img
                                                src={`/storage/${idea.employee.image_url}`}
                                                alt={idea.employee.user?.name}
                                                className="size-full object-cover"
                                            />
                                        ) : (
                                            <div className="flex size-full items-center justify-center text-lg font-medium text-muted-foreground">
                                                {idea.employee.user?.name?.charAt(0) ?? '?'}
                                            </div>
                                        )}
                                    </div>
                                    <div className="flex flex-col overflow-hidden">
                                        <span className="truncate font-medium text-foreground">
                                            {idea.employee.user?.name ?? 'Unknown'}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {idea.employee.designation ?? 'No designation'}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {idea.employee.department?.name ?? 'No department'}
                                        </span>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">Employee details not found.</p>
                            )}
                        </section>

                        <section className="flex flex-col gap-5 rounded-[1.25rem] border border-border/50 bg-card/80 p-6 shadow-sm backdrop-blur-sm md:p-8">
                            <h3 className="text-base font-semibold tracking-tight text-foreground">
                                Status Tracking
                            </h3>
                            <div className="relative space-y-6 pl-4 before:absolute before:bottom-2 before:left-[11px] before:top-2 before:w-px before:bg-border">
                                {idea.tracks.map((track, index) => (
                                    <div key={track.id} className="relative">
                                        <div className="absolute -left-6 mt-0.5 flex size-4 items-center justify-center rounded-full bg-primary ring-4 ring-white">
                                            <div className="size-1.5 rounded-full bg-white" />
                                        </div>
                                        <div className="flex flex-col">
                                            <span className="text-sm font-medium text-foreground">
                                                {track.action_type.charAt(0).toUpperCase() + track.action_type.slice(1)}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {new Date(track.action_on).toLocaleString()}
                                            </span>
                                            {track.done_by && (
                                                <span className="text-xs text-muted-foreground mt-1">
                                                    by {track.done_by.name}
                                                </span>
                                            )}
                                            {track.remarks && (
                                                <div className="mt-2 rounded-lg bg-muted/50 p-3 text-sm text-foreground">
                                                    "{track.remarks}"
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}

IdeaShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Ideas', href: IdeaController.index.url() },
        { title: 'View Idea' },
    ],
};
