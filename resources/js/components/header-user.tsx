import { usePage } from '@inertiajs/react';
import { ChevronsUpDown } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';

export function HeaderUser() {
    const { auth } = usePage().props;
    const getInitials = useInitials();

    if (!auth.user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className="flex items-center gap-2.5 rounded-md px-1.5 py-1 outline-none transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-primary/30"
                data-test="header-user-menu"
            >
                <Avatar className="size-8 overflow-hidden rounded-full">
                    <AvatarImage
                        src={auth.user.avatar}
                        alt={auth.user.name}
                    />
                    <AvatarFallback className="bg-neutral-100 text-sm font-medium text-neutral-600">
                        {getInitials(auth.user.name)}
                    </AvatarFallback>
                </Avatar>
                <span className="max-w-40 truncate text-sm font-medium text-primary">
                    {auth.user.name}
                </span>
                <ChevronsUpDown className="size-4 shrink-0 text-muted-foreground" />
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className="min-w-56 rounded-lg"
                align="end"
                sideOffset={8}
            >
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
