import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

/** CSS filters that turn any logo into a single-colour white or black mark. */
export const LOGO_TONE_FILTERS = {
    original: undefined,
    white: 'brightness(0) invert(1)',
    black: 'brightness(0)',
} as const;

export default function AppLogo() {
    const { name, branding } = usePage().props;
    const showName = branding?.show_name ?? true;

    // A custom logo is shown at its full width instead of in the square badge.
    if (branding?.logo_url) {
        return (
            <div
                className="flex min-w-0 flex-1 items-center justify-center gap-2"
                data-test="custom-logo"
            >
                <img
                    src={branding.logo_url}
                    alt={name}
                    className="h-10 max-w-full min-w-0 shrink object-contain object-center group-data-[collapsible=icon]:h-8 group-data-[collapsible=icon]:max-w-8"
                    style={{
                        filter: LOGO_TONE_FILTERS[
                            branding.logo_tone ?? 'original'
                        ],
                    }}
                />
                {showName && (
                    <span className="truncate text-sm leading-tight font-semibold tracking-tight group-data-[collapsible=icon]:hidden">
                        {name}
                    </span>
                )}
            </div>
        );
    }

    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md">
                <AppLogoIcon className="size-5 fill-current text-white" />
            </div>
            {showName && (
                <div className="ml-1 grid flex-1 text-left text-sm">
                    <span className="mb-0.5 truncate leading-tight font-semibold tracking-tight">
                        {name}
                    </span>
                </div>
            )}
        </>
    );
}
