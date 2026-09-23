export type BreadcrumbItem = {
    title: string;
    href: NonNullable<import('@inertiajs/react').InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<import('@inertiajs/react').InertiaLinkProps['href']>;
    icon?: import('lucide-react').LucideIcon | null;
    isActive?: boolean;
    disabled?: boolean;
    items?: NavItem[];
};

export type NavGroup = {
    title: string;
    items: NavItem[];
    className?: string;
};
