import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as teams } from '@/routes/teams';
import type { NavItem } from '@/types';

type SettingsGroup = {
    title: string;
    items: NavItem[];
};

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const page = usePage();
    const slug = page.props.currentTeam?.slug;
    const canManageBilling = Boolean(
        page.props.billingPermissions?.canManageBilling,
    );
    const canViewAuditLogs = Boolean(page.props.canViewAuditLogs);
    const isPlatformAdmin = page.props.isPlatformAdmin;

    const groups: SettingsGroup[] = [
        {
            title: 'Account',
            items: [
                { title: 'Profile', href: edit(), icon: null },
                { title: 'Security', href: editSecurity(), icon: null },
                { title: 'API', href: '/settings/api', icon: null },
                { title: 'Appearance', href: editAppearance(), icon: null },
            ],
        },
        {
            title: 'Workspace',
            items: [
                { title: 'Team', href: teams(), icon: null },
                ...(slug
                    ? [
                          {
                              title: 'Notifications',
                              href: `/${slug}/notifications`,
                              icon: null,
                          },
                          {
                              title: 'Monitoring',
                              href: `/${slug}/monitoring`,
                              icon: null,
                          },
                          {
                              title: 'Player fallback',
                              href: `/${slug}/player-fallback`,
                              icon: null,
                          },
                          {
                              title: 'Apps',
                              href: `/${slug}/apps`,
                              icon: null,
                          },
                          ...(canViewAuditLogs
                              ? [
                                    {
                                        title: 'Audit logs',
                                        href: `/${slug}/audit-logs`,
                                        icon: null,
                                    },
                                ]
                              : []),
                          ...(canManageBilling
                              ? [
                                    {
                                        title: 'Billing',
                                        href: `/${slug}/billing`,
                                        icon: null,
                                    },
                                ]
                              : []),
                      ]
                    : []),
            ],
        },
        ...(slug
            ? [
                  {
                      title: 'Content',
                      items: [
                          {
                              title: 'Approvals',
                              href: `/${slug}/approvals`,
                              icon: null,
                          },
                          {
                              title: 'Proof of play',
                              href: `/${slug}/reports/proof-of-play`,
                              icon: null,
                          },
                          {
                              title: 'Analytics',
                              href: `/${slug}/analytics`,
                              icon: null,
                          },
                      ],
                  },
              ]
            : []),
        ...(isPlatformAdmin
            ? [
                  {
                      title: 'Platform',
                      items: [
                          {
                              title: 'Platform admin',
                              href: '/platform',
                              icon: null,
                          },
                      ],
                  },
              ]
            : []),
    ];

    return (
        <div className="px-4 py-6 md:px-6">
            <Heading
                title="Settings"
                description="Manage your account and this workspace"
            />

            <div className="mt-6 flex flex-col lg:flex-row lg:space-x-10">
                <aside className="w-full max-w-xl lg:w-56">
                    <nav className="space-y-6" aria-label="Settings">
                        {groups.map((group) => (
                            <div key={group.title}>
                                <p className="text-muted-foreground mb-2 px-3 text-xs font-semibold tracking-[0.14em] uppercase">
                                    {group.title}
                                </p>
                                <div className="flex flex-col space-y-1">
                                    {group.items.map((item) => (
                                        <Button
                                            key={`${toUrl(item.href)}`}
                                            size="sm"
                                            variant="ghost"
                                            asChild
                                            className={cn(
                                                'w-full justify-start',
                                                {
                                                    'bg-muted':
                                                        isCurrentOrParentUrl(
                                                            item.href,
                                                        ),
                                                },
                                            )}
                                        >
                                            <Link href={item.href}>
                                                {item.title}
                                            </Link>
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="min-w-0 flex-1">
                    <section className="space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
