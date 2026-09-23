import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    InAppNotificationItem,
    NotificationChannelFlags,
    NotificationSettings,
} from '@/types';

type Props = {
    notifications: InAppNotificationItem[];
    settings: NotificationSettings;
    canManage: boolean;
};

const channels: Array<keyof NotificationChannelFlags> = [
    'email',
    'in_app',
    'webhook',
    'slack',
];

export default function NotificationsIndex({
    notifications,
    settings,
    canManage,
}: Props) {
    const slug = usePage().props.currentTeam?.slug ?? '';
    const [webhookUrl, setWebhookUrl] = useState(settings.webhook_url ?? '');
    const [slackUrl, setSlackUrl] = useState(settings.slack_webhook_url ?? '');
    const [minVersion, setMinVersion] = useState(settings.min_player_version ?? '');
    const [preferences, setPreferences] = useState(settings.preferences);

    const toggle = (
        event: string,
        channel: keyof NotificationChannelFlags,
        value: boolean,
    ) => {
        setPreferences((current) => ({
            ...current,
            [event]: {
                ...current[event],
                [channel]: value,
            },
        }));
    };

    const save = () => {
        router.patch(`/${slug}/notifications/settings`, {
            webhook_url: webhookUrl || null,
            slack_webhook_url: slackUrl || null,
            min_player_version: minVersion || null,
            preferences,
        });
    };

    return (
        <>
            <Head title="Notifications" />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title="Notifications"
                        description="In-app alerts, email, outbound webhooks, and Slack-compatible webhooks for screen and content events."
                    />
                    {notifications.some((item) => item.read_at === null) && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(`/${slug}/notifications/read-all`, {}, {
                                    preserveScroll: true,
                                    headers: {
                                        'X-Stay-On-Page': window.location.href,
                                    },
                                })
                            }
                        >
                            Mark all read
                        </Button>
                    )}
                </div>

                <section className="rounded-lg border">
                    <div className="border-b px-4 py-3">
                        <h2 className="font-semibold">Inbox</h2>
                    </div>
                    {notifications.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-8 text-sm">
                            No alerts yet.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {notifications.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex flex-col gap-2 px-4 py-3 md:flex-row md:items-start md:justify-between"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {item.url ? (
                                                <a
                                                    href={item.url}
                                                    className="hover:underline"
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        if (item.read_at === null) {
                                                            router.post(
                                                                `/${slug}/notifications/${item.id}/read`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                    headers: {
                                                                        'X-Stay-On-Page':
                                                                            window.location.href,
                                                                    },
                                                                    onSuccess: () =>
                                                                        router.visit(
                                                                            item.url as string,
                                                                        ),
                                                                },
                                                            );
                                                } else {
                                                    router.visit(item.url as string);
                                                }
                                                    }}
                                                >
                                                    {item.title}
                                                </a>
                                            ) : (
                                                item.title
                                            )}
                                            {item.read_at === null && (
                                                <span className="bg-primary ml-2 inline-block size-2 rounded-full align-middle" />
                                            )}
                                        </p>
                                        <p className="text-muted-foreground text-sm">
                                            {item.body}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            {item.event_label}
                                            {item.created_at
                                                ? ` · ${new Date(item.created_at).toLocaleString()}`
                                                : ''}
                                        </p>
                                    </div>
                                    {item.read_at === null && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    `/${slug}/notifications/${item.id}/read`,
                                                    {},
                                                    {
                                                        preserveScroll: true,
                                                        headers: {
                                                            'X-Stay-On-Page':
                                                                window.location.href,
                                                        },
                                                    },
                                                )
                                            }
                                        >
                                            Mark read
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {canManage && (
                    <section className="space-y-4 rounded-lg border p-4">
                        <h2 className="font-semibold">Delivery preferences</h2>
                        <p className="text-muted-foreground text-sm">
                            Owners and admins receive email and in-app alerts. Webhook
                            and Slack posts use the URLs below.
                        </p>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="webhook_url">Webhook URL</Label>
                                <Input
                                    id="webhook_url"
                                    value={webhookUrl}
                                    onChange={(event) =>
                                        setWebhookUrl(event.target.value)
                                    }
                                    placeholder="https://example.com/hooks/signage"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="slack_webhook_url">
                                    Slack-compatible webhook
                                </Label>
                                <Input
                                    id="slack_webhook_url"
                                    value={slackUrl}
                                    onChange={(event) =>
                                        setSlackUrl(event.target.value)
                                    }
                                    placeholder="https://hooks.slack.com/services/..."
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="min_player_version">
                                    Minimum player version
                                </Label>
                                <Input
                                    id="min_player_version"
                                    value={minVersion}
                                    onChange={(event) =>
                                        setMinVersion(event.target.value)
                                    }
                                    placeholder="1.2.0"
                                />
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left">
                                        <th className="px-2 py-2 font-medium">Event</th>
                                        {channels.map((channel) => (
                                            <th
                                                key={channel}
                                                className="px-2 py-2 font-medium capitalize"
                                            >
                                                {channel.replace('_', '-')}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {Object.entries(preferences).map(
                                        ([event, row]) => (
                                            <tr key={event} className="border-b">
                                                <td className="px-2 py-2">
                                                    {row.label}
                                                </td>
                                                {channels.map((channel) => (
                                                    <td
                                                        key={channel}
                                                        className="px-2 py-2"
                                                    >
                                                        <Checkbox
                                                            checked={row[channel]}
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                toggle(
                                                                    event,
                                                                    channel,
                                                                    checked === true,
                                                                )
                                                            }
                                                            aria-label={`${row.label} ${channel}`}
                                                        />
                                                    </td>
                                                ))}
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Button onClick={save}>Save preferences</Button>
                    </section>
                )}
            </div>
        </>
    );
}

NotificationsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Settings',
            href: '/settings/profile',
        },
        {
            title: 'Notifications',
            href: props.currentTeam
                ? `/${props.currentTeam.slug}/notifications`
                : '/',
        },
    ],
});
