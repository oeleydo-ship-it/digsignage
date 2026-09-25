import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CalendarCheck,
    CalendarClock,
    Cloud,
    DoorOpen,
    CalendarDays,
    ConciergeBell,
    Images,
    Layers,
    LayoutDashboard,
    LayoutGrid,
    LayoutTemplate,
    ListMusic,
    MapPin,
    Megaphone,
    Monitor,
    MonitorPlay,
    PanelTop,
    PenTool,
    Radio,
    Settings,
    SlidersHorizontal,
    Tablet,
    Ticket,
    Tv,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import { index as designsIndex } from '@/routes/designs';
import { index as locationsIndex } from '@/routes/locations';
import { index as mediaIndex } from '@/routes/media';
import { index as screensIndex } from '@/routes/screens';
import { index as screenGroupsIndex } from '@/routes/screen-groups';
import { index as channelsIndex } from '@/routes/channels';
import { index as playlistsIndex } from '@/routes/playlists';
import { index as schedulesIndex } from '@/routes/schedules';
import { index as templatesIndex } from '@/routes/templates';
import type { NavGroup } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const slug = page.props.currentTeam?.slug;
    const dashboardUrl = slug ? dashboard(slug) : '/';
    const queuePermissions = page.props.queuePermissions;
    const canViewQueue = Boolean(queuePermissions?.canViewQueue);
    const queueHref = (path: string) => (slug ? `/${slug}/queue${path}` : '/');
    const bookingPermissions = page.props.bookingPermissions;
    const teamHref = (path: string) => (slug ? `/${slug}${path}` : '/');

    const groups: NavGroup[] = [
        {
            title: 'Overview',
            items: [
                {
                    title: 'Dashboard',
                    href: dashboardUrl,
                    icon: LayoutGrid,
                },
            ],
        },
        {
            title: 'Content',
            items: [
                {
                    title: 'Media',
                    href: slug ? mediaIndex(slug) : '/',
                    icon: Images,
                },
                {
                    title: 'Designer',
                    href: slug ? designsIndex(slug) : '/',
                    icon: PenTool,
                },
                {
                    title: 'Templates',
                    href: slug ? templatesIndex(slug) : '/',
                    icon: LayoutTemplate,
                },
                {
                    title: 'Playlists',
                    href: slug ? playlistsIndex(slug) : '/',
                    icon: ListMusic,
                },
                {
                    title: 'Channels',
                    href: slug ? channelsIndex(slug) : '/',
                    icon: Tv,
                },
            ],
        },
        {
            title: 'Signage',
            items: [
                {
                    title: 'Screens',
                    href: slug ? screensIndex(slug) : '/',
                    icon: Monitor,
                },
                {
                    title: 'Groups',
                    href: slug ? screenGroupsIndex(slug) : '/',
                    icon: Layers,
                },
                {
                    title: 'Locations',
                    href: slug ? locationsIndex(slug) : '/',
                    icon: MapPin,
                },
                {
                    title: 'Schedule',
                    href: slug ? schedulesIndex(slug) : '/',
                    icon: CalendarClock,
                },
                {
                    title: 'Emergencies',
                    href: slug ? `/${slug}/emergencies` : '/',
                    icon: Megaphone,
                },
            ],
        },
        ...(canViewQueue
            ? [
                  {
                      title: 'Queue Management',
                      items: [
                          {
                              title: 'Overview',
                              href: queueHref(''),
                              icon: LayoutDashboard,
                          },
                          {
                              title: 'Live Queue',
                              href: queueHref('/live'),
                              icon: Radio,
                          },
                          {
                              title: 'Services',
                              href: queueHref('/services'),
                              icon: ConciergeBell,
                          },
                          {
                              title: 'Counters',
                              href: queueHref('/counters'),
                              icon: PanelTop,
                          },
                          {
                              title: 'Kiosks',
                              href: queueHref('/kiosks'),
                              icon: Tablet,
                          },
                          {
                              title: 'Tickets',
                              href: queueHref('/tickets'),
                              icon: Ticket,
                          },
                          {
                              title: 'Appointments',
                              href: queueHref('/appointments'),
                              icon: CalendarDays,
                          },
                          {
                              title: 'Displays',
                              href: queueHref('/displays'),
                              icon: MonitorPlay,
                          },
                          ...(queuePermissions?.canViewReports
                              ? [
                                    {
                                        title: 'Reports',
                                        href: queueHref('/reports'),
                                        icon: BarChart3,
                                    },
                                ]
                              : []),
                          ...(queuePermissions?.canManageSettings
                              ? [
                                    {
                                        title: 'Queue Configuration',
                                        href: queueHref('/settings'),
                                        icon: SlidersHorizontal,
                                    },
                                ]
                              : []),
                      ],
                  } satisfies NavGroup,
              ]
            : []),
        ...(bookingPermissions?.canViewBookings
            ? [
                  {
                      title: 'Room Booking',
                      items: [
                          {
                              title: 'Bookings',
                              href: teamHref('/bookings'),
                              icon: CalendarCheck,
                          },
                          {
                              title: 'Rooms',
                              href: teamHref('/rooms'),
                              icon: DoorOpen,
                          },
                          ...(bookingPermissions.canManageRooms
                              ? [
                                    {
                                        title: 'Microsoft 365',
                                        href: teamHref(
                                            '/integrations/microsoft-365',
                                        ),
                                        icon: Cloud,
                                    },
                                ]
                              : []),
                      ],
                  } satisfies NavGroup,
              ]
            : []),
        {
            title: '',
            className: 'pb-8',
            items: [
                {
                    title: 'Settings',
                    href: profileEdit(),
                    icon: Settings,
                },
            ],
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="border-sidebar-border gap-4 border-b px-3 py-4 group-data-[collapsible=icon]:px-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TeamSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain groups={groups} />
            </SidebarContent>
        </Sidebar>
    );
}
