import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import { useSidebar } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { AppLayoutProps } from '@/types';

const EDITOR_SIDEBAR_BACKUP = 'sidebar_open_before_canvas_editor';

export function isCanvasEditorPage(component: string) {
    return component === 'designs/edit' || component === 'templates/edit';
}

function CanvasEditorSidebarMode() {
    const { component } = usePage();
    const { open, setOpen } = useSidebar();
    const isEditor = isCanvasEditorPage(component);
    const collapsedForEditor = useRef(false);

    useEffect(() => {
        const readBackup = () => {
            try {
                return sessionStorage.getItem(EDITOR_SIDEBAR_BACKUP);
            } catch {
                return null;
            }
        };
        const writeBackup = (value: string | null) => {
            try {
                if (value === null) {
                    sessionStorage.removeItem(EDITOR_SIDEBAR_BACKUP);
                } else {
                    sessionStorage.setItem(EDITOR_SIDEBAR_BACKUP, value);
                }
            } catch {
                /* Private mode can block sessionStorage. */
            }
        };

        if (isEditor) {
            if (!collapsedForEditor.current) {
                collapsedForEditor.current = true;
                if (readBackup() === null) {
                    writeBackup(open ? 'true' : 'false');
                }
                if (open) {
                    setOpen(false);
                }
            }
            return;
        }

        collapsedForEditor.current = false;
        const stored = readBackup();
        if (stored !== null) {
            writeBackup(null);
            const restore = stored === 'true';
            if (open !== restore) {
                setOpen(restore);
            }
        }
    }, [isEditor, open, setOpen]);

    return null;
}

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { component } = usePage();
    const isEditor = isCanvasEditorPage(component);

    return (
        <AppShell variant="sidebar">
            <CanvasEditorSidebarMode />
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className={cn(
                    'bg-background min-w-0 overflow-x-clip',
                    isEditor && 'h-svh overflow-hidden',
                )}
            >
                <ImpersonationBanner />
                <AppSidebarHeader breadcrumbs={breadcrumbs} compact={isEditor} />
                {children}
            </AppContent>
        </AppShell>
    );
}
