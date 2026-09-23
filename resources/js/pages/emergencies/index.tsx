import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import EmptyState from '@/components/empty-state';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import type { EmergencyPermissions, EmergencyRecord } from '@/types';
import type { Paginated } from '@/types/signage';

type Option = { id: number; name: string; depth?: number };
type Severity = { value: string; label: string };

type Props = {
    emergencies: Paginated<EmergencyRecord>;
    screens: Option[];
    locations: Option[];
    images: Option[];
    videos: Option[];
    severities: Severity[];
    permissions: EmergencyPermissions;
};

const emptyForm = {
    title: '',
    message: '',
    instructions: '',
    background: '#b91c1c',
    severity: 'emergency',
    image_id: '',
    video_id: '',
    starts_at: '',
    expires_at: '',
    screen_ids: [] as number[],
    location_ids: [] as number[],
};

export default function EmergenciesIndex({
    emergencies,
    screens,
    locations,
    images,
    videos,
    severities,
    permissions,
}: Props) {
    const slug = usePage().props.currentTeam?.slug ?? '';
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState(emptyForm);

    const toggleId = (key: 'screen_ids' | 'location_ids', id: number) => {
        setForm((current) => {
            const next = current[key].includes(id)
                ? current[key].filter((value) => value !== id)
                : [...current[key], id];

            return { ...current, [key]: next };
        });
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.post(`/${slug}/emergencies`, {
            ...form,
            image_id: form.image_id === '' ? null : Number(form.image_id),
            video_id: form.video_id === '' ? null : Number(form.video_id),
            starts_at: form.starts_at === '' ? null : form.starts_at,
            expires_at: form.expires_at === '' ? null : form.expires_at,
        });
    };

    return (
        <>
            <Head title="Emergencies" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Emergency broadcasts"
                        description="Override playback with a confirmed emergency or critical message. Location targets include descendant screens."
                    />
                    {permissions.canCreateEmergency && (
                        <Button onClick={() => setOpen((value) => !value)}>
                            {open ? 'Close' : 'New broadcast'}
                        </Button>
                    )}
                </div>

                {open && permissions.canCreateEmergency && (
                    <form
                        onSubmit={submit}
                        className="grid gap-4 rounded-xl border p-4 md:grid-cols-2"
                    >
                        <div className="space-y-2">
                            <Label htmlFor="title">Title</Label>
                            <Input
                                id="title"
                                value={form.title}
                                onChange={(event) =>
                                    setForm({ ...form, title: event.target.value })
                                }
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Severity</Label>
                            <Select
                                value={form.severity}
                                onValueChange={(value) =>
                                    setForm({ ...form, severity: value })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {severities.map((severity) => (
                                        <SelectItem
                                            key={severity.value}
                                            value={severity.value}
                                        >
                                            {severity.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="message">Message</Label>
                            <textarea
                                id="message"
                                className="border-input bg-background min-h-20 w-full rounded-md border px-3 py-2 text-sm"
                                value={form.message}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        message: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="instructions">Instructions</Label>
                            <textarea
                                id="instructions"
                                className="border-input bg-background min-h-20 w-full rounded-md border px-3 py-2 text-sm"
                                value={form.instructions}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        instructions: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Image</Label>
                            <Select
                                value={form.image_id || 'none'}
                                onValueChange={(value) =>
                                    setForm({
                                        ...form,
                                        image_id: value === 'none' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="None" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">None</SelectItem>
                                    {images.map((image) => (
                                        <SelectItem
                                            key={image.id}
                                            value={String(image.id)}
                                        >
                                            {image.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Video</Label>
                            <Select
                                value={form.video_id || 'none'}
                                onValueChange={(value) =>
                                    setForm({
                                        ...form,
                                        video_id: value === 'none' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="None" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">None</SelectItem>
                                    {videos.map((video) => (
                                        <SelectItem
                                            key={video.id}
                                            value={String(video.id)}
                                        >
                                            {video.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="background">Background</Label>
                            <Input
                                id="background"
                                type="color"
                                value={form.background}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        background: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="starts_at">Start</Label>
                            <Input
                                id="starts_at"
                                type="datetime-local"
                                value={form.starts_at}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        starts_at: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="expires_at">Expiration</Label>
                            <Input
                                id="expires_at"
                                type="datetime-local"
                                value={form.expires_at}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        expires_at: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Affected screens</Label>
                            <div className="max-h-40 space-y-2 overflow-auto rounded-md border p-3">
                                {screens.map((screen) => (
                                    <label
                                        key={screen.id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.screen_ids.includes(
                                                screen.id,
                                            )}
                                            onCheckedChange={() =>
                                                toggleId('screen_ids', screen.id)
                                            }
                                        />
                                        {screen.name}
                                    </label>
                                ))}
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label>Affected locations</Label>
                            <div className="max-h-40 space-y-2 overflow-auto rounded-md border p-3">
                                {locations.map((location) => (
                                    <label
                                        key={location.id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.location_ids.includes(
                                                location.id,
                                            )}
                                            onCheckedChange={() =>
                                                toggleId(
                                                    'location_ids',
                                                    location.id,
                                                )
                                            }
                                        />
                                        <span
                                            style={{
                                                paddingLeft: `${(location.depth ?? 0) * 12}px`,
                                            }}
                                        >
                                            {location.name}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </div>
                        <div className="md:col-span-2">
                            <Button type="submit">Save draft</Button>
                        </div>
                    </form>
                )}

                {emergencies.data.length === 0 ? (
                    <EmptyState
                        title="No emergency broadcasts"
                        description="Create a draft, confirm start, and it will override scheduled playback on the selected screens."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Title</th>
                                    <th className="px-4 py-3 font-medium">
                                        Severity
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Screens
                                    </th>
                                    <th className="px-4 py-3 font-medium">Start</th>
                                </tr>
                            </thead>
                            <tbody>
                                {emergencies.data.map((emergency) => (
                                    <tr key={emergency.id} className="border-t">
                                        <td className="px-4 py-3">
                                            <Link
                                                href={`/${slug}/emergencies/${emergency.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {emergency.title}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant="outline">
                                                {emergency.severity_label}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            {emergency.status_label}
                                        </td>
                                        <td className="px-4 py-3">
                                            {emergency.deliveries_count}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">
                                            {emergency.starts_at
                                                ? new Date(
                                                      emergency.starts_at,
                                                  ).toLocaleString()
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {emergencies.last_page > 1 && (
                    <div className="flex gap-2">
                        {emergencies.links.map((link) => (
                            <Button
                                key={link.label}
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
                                disabled={!link.url}
                                onClick={() =>
                                    link.url && router.get(link.url)
                                }
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

EmergenciesIndex.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Emergencies',
            href: props.currentTeam
                ? `/${props.currentTeam.slug}/emergencies`
                : '/',
        },
    ],
});
