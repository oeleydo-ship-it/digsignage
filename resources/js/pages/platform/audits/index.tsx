import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    action_label: string;
    resource_type: string | null;
    resource_id: number | null;
    ip_address: string | null;
    actor_name: string | null;
    created_at: string | null;
};

type Props = {
    filters: { action: string; search: string };
    logs: Paginated<Row>;
    actions: { value: string; label: string }[];
};

export default function PlatformAudits({ filters, logs, actions }: Props) {
    const [form, setForm] = useState(filters);

    return (
        <>
            <Head title="Platform audit logs" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Platform audit logs" description="Impersonation, billing overrides, flags, and job actions." />
                <form
                    className="grid gap-3 md:grid-cols-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get('/platform/audits', form, { preserveState: true });
                    }}
                >
                    <div className="space-y-1">
                        <Label htmlFor="action">Action</Label>
                        <select
                            id="action"
                            className="w-full rounded-md border bg-background p-2"
                            value={form.action}
                            onChange={(event) => setForm({ ...form, action: event.target.value })}
                        >
                            <option value="">All</option>
                            {actions.map((action) => (
                                <option key={action.value} value={action.value}>
                                    {action.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            value={form.search}
                            onChange={(event) => setForm({ ...form, search: event.target.value })}
                        />
                    </div>
                    <Button className="self-end" type="submit">
                        Filter
                    </Button>
                </form>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="p-3">When</th>
                                <th className="p-3">Action</th>
                                <th className="p-3">Actor</th>
                                <th className="p-3">Resource</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log) => (
                                <tr key={log.id} className="border-b">
                                    <td className="p-3">
                                        {log.created_at ? new Date(log.created_at).toLocaleString() : '—'}
                                    </td>
                                    <td className="p-3">{log.action_label}</td>
                                    <td className="p-3">{log.actor_name ?? '—'}</td>
                                    <td className="p-3">
                                        {log.resource_type ?? '—'} {log.resource_id ?? ''}
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

PlatformAudits.layout = () => ({
    breadcrumbs: [
        { title: 'Platform', href: '/platform' },
        { title: 'Audit logs', href: '/platform/audits' },
    ],
});
