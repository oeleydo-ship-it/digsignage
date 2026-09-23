import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AuditLogFilters, AuditLogRecord, Paginated } from '@/types';

type Option = { value: string; label: string };

type Props = {
    filters: AuditLogFilters;
    logs: Paginated<AuditLogRecord>;
    actions: Option[];
    users: Option[];
};

function when(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

function jsonPreview(value: Record<string, unknown> | null): string {
    if (!value) {
        return '—';
    }

    return JSON.stringify(value);
}

export default function AuditLogsIndex({ filters, logs, actions, users }: Props) {
    const slug = usePage().props.currentTeam?.slug ?? '';
    const [form, setForm] = useState({
        action: filters.action,
        resource_type: filters.resource_type,
        user_id: filters.user_id,
        search: filters.search,
        from: filters.from,
        until: filters.until,
    });

    const apply = () => {
        router.get(`/${slug}/audit-logs`, form, { preserveState: true });
    };

    return (
        <>
            <Head title="Audit logs" />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title="Audit logs"
                        description="Security-sensitive and administrative actions for this organization."
                    />
                    <Button
                        variant="outline"
                        asChild
                    >
                        <a
                            href={`/${slug}/audit-logs/export?${new URLSearchParams(form).toString()}`}
                        >
                            Export CSV
                        </a>
                    </Button>
                </div>

                <form
                    className="grid gap-3 rounded-lg border p-4 md:grid-cols-3 lg:grid-cols-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply();
                    }}
                >
                    <div className="space-y-1">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            value={form.search}
                            onChange={(event) =>
                                setForm((current) => ({
                                    ...current,
                                    search: event.target.value,
                                }))
                            }
                            placeholder="User, IP, action"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="action">Action</Label>
                        <select
                            id="action"
                            className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            value={form.action}
                            onChange={(event) =>
                                setForm((current) => ({
                                    ...current,
                                    action: event.target.value,
                                }))
                            }
                        >
                            <option value="">All actions</option>
                            {actions.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="user_id">User</Label>
                        <select
                            id="user_id"
                            className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            value={form.user_id}
                            onChange={(event) =>
                                setForm((current) => ({
                                    ...current,
                                    user_id: event.target.value,
                                }))
                            }
                        >
                            <option value="">Anyone</option>
                            {users.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="resource_type">Resource</Label>
                        <Input
                            id="resource_type"
                            value={form.resource_type}
                            onChange={(event) =>
                                setForm((current) => ({
                                    ...current,
                                    resource_type: event.target.value,
                                }))
                            }
                            placeholder="playlist, screen…"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            type="date"
                            value={form.from}
                            onChange={(event) =>
                                setForm((current) => ({
                                    ...current,
                                    from: event.target.value,
                                }))
                            }
                        />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="until">Until</Label>
                        <Input
                            id="until"
                            type="date"
                            value={form.until}
                            onChange={(event) =>
                                setForm((current) => ({
                                    ...current,
                                    until: event.target.value,
                                }))
                            }
                        />
                    </div>
                    <div className="flex items-end">
                        <Button type="submit">Filter</Button>
                    </div>
                </form>

                <section className="overflow-x-auto rounded-lg border">
                    {logs.data.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-8 text-sm">
                            No audit events match these filters.
                        </p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="px-3 py-2 font-medium">When</th>
                                    <th className="px-3 py-2 font-medium">User</th>
                                    <th className="px-3 py-2 font-medium">Action</th>
                                    <th className="px-3 py-2 font-medium">Resource</th>
                                    <th className="px-3 py-2 font-medium">IP</th>
                                    <th className="px-3 py-2 font-medium">Changes</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data.map((log) => (
                                    <tr key={log.id} className="border-b align-top">
                                        <td className="px-3 py-2 whitespace-nowrap">
                                            {when(log.created_at)}
                                        </td>
                                        <td className="px-3 py-2">
                                            {log.user_name ?? 'System'}
                                            {log.user_email ? (
                                                <div className="text-muted-foreground text-xs">
                                                    {log.user_email}
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className="px-3 py-2">{log.action_label}</td>
                                        <td className="px-3 py-2">
                                            {log.resource_type ?? '—'}
                                            {log.resource_id != null
                                                ? ` #${log.resource_id}`
                                                : ''}
                                        </td>
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {log.ip_address ?? '—'}
                                        </td>
                                        <td className="text-muted-foreground max-w-sm px-3 py-2 text-xs break-all">
                                            {jsonPreview(log.after ?? log.before)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
            </div>
        </>
    );
}

AuditLogsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Settings',
            href: '/settings/profile',
        },
        {
            title: 'Audit logs',
            href: props.currentTeam
                ? `/${props.currentTeam.slug}/audit-logs`
                : '/',
        },
    ],
});
