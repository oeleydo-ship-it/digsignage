import { Head } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import { dashboard } from '@/routes';

export type QueuePageShellProps = {
    title: string;
    description: string;
    children?: React.ReactNode;
    empty?: {
        title: string;
        description: string;
    };
};

export function QueuePageShell({
    title,
    description,
    children,
    empty,
}: QueuePageShellProps) {
    return (
        <>
            <Head title={title} />
            <div className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-7 p-4 sm:p-8 lg:p-10">
                <div>
                    <p className="text-muted-foreground mb-2 text-[11px] font-semibold tracking-[0.18em] uppercase">
                        Queue Management
                    </p>
                    <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">
                        {title}
                        <span className="text-blue-600">.</span>
                    </h1>
                    <p className="text-muted-foreground mt-3 max-w-2xl text-sm">
                        {description}
                    </p>
                </div>
                {children}
                {empty ? (
                    <EmptyState
                        title={empty.title}
                        description={empty.description}
                    />
                ) : null}
            </div>
        </>
    );
}

export function queueBreadcrumbs(title: string, path: string) {
    return (props: { currentTeam?: { slug: string } | null }) => {
        const slug = props.currentTeam?.slug;

        return {
            breadcrumbs: [
                {
                    title: 'Dashboard',
                    href: slug ? dashboard(slug) : '/',
                },
                {
                    title: 'Queue Management',
                    href: slug ? `/${slug}/queue` : '/',
                },
                {
                    title,
                    href: slug ? `/${slug}/queue${path}` : '/',
                },
            ],
        };
    };
}

export function QueueStatCard({
    label,
    value,
    hint,
    icon: Icon,
    color,
    background,
}: {
    label: string;
    hint: string;
    value: number | string;
    icon: LucideIcon;
    color: string;
    background: string;
}) {
    return (
        <div className="px-4 sm:px-5">
            <div className="mb-3 flex items-center gap-2">
                <span
                    className={`flex size-7 items-center justify-center rounded-lg ${color} ${background}`}
                >
                    <Icon className="size-3.5" />
                </span>
                <span className="text-muted-foreground text-xs font-medium">
                    {label}
                </span>
            </div>
            <p className="text-3xl font-semibold tracking-tight tabular-nums">
                {value}
            </p>
            <p className="text-muted-foreground mt-1.5 text-[11px]">{hint}</p>
        </div>
    );
}
