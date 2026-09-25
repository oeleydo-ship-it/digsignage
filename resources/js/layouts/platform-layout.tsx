import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Bell,
    Building2,
    CreditCard,
    Database,
    Flag,
    HardDrive,
    LayoutGrid,
    LayoutTemplate,
    ListTodo,
    Monitor,
    Rocket,
    ScrollText,
    Settings,
    Shield,
    Users,
} from 'lucide-react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { AppLayoutProps, NavGroup } from '@/types';

function PlatformSidebar() {
    const slug = usePage().props.currentTeam?.slug;

    const groups: NavGroup[] = [
        {
            title: 'Platform',
            items: [
                { title: 'Overview', href: '/platform', icon: LayoutGrid },
                {
                    title: 'General settings',
                    href: '/platform/settings',
                    icon: Settings,
                },
                {
                    title: 'Organizations',
                    href: '/platform/organizations',
                    icon: Building2,
                },
                { title: 'Users', href: '/platform/users', icon: Users },
                {
                    title: 'Subscriptions',
                    href: '/platform/subscriptions',
                    icon: CreditCard,
                },
                { title: 'Plans', href: '/platform/plans', icon: Shield },
                { title: 'Screens', href: '/platform/screens', icon: Monitor },
                { title: 'Usage', href: '/platform/usage', icon: HardDrive },
                { title: 'Storage', href: '/platform/storage', icon: Database },
                {
                    title: 'System health',
                    href: '/platform/health',
                    icon: Activity,
                },
                { title: 'Jobs', href: '/platform/jobs', icon: ListTodo },
                {
                    title: 'Templates',
                    href: '/platform/templates',
                    icon: LayoutTemplate,
                },
                {
                    title: 'Feature flags',
                    href: '/platform/feature-flags',
                    icon: Flag,
                },
                {
                    title: 'Announcements',
                    href: '/platform/announcements',
                    icon: Bell,
                },
                {
                    title: 'Audit logs',
                    href: '/platform/audits',
                    icon: ScrollText,
                },
                {
                    title: 'Updates',
                    href: '/platform/updates',
                    icon: Rocket,
                },
            ],
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/platform" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {slug && (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href={`/${slug}/dashboard`}>
                                    Back to organization
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
            </SidebarHeader>
            <SidebarContent>
                <NavMain groups={groups} />
            </SidebarContent>
        </Sidebar>
    );
}

export default function PlatformLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <PlatformSidebar />
            <AppContent
                variant="sidebar"
                className="bg-background min-w-0 overflow-x-clip"
            >
                <ImpersonationBanner />
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
