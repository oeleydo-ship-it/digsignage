import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    name: string;
    status_label: string;
    last_seen_at: string | null;
    team: { name: string; slug: string } | null;
};

type Props = { filters: { search: string }; screens: Paginated<Row> };

export default function PlatformScreens({ filters, screens }: Props) {
    const [search, setSearch] = useState(filters.search);

    return (
        <>
            <Head title="Platform screens" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Screens" description="Every display registered across organizations." />
                <form
                    className="flex max-w-md gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get('/platform/screens', { search }, { preserveState: true });
                    }}
                >
                    <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search" />
                    <Button type="submit">Search</Button>
                </form>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="p-3">Screen</th>
                                <th className="p-3">Organization</th>
                                <th className="p-3">Status</th>
                                <th className="p-3">Last seen</th>
                            </tr>
                        </thead>
                        <tbody>
                            {screens.data.map((screen) => (
                                <tr key={screen.id} className="border-b">
                                    <td className="p-3">{screen.name}</td>
                                    <td className="p-3">{screen.team?.name ?? '—'}</td>
                                    <td className="p-3">{screen.status_label}</td>
                                    <td className="p-3">
                                        {screen.last_seen_at ? new Date(screen.last_seen_at).toLocaleString() : 'Never'}
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

PlatformScreens.layout = () => ({
    breadcrumbs: [
        { title: 'Platform', href: '/platform' },
        { title: 'Screens', href: '/platform/screens' },
    ],
});
