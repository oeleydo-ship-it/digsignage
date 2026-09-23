import { Link, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    Images,
    Monitor,
    Plus,
    CalendarClock,
} from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { NotificationBell } from '@/components/notification-bell';
import { UserMenuContent } from '@/components/user-menu-content';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
    compact = false,
}: {
    breadcrumbs?: BreadcrumbItemType[];
    compact?: boolean;
}) {
    const { currentTeam, auth } = usePage().props;
    const initials = auth.user.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('');

    return (
        <header
            className={`bg-card/95 sticky top-0 z-20 flex shrink-0 items-center justify-between gap-3 border-b backdrop-blur-md ${
                compact
                    ? 'h-12 px-3 sm:px-4'
                    : 'h-[72px] px-4 sm:px-8'
            }`}
        >
            <div className="flex min-w-0 items-center gap-3">
                <SidebarTrigger className="text-muted-foreground -ml-1" />
                <span className="bg-border h-5 w-px shrink-0" />
                <div className="min-w-0 truncate">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>
            <div className="flex shrink-0 items-center gap-2 sm:gap-4">
                {currentTeam && !compact && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button className="gap-2 rounded-lg bg-blue-600 text-white shadow-sm hover:bg-blue-700">
                                <Plus className="size-4" />
                                <span className="hidden sm:inline">
                                    Quick actions
                                </span>
                                <ChevronDown className="size-3" />
                                <span className="sr-only sm:hidden">
                                    Quick actions
                                </span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            className="w-56 rounded-xl p-2"
                        >
                            <DropdownMenuLabel>
                                Manage your workspace
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link href={`/${currentTeam.slug}/media`}>
                                    <Images />
                                    Open media library
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href={`/${currentTeam.slug}/screens`}>
                                    <Monitor />
                                    Manage screens
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href={`/${currentTeam.slug}/schedules`}>
                                    <CalendarClock />
                                    Schedule content
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
                <NotificationBell />
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            aria-label="Account menu"
                            className="flex size-9 items-center justify-center rounded-full border border-blue-200 bg-blue-50 text-xs font-bold text-blue-700 transition-colors hover:bg-blue-100"
                        >
                            {initials}
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
