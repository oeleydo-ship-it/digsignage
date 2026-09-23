import { Link, usePage } from '@inertiajs/react';
import { Activity, CalendarClock, Monitor } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const highlights = [
    {
        icon: Monitor,
        title: 'Every screen, one dashboard',
        description: 'Run your whole signage network from a single place.',
    },
    {
        icon: CalendarClock,
        title: 'Schedule in minutes',
        description: 'The right content, at the right time, on the right screen.',
    },
    {
        icon: Activity,
        title: 'Live playback status',
        description: 'Know the moment a screen needs attention.',
    },
];

function ScreenShowcase() {
    return (
        <div
            aria-hidden="true"
            className="relative mx-auto mt-10 flex h-36 w-full max-w-xs items-center justify-center"
        >
            <div className="absolute h-24 w-40 -translate-x-14 -rotate-12 rounded-xl border border-white/25 bg-blue-400/15 p-2">
                <div className="h-full rounded-md bg-blue-400/20" />
            </div>
            <div className="relative ml-8 h-32 w-52 rotate-6 rounded-xl border border-white/40 bg-[#162b55] p-2 shadow-2xl">
                <div className="flex h-full flex-col justify-end rounded-md bg-gradient-to-tr from-blue-600 via-indigo-500 to-cyan-300 p-3.5">
                    <div className="mb-1.5 h-1.5 w-9 rounded bg-white/50" />
                    <div className="h-2 w-24 rounded bg-white/90" />
                    <div className="mt-1.5 h-1 w-16 rounded bg-white/50" />
                </div>
                <div className="absolute -top-3 -right-3 flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 text-[10px] font-semibold tracking-wide text-blue-950 uppercase shadow-lg">
                    <span className="relative flex size-1.5">
                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                        <span className="relative inline-flex size-1.5 rounded-full bg-emerald-500" />
                    </span>
                    Live
                </div>
            </div>
        </div>
    );
}

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="bg-background grid min-h-dvh lg:grid-cols-[45%_55%]">
            {/* Brand panel */}
            <div className="dashboard-feature relative hidden flex-col overflow-hidden p-10 text-white lg:flex xl:p-12 dark:border-r dark:border-white/5">
                <div
                    aria-hidden="true"
                    className="absolute inset-0"
                    style={{
                        backgroundImage:
                            'linear-gradient(to right, rgb(255 255 255 / 0.05) 1px, transparent 1px), linear-gradient(to bottom, rgb(255 255 255 / 0.05) 1px, transparent 1px)',
                        backgroundSize: '44px 44px',
                    }}
                />

                <Link
                    href={home()}
                    className="relative z-10 flex items-center gap-3"
                >
                    <span className="flex size-10 items-center justify-center rounded-xl border border-white/15 bg-white/10">
                        <AppLogoIcon className="size-6 fill-current text-white" />
                    </span>
                    <span className="text-lg font-semibold tracking-tight">
                        {name}
                    </span>
                </Link>

                <div className="relative z-10 my-auto max-w-md py-12">
                    <p className="text-[11px] font-semibold tracking-[0.18em] text-blue-200/70 uppercase">
                        Digital signage platform
                    </p>
                    <h1 className="mt-4 text-3xl leading-tight font-semibold tracking-tight xl:text-4xl">
                        Great content.
                        <br />
                        Every screen.
                    </h1>
                    <p className="mt-4 text-sm leading-relaxed text-blue-100/70">
                        Design, schedule, and publish to your displays in
                        minutes — then keep an eye on every screen from one
                        place.
                    </p>

                    <ul className="mt-9 space-y-4">
                        {highlights.map((item) => (
                            <li
                                key={item.title}
                                className="flex items-center gap-3.5"
                            >
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg border border-white/15 bg-white/10">
                                    <item.icon className="size-4 text-blue-100" />
                                </span>
                                <div>
                                    <p className="text-sm font-semibold">
                                        {item.title}
                                    </p>
                                    <p className="text-xs text-blue-100/60">
                                        {item.description}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>

                    <ScreenShowcase />
                </div>

                <p className="relative z-10 text-xs text-blue-100/50">
                    Made for content. Built for connection.
                </p>
            </div>

            {/* Form panel */}
            <div className="relative flex flex-col items-center justify-center px-6 py-12 sm:px-10">
                <div className="mb-10 flex flex-col items-center gap-3 lg:hidden">
                    <Link
                        href={home()}
                        className="flex flex-col items-center gap-3"
                    >
                        <span className="bg-sidebar-primary text-sidebar-primary-foreground flex size-11 items-center justify-center rounded-xl">
                            <AppLogoIcon className="size-6 fill-current" />
                        </span>
                        <span className="text-base font-semibold tracking-tight">
                            {name}
                        </span>
                    </Link>
                </div>

                <div className="w-full max-w-sm">
                    <div className="mb-8 flex flex-col gap-2 text-center lg:text-left">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-muted-foreground text-sm leading-relaxed">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
