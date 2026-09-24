import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { ShieldAlert, Trash2, CheckCircle2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

interface User {
    id: number;
    name: string;
}

interface Author {
    id: number;
    user_id: number;
    emp_num: string;
    designation: string;
    user: User;
}

interface SocialMedia {
    id: number;
    post_id: number;
    file_type: 'photo' | 'video';
    file_path: string;
    thumbnail_path: string | null;
}

interface SocialPost {
    id: number;
    posted_by: number;
    content: string;
    status: 'published' | 'hidden' | 'removed' | 'pending';
    created_at: string;
    reactions_count: number;
    author: Author;
    media: SocialMedia[];
}

interface PaginatedData {
    data: SocialPost[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

interface Props {
    posts: PaginatedData;
}

export default function SocialsModeration({ posts }: Props) {
    const [processing, setProcessing] = useState<number | null>(null);

    const updateStatus = (post: SocialPost, newStatus: string) => {
        if (!confirm(`Are you sure you want to mark this post as ${newStatus}?`)) {
            return;
        }

        setProcessing(post.id);
        router.post(
            route('socials.update-status', post.id),
            { status: newStatus },
            {
                preserveScroll: true,
                onSuccess: () => {
                    alert(`Post marked as ${newStatus}.`);
                },
                onError: () => {
                    alert('Failed to update post status.');
                },
                onFinish: () => setProcessing(null),
            }
        );
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'published':
                return <Badge className="bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20">Published</Badge>;
            case 'hidden':
                return <Badge variant="secondary" className="bg-slate-100 text-slate-600">Hidden</Badge>;
            case 'removed':
                return <Badge variant="destructive" className="bg-red-500/10 text-red-500 hover:bg-red-500/20">Removed</Badge>;
            case 'pending':
                return <Badge variant="outline" className="text-amber-500 border-amber-500/20">Pending</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <>
            <Head title="Social Feed Moderation" />

            <div className="flex flex-col space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <Heading
                        title="Content Moderation"
                        description="Monitor and manage employee social feed posts."
                        icon={ShieldAlert}
                    />
                </div>

                <Card className="border-slate-200/60 shadow-sm">
                    <CardHeader className="bg-slate-50/50 border-b border-slate-100 pb-4">
                        <CardTitle className="text-lg font-medium text-slate-800">
                            Recent Posts
                        </CardTitle>
                        <CardDescription>
                            Review published posts and remove inappropriate content.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm text-left">
                                <thead className="bg-slate-50/80 border-b">
                                    <tr>
                                        <th className="px-4 py-3 font-medium text-slate-500 w-[80px]">ID</th>
                                        <th className="px-4 py-3 font-medium text-slate-500">Author</th>
                                        <th className="px-4 py-3 font-medium text-slate-500 w-[400px]">Content</th>
                                        <th className="px-4 py-3 font-medium text-slate-500">Media</th>
                                        <th className="px-4 py-3 font-medium text-slate-500 text-center">Reactions</th>
                                        <th className="px-4 py-3 font-medium text-slate-500">Status</th>
                                        <th className="px-4 py-3 font-medium text-slate-500 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {posts.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="px-4 py-12 text-center text-slate-500">
                                                <div className="flex flex-col items-center justify-center gap-2">
                                                    <ShieldAlert className="h-8 w-8 text-slate-300" />
                                                    <p>No posts found.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : (
                                        posts.data.map((post) => (
                                            <tr key={post.id} className="hover:bg-slate-50/50 transition-colors">
                                                <td className="px-4 py-3 font-medium text-slate-700">
                                                    #{post.id}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-col">
                                                        <span className="font-medium text-slate-900">{post.author?.user?.name || 'Unknown'}</span>
                                                        <span className="text-xs text-slate-500">{post.author?.emp_num}</span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <p className="text-sm text-slate-600 line-clamp-2">
                                                        {post.content || <span className="italic text-slate-400">Media only post</span>}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {post.media && post.media.length > 0 ? (
                                                        <Badge variant="secondary" className="text-xs">
                                                            {post.media.length} {post.media.length === 1 ? 'item' : 'items'}
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-xs text-slate-400">None</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span className="text-sm font-medium text-slate-700">{post.reactions_count}</span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {getStatusBadge(post.status)}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        {post.status === 'published' && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                className="h-8 text-red-600 border-red-200 hover:bg-red-50 hover:text-red-700"
                                                                disabled={processing === post.id}
                                                                onClick={() => updateStatus(post, 'removed')}
                                                            >
                                                                <Trash2 className="h-4 w-4 mr-1" />
                                                                Remove
                                                            </Button>
                                                        )}
                                                        {post.status === 'removed' && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                className="h-8 text-emerald-600 border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                                                                disabled={processing === post.id}
                                                                onClick={() => updateStatus(post, 'published')}
                                                            >
                                                                <CheckCircle2 className="h-4 w-4 mr-1" />
                                                                Restore
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {posts.last_page > 1 && (
                    <div className="flex justify-center mt-6 pb-8 space-x-2">
                        <a 
                            href={posts.prev_page_url || '#'}
                            className={`px-4 py-2 border rounded text-sm ${!posts.prev_page_url ? 'pointer-events-none opacity-50 bg-slate-100' : 'hover:bg-slate-50'}`}
                        >
                            Previous
                        </a>
                        <span className="px-4 py-2 text-sm text-slate-600">
                            Page {posts.current_page} of {posts.last_page}
                        </span>
                        <a 
                            href={posts.next_page_url || '#'}
                            className={`px-4 py-2 border rounded text-sm ${!posts.next_page_url ? 'pointer-events-none opacity-50 bg-slate-100' : 'hover:bg-slate-50'}`}
                        >
                            Next
                        </a>
                    </div>
                )}
            </div>
        </>
    );
}

SocialsModeration.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Content Moderation',
            href: '/socials/moderation',
        },
    ],
};
