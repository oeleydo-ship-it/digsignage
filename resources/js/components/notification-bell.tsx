import { Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { InAppNotificationItem } from '@/types';

function stayOnPageVisit() {
    return {
        preserveScroll: true,
        headers: {
            'X-Stay-On-Page': window.location.href,
        },
    };
}

function formatTime(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString();
}

export function NotificationBell() {
    const page = usePage();
    const slug = page.props.currentTeam?.slug;
    const unread = page.props.unreadNotifications ?? 0;
    const recent: InAppNotificationItem[] = page.props.recentNotifications ?? [];

    if (!slug) {
        return null;
    }

    const markRead = (item: InAppNotificationItem, visit = false) => {
        if (item.read_at !== null) {
            if (visit && item.url) {
                router.visit(item.url);
            }

            return;
        }

        router.post(`/${slug}/notifications/${item.id}/read`, {}, {
            ...stayOnPageVisit(),
            onSuccess: () => {
                if (visit && item.url) {
                    router.visit(item.url);
                }
            },
        });
    };

    const markAllRead = () => {
        router.post(`/${slug}/notifications/read-all`, {}, stayOnPageVisit());
    };

    const label =
        unread > 0
            ? `Notifications, ${unread} unread`
            : 'Notifications';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={label}
                >
                    <Bell className="size-4" />
                    {unread > 0 && (
                        <span className="bg-destructive text-destructive-foreground absolute -top-0.5 -right-0.5 min-w-4 rounded-full px-1 text-[10px] leading-4">
                            {unread > 99 ? '99+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80 p-0">
                <div className="flex items-center justify-between gap-2 px-3 py-2">
                    <p className="text-sm font-medium">Notifications</p>
                    {unread > 0 && (
                        <button
                            type="button"
                            className="text-muted-foreground hover:text-foreground text-xs"
                            onClick={markAllRead}
                        >
                            Mark all read
                        </button>
                    )}
                </div>
                <DropdownMenuSeparator className="my-0" />
                {recent.length === 0 ? (
                    <p className="text-muted-foreground px-3 py-8 text-center text-sm">
                        No notifications yet.
                    </p>
                ) : (
                    <ul className="max-h-80 overflow-y-auto">
                        {recent.map((item) => (
                            <li key={item.id} className="border-b last:border-b-0">
                                <div
                                    className={cn(
                                        'flex items-start gap-2 px-3 py-2',
                                        item.read_at === null && 'bg-muted/50',
                                    )}
                                >
                                    <button
                                        type="button"
                                        className="min-w-0 flex-1 text-left"
                                        onClick={() => markRead(item, Boolean(item.url))}
                                    >
                                        <p className="text-sm font-medium">
                                            {item.title}
                                            {item.read_at === null && (
                                                <span className="bg-primary ml-2 inline-block size-2 rounded-full align-middle" />
                                            )}
                                        </p>
                                        <p className="text-muted-foreground line-clamp-2 text-xs">
                                            {item.body}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-[11px]">
                                            {item.event_label}
                                            {item.created_at
                                                ? ` · ${formatTime(item.created_at)}`
                                                : ''}
                                        </p>
                                    </button>
                                    {item.read_at === null && (
                                        <button
                                            type="button"
                                            className="text-muted-foreground hover:text-foreground shrink-0 text-xs"
                                            onClick={() => markRead(item)}
                                        >
                                            Mark read
                                        </button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
                <DropdownMenuSeparator className="my-0" />
                <Link
                    href={`/${slug}/notifications`}
                    className="hover:bg-accent block px-3 py-2 text-center text-sm"
                >
                    View all
                </Link>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
