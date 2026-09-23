import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    name: string;
    email: string;
    is_platform_admin: boolean;
    team: { name: string; slug: string } | null;
    can_impersonate: boolean;
};

type Props = { filters: { search: string }; users: Paginated<Row> };

export default function PlatformUsers({ filters, users }: Props) {
    const [search, setSearch] = useState(filters.search);

    return (
        <>
            <Head title="Users" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Users" description="Search accounts and start a logged support impersonation." />
                <form
                    className="flex max-w-md gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get('/platform/users', { search }, { preserveState: true });
                    }}
                >
                    <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search" />
                    <Button type="submit">Search</Button>
                </form>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="p-3">User</th>
                                <th className="p-3">Organization</th>
                                <th className="p-3">Role</th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr key={user.id} className="border-b">
                                    <td className="p-3">
                                        {user.name}
                                        <div className="text-muted-foreground">{user.email}</div>
                                    </td>
                                    <td className="p-3">{user.team?.name ?? '—'}</td>
                                    <td className="p-3">{user.is_platform_admin ? 'Platform admin' : 'User'}</td>
                                    <td className="p-3">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.patch(`/platform/users/${user.id}`, {
                                                    is_platform_admin:
                                                        !user.is_platform_admin,
                                                })
                                            }
                                        >
                                            {user.is_platform_admin
                                                ? 'Revoke admin'
                                                : 'Make admin'}
                                        </Button>
                                        {user.can_impersonate && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => router.post(`/platform/users/${user.id}/impersonate`)}
                                            >
                                                Impersonate
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

PlatformUsers.layout = () => ({
    breadcrumbs: [
        { title: 'Platform', href: '/platform' },
        { title: 'Users', href: '/platform/users' },
    ],
});
