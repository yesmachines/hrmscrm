import { Head, Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import IdeaController from '@/actions/App/Http/Controllers/IdeaController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type IdeaRow = {
    id: number;
    title: string;
    status: string;
    created_at: string;
    employee: {
        id: number;
        user: { name: string } | null;
        employee_code: string | null;
        image_url: string | null;
    } | null;
};

type PaginatedIdeas = {
    data: IdeaRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function IdeasIndex({ ideas }: { ideas: PaginatedIdeas }) {
    return (
        <>
            <Head title="Ideas" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Ideas"
                        description="Track and manage employee submitted ideas"
                    />
                </div>

                <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Idea Title</th>
                                <th className="px-4 py-3 font-medium">Submitted By</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Date</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {ideas.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No ideas submitted yet.
                                    </td>
                                </tr>
                            ) : (
                                ideas.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium text-foreground">
                                            {row.title}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <div className="size-8 shrink-0 overflow-hidden rounded-full bg-muted">
                                                    {row.employee?.image_url ? (
                                                        <img
                                                            src={`/storage/${row.employee.image_url}`}
                                                            alt={row.employee.user?.name}
                                                            className="size-full object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex size-full items-center justify-center text-sm font-medium text-muted-foreground">
                                                            {row.employee?.user?.name?.charAt(0) ?? '?'}
                                                        </div>
                                                    )}
                                                </div>
                                                <div>
                                                    <div className="font-medium">
                                                        {row.employee?.user?.name ?? 'Unknown'}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {row.employee?.employee_code}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                                {row.status.charAt(0).toUpperCase() + row.status.slice(1)}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {new Date(row.created_at).toLocaleDateString()}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={IdeaController.show.url(row.id)}>
                                                    <Eye className="mr-2 size-4" />
                                                    View
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {ideas.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {ideas.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={`rounded-md border px-3 py-1.5 text-sm ${
                                        link.active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-white text-foreground hover:bg-muted'
                                    }`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : (
                                <span
                                    key={index}
                                    className="rounded-md border border-border px-3 py-1.5 text-sm text-muted-foreground opacity-50"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

IdeasIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Ideas', href: IdeaController.index.url() },
    ],
};
