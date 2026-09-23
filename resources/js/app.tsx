import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PlatformLayout from '@/layouts/platform-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name.startsWith('player/'):
            case name === 'queue/desk':
            case name === 'queue/kiosk-serve':
            case name.startsWith('queue/virtual-'):
            case name === 'designs/preview':
            case name === 'templates/preview':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('platform/'):
                return PlatformLayout;
            case name.startsWith('settings/'):
            case name.startsWith('teams/'):
            case name === 'notifications/index':
            case name === 'billing/index':
            case name === 'audit-logs/index':
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
