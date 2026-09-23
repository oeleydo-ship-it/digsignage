import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup, NavItem } from '@/types';

export function NavMain({
    items,
    groups,
}: {
    items?: NavItem[];
    groups?: NavGroup[];
}) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const sections: NavGroup[] =
        groups ??
        (items
            ? [
                  {
                      title: 'Platform',
                      items,
                  },
              ]
            : []);

    const itemIsActive = (item: NavItem) =>
        item.isActive ??
        (item.items?.length
            ? isCurrentOrParentUrl(item.href) ||
              item.items.some(
                  (child) =>
                      child.isActive ?? isCurrentUrl(child.href),
              )
            : isCurrentUrl(item.href));

    const renderLink = (item: NavItem) =>
        item.disabled ? (
            <SidebarMenuButton
                disabled
                className="h-10 rounded-lg text-sm"
                tooltip={{
                    children: `${item.title} (coming soon)`,
                }}
            >
                {item.icon && <item.icon />}
                <span>{item.title}</span>
            </SidebarMenuButton>
        ) : (
            <SidebarMenuButton
                asChild
                isActive={itemIsActive(item)}
                className="text-sidebar-foreground/75 h-10 gap-3 rounded-lg px-3 text-sm transition-colors hover:text-white data-[active=true]:bg-blue-500/15 data-[active=true]:font-semibold data-[active=true]:text-blue-200 data-[active=true]:shadow-[inset_3px_0_0_#60a5fa]"
                tooltip={{ children: item.title }}
            >
                <Link href={item.href} prefetch>
                    {item.icon && <item.icon />}
                    <span>{item.title}</span>
                </Link>
            </SidebarMenuButton>
        );

    return (
        <>
            {sections.map((group) => (
                <SidebarGroup
                    key={group.title || group.items[0]?.title}
                    className={`px-3 py-2 group-data-[collapsible=icon]:px-2 ${group.className ?? ''}`.trim()}
                >
                    {group.title ? (
                        <SidebarGroupLabel className="text-sidebar-foreground/50 h-8 px-3 text-[10px] font-semibold tracking-[0.16em] uppercase">
                            {group.title}
                        </SidebarGroupLabel>
                    ) : null}
                    <SidebarMenu className="gap-0.5">
                        {group.items.map((item) =>
                            item.items?.length ? (
                                <Collapsible
                                    key={item.title}
                                    defaultOpen={itemIsActive(item)}
                                    className="group/queue"
                                >
                                    <SidebarMenuItem>
                                        <CollapsibleTrigger asChild>
                                            <SidebarMenuButton
                                                isActive={itemIsActive(item)}
                                                className="text-sidebar-foreground/75 h-10 gap-3 rounded-lg px-3 text-sm transition-colors hover:text-white data-[active=true]:bg-blue-500/15 data-[active=true]:font-semibold data-[active=true]:text-blue-200 data-[active=true]:shadow-[inset_3px_0_0_#60a5fa]"
                                                tooltip={{ children: item.title }}
                                            >
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                                <ChevronRight className="ml-auto size-4 transition-transform group-data-[state=open]/queue:rotate-90" />
                                            </SidebarMenuButton>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <SidebarMenuSub className="mx-0 border-l-blue-500/20 px-1">
                                                {item.items.map((child) => (
                                                    <SidebarMenuSubItem
                                                        key={child.title}
                                                    >
                                                        <SidebarMenuSubButton
                                                            asChild
                                                            isActive={
                                                                child.isActive ??
                                                                isCurrentUrl(
                                                                    child.href,
                                                                )
                                                            }
                                                            className="h-8 text-sidebar-foreground/70 data-[active=true]:text-blue-200"
                                                        >
                                                            <Link
                                                                href={child.href}
                                                                prefetch
                                                            >
                                                                <span>
                                                                    {child.title}
                                                                </span>
                                                            </Link>
                                                        </SidebarMenuSubButton>
                                                    </SidebarMenuSubItem>
                                                ))}
                                            </SidebarMenuSub>
                                        </CollapsibleContent>
                                    </SidebarMenuItem>
                                </Collapsible>
                            ) : (
                                <SidebarMenuItem key={item.title}>
                                    {renderLink(item)}
                                </SidebarMenuItem>
                            ),
                        )}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
