import { router } from '@inertiajs/react';
import {
    Bell,
    CheckCircle2,
    CircleAlert,
    Mail,
    MessageCircle,
    RotateCcw,
    Send,
    Smartphone,
} from 'lucide-react';
import { type ReactNode, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    QueueCustomerNotificationSettings,
    QueueNotificationChannelKey,
    QueueNotificationDeliveryRecord,
    QueueNotificationProvider,
} from '@/types';

type Errors = Record<string, string>;

const CHANNELS: {
    key: QueueNotificationChannelKey;
    label: string;
    icon: typeof Mail;
    hint: string;
}[] = [
    {
        key: 'email',
        label: 'Email',
        icon: Mail,
        hint: 'Sent with the platform email (Super admin → General settings).',
    },
    {
        key: 'sms',
        label: 'SMS',
        icon: Smartphone,
        hint: 'Text messages through Twilio or your own gateway.',
    },
    {
        key: 'whatsapp',
        label: 'WhatsApp',
        icon: MessageCircle,
        hint: 'WhatsApp messages through Twilio or the Meta Cloud API.',
    },
    {
        key: 'push',
        label: 'Push',
        icon: Bell,
        hint: 'Browser notifications for customers who tap "Notify me" on their virtual ticket.',
    },
];

const stay = {
    preserveScroll: true,
    preserveState: true,
    headers: {
        'X-Stay-On-Page':
            typeof window === 'undefined' ? '' : window.location.href,
    },
};

export function ChannelStatus({
    provider,
}: {
    provider: QueueNotificationProvider | undefined;
}) {
    if (!provider) {
        return null;
    }

    return (
        <span
            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium ${
                provider.status.ready
                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                    : 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
            }`}
        >
            {provider.status.ready ? (
                <CheckCircle2 className="size-3" />
            ) : (
                <CircleAlert className="size-3" />
            )}
            {provider.status.label}
        </span>
    );
}

function Field({
    id,
    label,
    error,
    hint,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-muted-foreground text-xs">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

/** Channel cards: where each channel delivers, plus a test send. */
export function NotificationChannels({
    slug,
    settings,
    canManage,
}: {
    slug: string;
    settings: QueueCustomerNotificationSettings;
    canManage: boolean;
}) {
    const [open, setOpen] = useState<QueueNotificationChannelKey | null>(null);

    return (
        <section className="dashboard-card space-y-4 p-5">
            <div>
                <h2 className="text-base font-semibold">Channels</h2>
                <p className="text-muted-foreground mt-1 text-sm">
                    Set up where each channel sends from, then pick which events
                    use it below. Messages are sent in the background and
                    retried up to three times.
                </p>
            </div>
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                {CHANNELS.map((channel) => {
                    const Icon = channel.icon;
                    const provider = settings.providers[channel.key];

                    return (
                        <button
                            key={channel.key}
                            type="button"
                            data-test={`channel-card-${channel.key}`}
                            onClick={() =>
                                setOpen(
                                    open === channel.key ? null : channel.key,
                                )
                            }
                            className={`hover:bg-accent/50 space-y-2 rounded-lg border p-4 text-left ${
                                open === channel.key
                                    ? 'border-primary ring-primary/30 ring-2'
                                    : ''
                            }`}
                        >
                            <span className="flex items-center justify-between gap-2">
                                <span className="flex items-center gap-2 font-medium">
                                    <Icon className="size-4" />
                                    {channel.label}
                                </span>
                                <ChannelStatus provider={provider} />
                            </span>
                            <span className="text-muted-foreground block text-xs">
                                {channel.hint}
                            </span>
                        </button>
                    );
                })}
            </div>

            {open && (
                <ChannelForm
                    key={open}
                    slug={slug}
                    channel={open}
                    provider={settings.providers[open]}
                    signatureHeader={settings.webhook_signature_header}
                    canManage={canManage}
                />
            )}
        </section>
    );
}

function ChannelForm({
    slug,
    channel,
    provider,
    signatureHeader,
    canManage,
}: {
    slug: string;
    channel: QueueNotificationChannelKey;
    provider: QueueNotificationProvider;
    signatureHeader: string;
    canManage: boolean;
}) {
    const [form, setForm] = useState({
        driver: provider.from_env ? '' : (provider.driver ?? ''),
        twilio_sid: provider.twilio_sid ?? '',
        twilio_token: '',
        twilio_from: provider.twilio_from ?? '',
        meta_phone_number_id: provider.meta_phone_number_id ?? '',
        meta_token: '',
        meta_template: provider.meta_template ?? '',
        meta_template_language: provider.meta_template_language ?? '',
        webhook_url: provider.from_env ? '' : (provider.webhook_url ?? ''),
        webhook_secret: '',
        enabled: provider.enabled ?? true,
        reply_to: provider.reply_to ?? '',
    });
    const [errors, setErrors] = useState<Errors>({});
    const [destination, setDestination] = useState('');
    const [testErrors, setTestErrors] = useState<Errors>({});
    const [sending, setSending] = useState(false);
    const base = `/${slug}/queue/settings/notification-channels/${channel}`;
    const set = (patch: Partial<typeof form>) =>
        setForm((current) => ({ ...current, ...patch }));
    const phone = channel === 'sms' || channel === 'whatsapp';

    const payload = (): Record<string, string | boolean | null> => {
        if (channel === 'email') {
            return { enabled: form.enabled, reply_to: form.reply_to || null };
        }

        if (channel === 'push') {
            return { enabled: form.enabled };
        }

        return {
            driver: form.driver || null,
            twilio_sid: form.twilio_sid || null,
            twilio_token: form.twilio_token || null,
            twilio_from: form.twilio_from || null,
            meta_phone_number_id: form.meta_phone_number_id || null,
            meta_token: form.meta_token || null,
            meta_template: form.meta_template || null,
            meta_template_language: form.meta_template_language || null,
            webhook_url: form.webhook_url || null,
            webhook_secret: form.webhook_secret || null,
        };
    };

    return (
        <div className="bg-muted/30 space-y-4 rounded-lg border p-4">
            {phone && (
                <>
                    {provider.from_env && (
                        <p className="rounded-md border border-sky-500/30 bg-sky-500/10 p-3 text-sm">
                            Currently sending to the webhook set in the server's
                            .env file. Choose a provider here to override it.
                        </p>
                    )}
                    <Field id={`${channel}-driver`} label="Provider">
                        <select
                            id={`${channel}-driver`}
                            value={form.driver}
                            data-test={`${channel}-driver`}
                            onChange={(event) =>
                                set({ driver: event.target.value })
                            }
                            className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        >
                            <option value="">Not set up</option>
                            <option value="twilio">Twilio</option>
                            {channel === 'whatsapp' && (
                                <option value="meta">
                                    Meta WhatsApp Cloud API
                                </option>
                            )}
                            <option value="webhook">
                                Webhook (your own gateway)
                            </option>
                        </select>
                    </Field>

                    {form.driver === 'twilio' && (
                        <div className="grid gap-4 md:grid-cols-3">
                            <Field
                                id={`${channel}-sid`}
                                label="Account SID"
                                error={errors.twilio_sid}
                            >
                                <Input
                                    id={`${channel}-sid`}
                                    value={form.twilio_sid}
                                    placeholder="AC…"
                                    className="font-mono text-xs"
                                    onChange={(event) =>
                                        set({
                                            twilio_sid:
                                                event.target.value.trim(),
                                        })
                                    }
                                />
                            </Field>
                            <Field
                                id={`${channel}-token`}
                                label="Auth token"
                                error={errors.twilio_token}
                                hint={
                                    provider.has_twilio_token
                                        ? 'Saved. Leave blank to keep it.'
                                        : undefined
                                }
                            >
                                <Input
                                    id={`${channel}-token`}
                                    type="password"
                                    autoComplete="off"
                                    value={form.twilio_token}
                                    className="font-mono text-xs"
                                    onChange={(event) =>
                                        set({
                                            twilio_token:
                                                event.target.value.trim(),
                                        })
                                    }
                                />
                            </Field>
                            <Field
                                id={`${channel}-from`}
                                label={
                                    channel === 'whatsapp'
                                        ? 'WhatsApp sender number'
                                        : 'Sender number'
                                }
                                error={errors.twilio_from}
                                hint="With country code, e.g. +15551234567."
                            >
                                <Input
                                    id={`${channel}-from`}
                                    value={form.twilio_from}
                                    placeholder="+15551234567"
                                    onChange={(event) =>
                                        set({ twilio_from: event.target.value })
                                    }
                                />
                            </Field>
                        </div>
                    )}

                    {form.driver === 'meta' && (
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                id="meta-phone-id"
                                label="Phone number ID"
                                error={errors.meta_phone_number_id}
                                hint="Meta Business → WhatsApp → API setup."
                            >
                                <Input
                                    id="meta-phone-id"
                                    value={form.meta_phone_number_id}
                                    onChange={(event) =>
                                        set({
                                            meta_phone_number_id:
                                                event.target.value.trim(),
                                        })
                                    }
                                />
                            </Field>
                            <Field
                                id="meta-token"
                                label="Access token"
                                error={errors.meta_token}
                                hint={
                                    provider.has_meta_token
                                        ? 'Saved. Leave blank to keep it.'
                                        : 'A permanent system-user token.'
                                }
                            >
                                <Input
                                    id="meta-token"
                                    type="password"
                                    autoComplete="off"
                                    value={form.meta_token}
                                    onChange={(event) =>
                                        set({
                                            meta_token:
                                                event.target.value.trim(),
                                        })
                                    }
                                />
                            </Field>
                            <Field
                                id="meta-template"
                                label="Message template (recommended)"
                                error={errors.meta_template}
                                hint="WhatsApp only delivers free text to customers who messaged you in the last 24 hours. Use an approved template with one body variable; the message fills it."
                            >
                                <Input
                                    id="meta-template"
                                    value={form.meta_template}
                                    placeholder="queue_update"
                                    onChange={(event) =>
                                        set({
                                            meta_template:
                                                event.target.value.trim(),
                                        })
                                    }
                                />
                            </Field>
                            <Field
                                id="meta-language"
                                label="Template language"
                                error={errors.meta_template_language}
                            >
                                <Input
                                    id="meta-language"
                                    value={form.meta_template_language}
                                    placeholder="en_US"
                                    onChange={(event) =>
                                        set({
                                            meta_template_language:
                                                event.target.value.trim(),
                                        })
                                    }
                                />
                            </Field>
                        </div>
                    )}

                    {form.driver === 'webhook' && (
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                id={`${channel}-webhook`}
                                label="Webhook URL"
                                error={errors.webhook_url}
                                hint='Receives a JSON POST: {"channel", "destination", "subject", "body", "data"}.'
                            >
                                <Input
                                    id={`${channel}-webhook`}
                                    value={form.webhook_url}
                                    placeholder="https://gateway.example.com/send"
                                    onChange={(event) =>
                                        set({
                                            webhook_url:
                                                event.target.value.trim(),
                                        })
                                    }
                                />
                            </Field>
                            <Field
                                id={`${channel}-secret`}
                                label="Signing secret (optional)"
                                error={errors.webhook_secret}
                                hint={
                                    <>
                                        Adds a <code>{signatureHeader}</code>{' '}
                                        header: sha256= HMAC of the body.
                                        {provider.has_webhook_secret
                                            ? ' Saved; leave blank to keep it.'
                                            : ''}
                                    </>
                                }
                            >
                                <Input
                                    id={`${channel}-secret`}
                                    type="password"
                                    autoComplete="off"
                                    value={form.webhook_secret}
                                    onChange={(event) =>
                                        set({
                                            webhook_secret: event.target.value,
                                        })
                                    }
                                />
                            </Field>
                        </div>
                    )}
                </>
            )}

            {channel === 'email' && (
                <div className="grid gap-4 md:grid-cols-2">
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.enabled}
                            onCheckedChange={(checked) =>
                                set({ enabled: checked === true })
                            }
                        />
                        Send email notifications
                    </label>
                    <Field
                        id="email-reply-to"
                        label="Reply-to address (optional)"
                        error={errors.reply_to}
                        hint="Where customer replies go."
                    >
                        <Input
                            id="email-reply-to"
                            type="email"
                            value={form.reply_to}
                            placeholder="frontdesk@example.com"
                            onChange={(event) =>
                                set({ reply_to: event.target.value })
                            }
                        />
                    </Field>
                </div>
            )}

            {channel === 'push' && (
                <label className="flex items-start gap-2 text-sm">
                    <Checkbox
                        className="mt-0.5"
                        checked={form.enabled}
                        onCheckedChange={(checked) =>
                            set({ enabled: checked === true })
                        }
                    />
                    <span>
                        Offer push notifications on virtual tickets
                        <span className="text-muted-foreground block text-xs">
                            Customers who join by QR code see a "Notify me"
                            button. Works on Chrome, Edge, Firefox and Safari
                            (iPhone: after adding the page to the Home Screen).
                            Needs HTTPS.
                        </span>
                    </span>
                </label>
            )}

            {canManage && (
                <Button
                    data-test={`save-channel-${channel}`}
                    onClick={() =>
                        router.put(base, payload(), {
                            ...stay,
                            onError: setErrors,
                            onSuccess: () => {
                                setErrors({});
                                set({
                                    twilio_token: '',
                                    meta_token: '',
                                    webhook_secret: '',
                                });
                            },
                        })
                    }
                >
                    Save
                </Button>
            )}

            {canManage && channel !== 'push' && (
                <div className="grid gap-2 border-t pt-4">
                    <Label htmlFor={`${channel}-test`}>Send a test</Label>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Input
                            id={`${channel}-test`}
                            value={destination}
                            data-test={`test-destination-${channel}`}
                            placeholder={
                                channel === 'email'
                                    ? 'you@example.com'
                                    : '+15551234567'
                            }
                            onChange={(event) =>
                                setDestination(event.target.value)
                            }
                            className="sm:max-w-xs"
                        />
                        <Button
                            variant="outline"
                            disabled={sending || destination.trim() === ''}
                            onClick={() => {
                                setSending(true);
                                router.post(
                                    `${base}/test`,
                                    { destination: destination.trim() },
                                    {
                                        ...stay,
                                        onError: setTestErrors,
                                        onSuccess: () => setTestErrors({}),
                                        onFinish: () => setSending(false),
                                    },
                                );
                            }}
                        >
                            <Send className="size-4" />
                            Send test
                        </Button>
                    </div>
                    <InputError message={testErrors.destination} />
                    <p className="text-muted-foreground text-xs">
                        Sends the "Ticket called" message right away with sample
                        details. Save your settings first.
                    </p>
                </div>
            )}
        </div>
    );
}

/** Editable wording for every event, with placeholders. */
export function NotificationTemplates({
    slug,
    settings,
    canManage,
}: {
    slug: string;
    settings: QueueCustomerNotificationSettings;
    canManage: boolean;
}) {
    const [templates, setTemplates] = useState(settings.templates);
    const [errors, setErrors] = useState<Errors>({});

    return (
        <section className="dashboard-card space-y-4 p-5">
            <div>
                <h2 className="text-base font-semibold">Message templates</h2>
                <p className="text-muted-foreground mt-1 text-sm">
                    The subject is used for email and push titles. Keep SMS
                    messages short: about 160 characters is one text.
                </p>
                <div className="mt-2 flex flex-wrap gap-1.5">
                    {Object.entries(settings.placeholders).map(
                        ([key, label]) => (
                            <code
                                key={key}
                                title={label}
                                className="bg-muted rounded px-1.5 py-0.5 text-xs"
                            >
                                {`{${key}}`}
                            </code>
                        ),
                    )}
                </div>
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                {settings.events.map((event) => {
                    const value = templates[event.value] ?? {
                        subject: '',
                        body: '',
                    };
                    const fallback = settings.default_templates[event.value];
                    const changed =
                        fallback !== undefined &&
                        (value.subject !== fallback.subject ||
                            value.body !== fallback.body);

                    return (
                        <div
                            key={event.value}
                            className="space-y-2 rounded-lg border p-3"
                            data-test={`template-${event.value}`}
                        >
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-sm font-medium">
                                    {event.label}
                                </p>
                                {changed && canManage && (
                                    <button
                                        type="button"
                                        className="text-primary text-xs hover:underline"
                                        onClick={() =>
                                            setTemplates({
                                                ...templates,
                                                [event.value]: { ...fallback },
                                            })
                                        }
                                    >
                                        Reset
                                    </button>
                                )}
                            </div>
                            <Input
                                aria-label={`${event.label} subject`}
                                value={value.subject}
                                maxLength={150}
                                disabled={!canManage}
                                onChange={(change) =>
                                    setTemplates({
                                        ...templates,
                                        [event.value]: {
                                            ...value,
                                            subject: change.target.value,
                                        },
                                    })
                                }
                            />
                            <textarea
                                aria-label={`${event.label} message`}
                                value={value.body}
                                maxLength={1000}
                                rows={3}
                                disabled={!canManage}
                                onChange={(change) =>
                                    setTemplates({
                                        ...templates,
                                        [event.value]: {
                                            ...value,
                                            body: change.target.value,
                                        },
                                    })
                                }
                                className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                            />
                            <p className="text-muted-foreground text-right text-[11px] tabular-nums">
                                {value.body.length} characters
                            </p>
                            <InputError
                                message={
                                    errors[`templates.${event.value}.body`] ??
                                    errors[`templates.${event.value}.subject`]
                                }
                            />
                        </div>
                    );
                })}
            </div>
            {canManage && (
                <Button
                    data-test="save-templates"
                    onClick={() =>
                        router.put(
                            `/${slug}/queue/settings/notification-templates`,
                            { templates },
                            {
                                ...stay,
                                onError: setErrors,
                                onSuccess: () => setErrors({}),
                            },
                        )
                    }
                >
                    Save templates
                </Button>
            )}
        </section>
    );
}

const STATUS_TONE: Record<QueueNotificationDeliveryRecord['status'], string> = {
    sent: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    pending: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    failed: 'bg-rose-500/15 text-rose-700 dark:text-rose-300',
    skipped: 'bg-muted text-muted-foreground',
};

/** The latest deliveries, with the reason for anything not sent. */
export function NotificationDeliveryLog({
    slug,
    deliveries,
    canManage,
}: {
    slug: string;
    deliveries: QueueNotificationDeliveryRecord[];
    canManage: boolean;
}) {
    return (
        <section className="dashboard-card overflow-x-auto">
            <div className="flex flex-wrap items-center justify-between gap-2 p-5 pb-3">
                <div>
                    <h2 className="text-base font-semibold">
                        Recent notifications
                    </h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        The last 30 messages to customers.
                    </p>
                </div>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.reload({ only: ['customerNotifications'] })
                    }
                >
                    <RotateCcw className="size-3.5" />
                    Refresh
                </Button>
            </div>
            {deliveries.length === 0 ? (
                <p className="text-muted-foreground px-5 pb-5 text-sm">
                    Nothing sent yet. Turn on an event below and issue a ticket
                    with a phone number or email to see it here.
                </p>
            ) : (
                <table className="w-full min-w-[760px] text-left text-sm">
                    <thead className="text-muted-foreground border-y text-xs tracking-wide uppercase">
                        <tr>
                            <th className="px-5 py-2 font-medium">When</th>
                            <th className="px-3 py-2 font-medium">Event</th>
                            <th className="px-3 py-2 font-medium">Channel</th>
                            <th className="px-3 py-2 font-medium">To</th>
                            <th className="px-3 py-2 font-medium">Status</th>
                            <th className="px-5 py-2" />
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {deliveries.map((delivery) => (
                            <tr
                                key={delivery.id}
                                className="align-top"
                                data-test={`delivery-${delivery.id}`}
                            >
                                <td className="text-muted-foreground px-5 py-2.5 text-xs whitespace-nowrap">
                                    {delivery.created_at
                                        ? new Date(
                                              delivery.created_at,
                                          ).toLocaleString(undefined, {
                                              dateStyle: 'short',
                                              timeStyle: 'short',
                                          })
                                        : '—'}
                                </td>
                                <td className="px-3 py-2.5">
                                    {delivery.event}
                                    {delivery.ticket && (
                                        <span className="text-muted-foreground block font-mono text-xs">
                                            {delivery.ticket}
                                        </span>
                                    )}
                                </td>
                                <td className="px-3 py-2.5">
                                    {delivery.channel_label}
                                </td>
                                <td className="max-w-48 truncate px-3 py-2.5">
                                    {delivery.destination ?? '—'}
                                </td>
                                <td className="px-3 py-2.5">
                                    <span
                                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize ${STATUS_TONE[delivery.status]}`}
                                    >
                                        {delivery.status}
                                    </span>
                                    {delivery.error && (
                                        <p className="mt-1 max-w-72 text-xs text-rose-600 dark:text-rose-400">
                                            {delivery.error}
                                        </p>
                                    )}
                                </td>
                                <td className="px-5 py-2.5 text-right">
                                    {canManage &&
                                        (delivery.status === 'failed' ||
                                            delivery.status === 'skipped') &&
                                        delivery.destination && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    router.post(
                                                        `/${slug}/queue/settings/notification-deliveries/${delivery.id}/retry`,
                                                        {},
                                                        stay,
                                                    )
                                                }
                                            >
                                                Retry
                                            </Button>
                                        )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </section>
    );
}
