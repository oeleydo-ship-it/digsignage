import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as screensIndex } from '@/routes/screens';

type CommandRow = {
    id: number;
    command: string;
    command_label: string;
    payload: Record<string, unknown> | null;
    status: string;
    status_label: string;
    result: Record<string, unknown> | null;
    issued_by: string | null;
    created_at: string | null;
    sent_at: string | null;
    acknowledged_at: string | null;
    completed_at: string | null;
    expires_at: string | null;
};

type Props = {
    screen: {
        id: number;
        name: string;
        paired: boolean;
        status: string;
        status_label: string;
        current_channel_id: number | null;
    };
    commands: CommandRow[];
    channels: { id: number; name: string }[];
    types: { value: string; label: string }[];
    canCommand: boolean;
};

export default function ScreenCommands({
    screen,
    commands,
    channels,
    types,
    canCommand,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [command, setCommand] = useState('sync');
    const [channelId, setChannelId] = useState(
        screen.current_channel_id ? String(screen.current_channel_id) : '',
    );
    const [title, setTitle] = useState('Emergency');
    const [message, setMessage] = useState('');

    return (
        <>
            <Head title={`${screen.name} commands`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={`${screen.name} commands`}
                        description="Send remote player commands and review delivery history. REST polling is used when the live socket is unavailable."
                    />
                    <Button variant="outline" asChild>
                        <Link href={screensIndex(slug).url}>Back to screens</Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-3 text-sm">
                    <Badge variant="outline">{screen.status_label}</Badge>
                    <span className="text-muted-foreground">
                        {screen.paired ? 'Paired player' : 'Unpaired'}
                    </span>
                </div>

                {canCommand && screen.paired && (
                    <form
                        className="grid max-w-xl gap-4 rounded-lg border p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const payload: {
                                channel_id?: number;
                                title?: string;
                                message?: string;
                            } = {};

                            if (command === 'change-channel' && channelId !== '') {
                                payload.channel_id = Number(channelId);
                            }

                            if (command === 'emergency-start') {
                                payload.title = title;
                                payload.message = message;
                            }

                            router.post(
                                `/${slug}/screens/${screen.id}/commands`,
                                { command, payload },
                                { preserveScroll: true, preserveState: true },
                            );
                        }}
                    >
                                <div className="grid gap-2">
                                    <Label htmlFor="command">Command</Label>
                                    <Select
                                        value={command}
                                        onValueChange={setCommand}
                                    >
                                        <SelectTrigger id="command">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {types.map((type) => (
                                                <SelectItem
                                                    key={type.value}
                                                    value={type.value}
                                                >
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <input type="hidden" name="command" value={command} />
                                </div>

                                {command === 'change-channel' && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="channel_id">Channel</Label>
                                        <Select
                                            value={channelId}
                                            onValueChange={setChannelId}
                                        >
                                            <SelectTrigger id="channel_id">
                                                <SelectValue placeholder="Select a channel" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {channels.map((channel) => (
                                                    <SelectItem
                                                        key={channel.id}
                                                        value={String(channel.id)}
                                                    >
                                                        {channel.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}

                                {command === 'emergency-start' && (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="title">Title</Label>
                                            <Input
                                                id="title"
                                                value={title}
                                                onChange={(event) =>
                                                    setTitle(event.target.value)
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="message">Message</Label>
                                            <Input
                                                id="message"
                                                value={message}
                                                onChange={(event) =>
                                                    setMessage(event.target.value)
                                                }
                                            />
                                        </div>
                                    </>
                                )}

                                <Button type="submit" data-test="send-command">
                                    Send command
                                </Button>
                    </form>
                )}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">ID</th>
                                <th className="px-4 py-3 font-medium">Command</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Issued by</th>
                                <th className="px-4 py-3 font-medium">Created</th>
                                <th className="px-4 py-3 font-medium">Acknowledged</th>
                                <th className="px-4 py-3 font-medium">Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            {commands.length === 0 ? (
                                <tr>
                                    <td
                                        className="text-muted-foreground px-4 py-6"
                                        colSpan={7}
                                    >
                                        No commands yet.
                                    </td>
                                </tr>
                            ) : (
                                commands.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <td className="px-4 py-3">{row.id}</td>
                                        <td className="px-4 py-3">
                                            {row.command_label}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant="outline">
                                                {row.status_label}
                                            </Badge>
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">
                                            {row.issued_by ?? '—'}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">
                                            {row.created_at ?? '—'}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">
                                            {row.acknowledged_at ?? '—'}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">
                                            {row.completed_at ?? '—'}
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
