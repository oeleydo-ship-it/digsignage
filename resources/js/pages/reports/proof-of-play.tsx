import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type {
    Paginated,
    ProofOfPlayEvent,
    ProofOfPlayGroup,
    ProofOfPlayTotals,
} from '@/types';

type Option = { value: string; label: string };

type Filters = {
    screen_id: number | null;
    location_id: number | null;
    playlist_id: number | null;
    channel_id: number | null;
    content_id: string | null;
    status: string | null;
    from: string;
    until: string;
    group: string;
};

type Props = {
    filters: Filters;
    totals: ProofOfPlayTotals;
    grouped: ProofOfPlayGroup[];
    events: Paginated<ProofOfPlayEvent>;
    options: {
        screens: Option[];
        locations: Option[];
        playlists: Option[];
        channels: Option[];
        statuses: Option[];
        groups: Option[];
    };
};

function durationLabel(ms: number | null): string {
    if (ms == null || ms <= 0) {
        return '—';
    }

    const seconds = Math.round(ms / 1000);

    if (seconds < 60) {
        return `${seconds}s`;
    }

    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    return rest ? `${minutes}m ${rest}s` : `${minutes}m`;
}

function when(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

export default function ProofOfPlayReport({
    filters,
    totals,
    grouped,
    events,
    options,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [form, setForm] = useState({
        screen_id: filters.screen_id ? String(filters.screen_id) : '',
        location_id: filters.location_id ? String(filters.location_id) : '',
        playlist_id: filters.playlist_id ? String(filters.playlist_id) : '',
        channel_id: filters.channel_id ? String(filters.channel_id) : '',
        content_id: filters.content_id ?? '',
        status: filters.status ?? '',
        from: filters.from,
        until: filters.until,
        group: filters.group,
    });

    const query = {
        screen_id: form.screen_id || undefined,
        location_id: form.location_id || undefined,
        playlist_id: form.playlist_id || undefined,
        channel_id: form.channel_id || undefined,
        content_id: form.content_id || undefined,
        status: form.status || undefined,
        from: form.from,
        until: form.until,
        group: form.group,
    };

    const reportUrl = `/${slug}/reports/proof-of-play`;
    const params = new URLSearchParams();
    Object.entries(query).forEach(([key, value]) => {
        if (value) {
            params.set(key, String(value));
        }
    });
    const exportUrl = `${reportUrl}/export?${params.toString()}`;

    const apply = () => {
        router.get(reportUrl, query, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Proof of play" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Proof of play"
                        description="What each screen actually played, including events queued while a player was offline."
                    />
                    <Button asChild variant="outline" data-test="export-proof-of-play">
                        <a href={exportUrl}>
                            Export CSV
                        </a>
                    </Button>
                </div>

                <form
                    className="grid gap-3 rounded-lg border p-4 md:grid-cols-3 lg:grid-cols-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply();
                    }}
                >
                    <FilterSelect
                        label="Screen"
                        value={form.screen_id}
                        options={options.screens}
                        onChange={(screen_id) => setForm((current) => ({ ...current, screen_id }))}
                    />
                    <FilterSelect
                        label="Location"
                        value={form.location_id}
                        options={options.locations}
                        onChange={(location_id) =>
                            setForm((current) => ({ ...current, location_id }))
                        }
                    />
                    <FilterSelect
                        label="Playlist"
                        value={form.playlist_id}
                        options={options.playlists}
                        onChange={(playlist_id) =>
                            setForm((current) => ({ ...current, playlist_id }))
                        }
                    />
                    <FilterSelect
                        label="Channel"
                        value={form.channel_id}
                        options={options.channels}
                        onChange={(channel_id) => setForm((current) => ({ ...current, channel_id }))}
                    />
                    <div className="grid gap-1">
                        <Label htmlFor="content_id">Content</Label>
                        <Input
                            id="content_id"
                            value={form.content_id}
                            placeholder="Title or content id"
                            onChange={(event) =>
                                setForm((current) => ({
                                    ...current,
                                    content_id: event.target.value,
                                }))
                            }
                        />
                    </div>
                    <FilterSelect
                        label="Status"
                        value={form.status}
                        options={options.statuses}
                        onChange={(status) => setForm((current) => ({ ...current, status }))}
                    />
                    <div className="grid gap-1">
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            type="date"
                            value={form.from}
                            onChange={(event) =>
                                setForm((current) => ({ ...current, from: event.target.value }))
                            }
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="until">Until</Label>
                        <Input
                            id="until"
                            type="date"
                            value={form.until}
                            onChange={(event) =>
                                setForm((current) => ({ ...current, until: event.target.value }))
                            }
                        />
                    </div>
                    <FilterSelect
                        label="Group by"
                        value={form.group}
                        options={options.groups}
                        allowEmpty={false}
                        onChange={(group) => setForm((current) => ({ ...current, group }))}
                    />
                    <div className="flex items-end">
                        <Button type="submit" data-test="apply-proof-of-play">
                            Apply filters
                        </Button>
                    </div>
                </form>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryCard label="Plays" value={totals.plays} />
                    <SummaryCard
                        label="Play time"
                        value={durationLabel(totals.duration_ms)}
                    />
                    <SummaryCard label="Screens reached" value={totals.screens} />
                    <SummaryCard label="Content items" value={totals.content} />
                </div>

                <section className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3">Group</th>
                                <th className="p-3">Plays</th>
                                <th className="p-3">Duration</th>
                                <th className="p-3">Screens</th>
                            </tr>
                        </thead>
                        <tbody>
                            {grouped.length === 0 ? (
                                <tr>
                                    <td className="text-muted-foreground p-3" colSpan={4}>
                                        No playback in this range.
                                    </td>
                                </tr>
                            ) : (
                                grouped.map((row) => (
                                    <tr key={row.key} className="border-t">
                                        <td className="p-3">{row.label}</td>
                                        <td className="p-3">{row.plays}</td>
                                        <td className="p-3">
                                            {durationLabel(row.duration_ms)}
                                        </td>
                                        <td className="p-3">{row.screens}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </section>

                <section className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3">Started</th>
                                <th className="p-3">Screen</th>
                                <th className="p-3">Location</th>
                                <th className="p-3">Content</th>
                                <th className="p-3">Playlist</th>
                                <th className="p-3">Channel</th>
                                <th className="p-3">Duration</th>
                                <th className="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {events.data.length === 0 ? (
                                <tr>
                                    <td className="text-muted-foreground p-3" colSpan={8}>
                                        No events recorded yet.
                                    </td>
                                </tr>
                            ) : (
                                events.data.map((event) => (
                                    <tr key={event.id} className="border-t">
                                        <td className="p-3 whitespace-nowrap">
                                            {when(event.started_at)}
                                        </td>
                                        <td className="p-3">{event.screen ?? '—'}</td>
                                        <td className="p-3">{event.location ?? '—'}</td>
                                        <td className="p-3">
                                            {event.title ?? event.content_id ?? '—'}
                                        </td>
                                        <td className="p-3">{event.playlist ?? '—'}</td>
                                        <td className="p-3">{event.channel ?? '—'}</td>
                                        <td className="p-3">
                                            {durationLabel(event.duration_ms)}
                                        </td>
                                        <td className="p-3">{event.status_label}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </section>

                {events.last_page > 1 && (
                    <div className="flex flex-wrap gap-2">
                        {events.links.map((link, index) => (
                            <Button
                                key={`${link.label}-${index}`}
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
                                disabled={!link.url}
                                onClick={() =>
                                    link.url
                                        ? router.get(link.url, {}, { preserveState: true })
                                        : undefined
                                }
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function SummaryCard({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-lg border p-4">
            <p className="text-muted-foreground text-sm">{label}</p>
            <p className="text-2xl font-semibold">{value}</p>
        </div>
    );
}

function FilterSelect({
    label,
    value,
    options,
    onChange,
    allowEmpty = true,
}: {
    label: string;
    value: string;
    options: Option[];
    onChange: (value: string) => void;
    allowEmpty?: boolean;
}) {
    const id = label.toLowerCase().replaceAll(' ', '-');

    return (
        <div className="grid gap-1">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            >
                {allowEmpty ? <option value="">All</option> : null}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </div>
    );
}

ProofOfPlayReport.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Proof of play',
            href: props.currentTeam
                ? `/${props.currentTeam.slug}/reports/proof-of-play`
                : '/',
        },
    ],
});
