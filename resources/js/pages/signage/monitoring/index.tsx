import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { MonitoringSummary, ScreenHealthRecord } from '@/types';

type Props = {
    screens: ScreenHealthRecord[];
    counts: MonitoringSummary['counts'];
    thresholds: {
        healthy_seconds: number;
        warning_seconds: number;
    };
    canUpdateThresholds: boolean;
};

function formatBytes(value: number | null): string {
    if (value == null) {
        return '—';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let size = value;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
    }

    return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
}

function formatSeen(value: string | null): string {
    if (!value) {
        return 'Never';
    }

    return new Date(value).toLocaleString();
}

export default function MonitoringIndex({
    screens,
    counts,
    thresholds,
    canUpdateThresholds,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [healthy, setHealthy] = useState(String(thresholds.healthy_seconds));
    const [warning, setWarning] = useState(String(thresholds.warning_seconds));

    return (
        <>
            <Head title="Monitoring" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Screen monitoring"
                    description="Heartbeat health, last seen, current content, storage, player version, and errors. Offline detection uses team thresholds, not hard-coded values."
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryCard label="Healthy" value={counts.healthy} />
                    <SummaryCard label="Warning" value={counts.warning} />
                    <SummaryCard label="Offline" value={counts.offline} />
                    <SummaryCard label="Disabled" value={counts.disabled} />
                </div>

                {canUpdateThresholds && (
                    <form
                        className="grid max-w-xl gap-4 rounded-lg border p-4 sm:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.patch(`/${slug}/monitoring`, {
                                healthy_seconds: Number(healthy),
                                warning_seconds: Number(warning),
                            });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="healthy_seconds">
                                Healthy for (seconds)
                            </Label>
                            <Input
                                id="healthy_seconds"
                                type="number"
                                min={30}
                                value={healthy}
                                onChange={(event) => setHealthy(event.target.value)}
                                data-test="healthy-seconds"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="warning_seconds">
                                Warning until (seconds)
                            </Label>
                            <Input
                                id="warning_seconds"
                                type="number"
                                min={60}
                                value={warning}
                                onChange={(event) => setWarning(event.target.value)}
                                data-test="warning-seconds"
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <Button type="submit" data-test="save-thresholds">
                                Save thresholds
                            </Button>
                        </div>
                    </form>
                )}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Screen</th>
                                <th className="px-4 py-3 font-medium">Online</th>
                                <th className="px-4 py-3 font-medium">Last seen</th>
                                <th className="px-4 py-3 font-medium">Channel</th>
                                <th className="px-4 py-3 font-medium">Content</th>
                                <th className="px-4 py-3 font-medium">Storage</th>
                                <th className="px-4 py-3 font-medium">Player</th>
                                <th className="px-4 py-3 font-medium">Errors</th>
                            </tr>
                        </thead>
                        <tbody>
                            {screens.length === 0 ? (
                                <tr>
                                    <td
                                        className="text-muted-foreground px-4 py-6"
                                        colSpan={8}
                                    >
                                        No screens to monitor.
                                    </td>
                                </tr>
                            ) : (
                                screens.map((screen) => (
                                    <tr key={screen.id} className="border-t">
                                        <td className="px-4 py-3">
                                            <Link
                                                href={`/${slug}/screens/${screen.id}/commands`}
                                                className="font-medium hover:underline"
                                            >
                                                {screen.name}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant="outline">
                                                {screen.status_label}
                                            </Badge>
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">
                                            {formatSeen(screen.last_seen_at)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {screen.current_channel ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {screen.current_content ?? '—'}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">
                                            {formatBytes(screen.storage_available)}
                                            {screen.storage_total
                                                ? ` / ${formatBytes(screen.storage_total)}`
                                                : ''}
                                        </td>
                                        <td className="px-4 py-3">
                                            {screen.app_version ?? '—'}
                                        </td>
                                        <td className="text-destructive max-w-xs truncate px-4 py-3">
                                            {screen.last_error ?? '—'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function SummaryCard({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-muted-foreground text-sm">{label}</p>
            <p className="mt-2 text-3xl font-semibold">{value}</p>
        </div>
    );
}
