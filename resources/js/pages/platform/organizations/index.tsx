import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Paginated } from '@/types';

type Org = {
    id: number;
    name: string;
    slug: string;
    plan_name: string;
    status_label: string;
    suspended_at: string | null;
    screens_count: number;
    members_count: number;
};

type Props = {
    filters: { search: string };
    organizations: Paginated<Org>;
};

export default function PlatformOrganizations({ filters, organizations }: Props) {
    const [search, setSearch] = useState(filters.search);

    return (
        <>
            <Head title="Organizations" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Organizations" description="Every tenant on the platform." />
                <form
                    className="flex max-w-md gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get('/platform/organizations', { search }, { preserveState: true });
                    }}
                >
                    <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search" />
                    <Button type="submit">Search</Button>
                </form>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="p-3">Name</th>
                                <th className="p-3">Plan</th>
                                <th className="p-3">Status</th>
                                <th className="p-3">Screens</th>
                                <th className="p-3">Users</th>
                            </tr>
                        </thead>
                        <tbody>
                            {organizations.data.map((org) => (
                                <tr key={org.id} className="border-b">
                                    <td className="p-3">
                                        <Link className="underline" href={`/platform/organizations/${org.slug}`}>
                                            {org.name}
                                        </Link>
                                        {org.suspended_at && (
                                            <span className="ml-2 text-destructive">Suspended</span>
                                        )}
                                    </td>
                                    <td className="p-3">{org.plan_name}</td>
                                    <td className="p-3">{org.status_label}</td>
                                    <td className="p-3">{org.screens_count}</td>
                                    <td className="p-3">{org.members_count}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

PlatformOrganizations.layout = () => ({
    breadcrumbs: [
        { title: 'Platform', href: '/platform' },
        { title: 'Organizations', href: '/platform/organizations' },
    ],
});
