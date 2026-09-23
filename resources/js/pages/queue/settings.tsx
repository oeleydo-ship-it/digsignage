import { router, usePage } from '@inertiajs/react';
import { Flag } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    QueuePageShell,
    queueBreadcrumbs,
} from '@/pages/queue/queue-page-shell';
import { update as updateSettings } from '@/routes/queue/settings';
import { update as updateAlertSettings } from '@/routes/queue/settings/alerts';
import { update as updateVoiceSettings } from '@/routes/queue/settings/voice';
import { destroy, store, update } from '@/routes/queue/priorities';
import type {
    QueuePermissions,
    QueueAlertSettings,
    QueuePriorityRecord,
    QueueStarvation,
    QueueVoiceLanguage,
    QueueVoiceSettings,
    QueueCustomerNotificationSettings,
} from '@/types';

type Option = { value: string; label: string };

type Props = {
    title: string;
    priorities: QueuePriorityRecord[];
    starvation: QueueStarvation;
    alerts: QueueAlertSettings;
    voice: QueueVoiceSettings;
    customerNotifications: QueueCustomerNotificationSettings;
    strategies: Option[];
    permissions: QueuePermissions;
};

const emptyForm = {
    name: '',
    code: '',
    weight: '0',
    color: '#64748b',
    is_active: true,
};

type FormState = typeof emptyForm;

function stayOnPage() {
    return {
        preserveScroll: true,
        headers: {
            'X-Stay-On-Page': window.location.href,
        },
    };
}

export default function QueueSettings({
    priorities,
    starvation,
    alerts,
    voice,
    customerNotifications,
    permissions,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<QueuePriorityRecord | null>(null);
    const [deleting, setDeleting] = useState<QueuePriorityRecord | null>(null);
    const [form, setForm] = useState<FormState>(emptyForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [maxWait, setMaxWait] = useState(
        starvation.max_priority_wait_seconds
            ? String(starvation.max_priority_wait_seconds)
            : '',
    );
    const [promoteAfter, setPromoteAfter] = useState(
        starvation.promote_after_seconds
            ? String(starvation.promote_after_seconds)
            : '',
    );
    const [starvationErrors, setStarvationErrors] = useState<
        Record<string, string>
    >({});
    const [voiceForm, setVoiceForm] = useState(voice);
    const [alertForm, setAlertForm] = useState(alerts);
    const [alertErrors, setAlertErrors] = useState<Record<string, string>>({});
    const [voiceErrors, setVoiceErrors] = useState<Record<string, string>>({});
    const [notificationRules, setNotificationRules] = useState(
        customerNotifications.rules,
    );
    const [appointmentNoticeMinutes, setAppointmentNoticeMinutes] = useState(
        customerNotifications.appointment_minutes_before,
    );
    const [notificationErrors, setNotificationErrors] = useState<
        Record<string, string>
    >({});

    const openCreate = () => {
        setEditing(null);
        setForm(emptyForm);
        setErrors({});
        setOpen(true);
    };

    const openEdit = (priority: QueuePriorityRecord) => {
        setEditing(priority);
        setForm({
            name: priority.name,
            code: priority.code,
            weight: String(priority.weight),
            color: priority.color ?? '#64748b',
            is_active: priority.is_active,
        });
        setErrors({});
        setOpen(true);
    };

    const savePriority = () => {
        const payload = {
            name: form.name,
            code: form.code === '' ? null : form.code,
            weight: Number(form.weight),
            color: form.color === '' ? null : form.color,
            is_active: form.is_active,
        };

        if (editing) {
            router.patch(update.url([slug, editing.id]), payload, {
                ...stayOnPage(),
                onError: setErrors,
                onSuccess: () => setOpen(false),
            });

            return;
        }

        router.post(store.url(slug), payload, {
            ...stayOnPage(),
            onError: setErrors,
            onSuccess: () => setOpen(false),
        });
    };

    const saveStarvation = () => {
        router.patch(
            updateSettings.url(slug),
            {
                max_priority_wait_seconds:
                    maxWait === '' ? null : Number(maxWait),
                promote_after_seconds:
                    promoteAfter === '' ? null : Number(promoteAfter),
            },
            {
                ...stayOnPage(),
                onError: setStarvationErrors,
            },
        );
    };

    const saveVoice = () => {
        router.patch(updateVoiceSettings.url(slug), voiceForm, {
            ...stayOnPage(),
            onError: setVoiceErrors,
        });
    };

    const saveAlerts = () => {
        router.patch(updateAlertSettings.url(slug), alertForm, {
            ...stayOnPage(),
            onError: setAlertErrors,
        });
    };

    const languageOptions: Array<{ value: QueueVoiceLanguage; label: string }> =
        [
            { value: 'en-US', label: 'English' },
            { value: 'ar-AE', label: 'Arabic' },
            { value: 'fil-PH', label: 'Tagalog' },
            { value: 'hi-IN', label: 'Hindi' },
        ];

    const saveCustomerNotifications = () => {
        router.patch(
            `/${slug}/queue/settings/customer-notifications`,
            {
                rules: notificationRules,
                appointment_minutes_before: appointmentNoticeMinutes,
            },
            {
                ...stayOnPage(),
                onError: setNotificationErrors,
            },
        );
    };

    return (
        <>
            <QueuePageShell
                title="Settings"
                description="Configure queue priorities, announcements, customer notifications, and operational alerts."
            >
                <section className="dashboard-card space-y-4 p-5">
                    <div>
                        <h2 className="text-base font-semibold">
                            Starvation protection
                        </h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            On Priority services, a lower-weight ticket that has
                            waited this long is served before newer
                            higher-weight tickets. Leave blank to disable.
                            Individual services choose FIFO or Priority on the
                            Services page.
                        </p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="max-priority-wait">
                                Max wait before skipping priority (seconds)
                            </Label>
                            <Input
                                id="max-priority-wait"
                                data-test="starvation-max-wait"
                                type="number"
                                min={1}
                                value={maxWait}
                                onChange={(event) =>
                                    setMaxWait(event.target.value)
                                }
                                placeholder="Disabled"
                            />
                            <InputError
                                message={
                                    starvationErrors.max_priority_wait_seconds
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="promote-after">
                                Promote after (seconds)
                            </Label>
                            <Input
                                id="promote-after"
                                data-test="starvation-promote-after"
                                type="number"
                                min={1}
                                value={promoteAfter}
                                onChange={(event) =>
                                    setPromoteAfter(event.target.value)
                                }
                                placeholder="Disabled"
                            />
                            <InputError
                                message={starvationErrors.promote_after_seconds}
                            />
                        </div>
                    </div>
                    {permissions.canManageSettings && (
                        <Button
                            type="button"
                            data-test="save-queue-starvation"
                            onClick={saveStarvation}
                        >
                            Save starvation rules
                        </Button>
                    )}
                </section>

                <section className="dashboard-card space-y-4 p-5">
                    <div>
                        <h2 className="text-base font-semibold">
                            Automated operational alerts
                        </h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Notify queue administrators when service levels or
                            counter availability need attention. Delivery
                            channels are controlled in Notifications settings.
                        </p>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            data-test="queue-alerts-enabled"
                            checked={alertForm.enabled}
                            onCheckedChange={(checked) =>
                                setAlertForm({
                                    ...alertForm,
                                    enabled: checked === true,
                                })
                            }
                        />
                        Enable automated queue alerts
                    </label>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="grid gap-2">
                            <Label htmlFor="queue-average-wait-alert">
                                Average wait above (minutes)
                            </Label>
                            <Input
                                id="queue-average-wait-alert"
                                type="number"
                                min={1}
                                max={1440}
                                value={alertForm.average_wait_minutes}
                                onChange={(event) =>
                                    setAlertForm({
                                        ...alertForm,
                                        average_wait_minutes: Number(
                                            event.target.value,
                                        ),
                                    })
                                }
                            />
                            <InputError
                                message={alertErrors.average_wait_minutes}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="queue-waiting-count-alert">
                                Waiting customers above
                            </Label>
                            <Input
                                id="queue-waiting-count-alert"
                                type="number"
                                min={1}
                                max={100000}
                                value={alertForm.waiting_customers}
                                onChange={(event) =>
                                    setAlertForm({
                                        ...alertForm,
                                        waiting_customers: Number(
                                            event.target.value,
                                        ),
                                    })
                                }
                            />
                            <InputError
                                message={alertErrors.waiting_customers}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="queue-customer-wait-alert">
                                Customer wait above (minutes)
                            </Label>
                            <Input
                                id="queue-customer-wait-alert"
                                type="number"
                                min={1}
                                max={10080}
                                value={alertForm.customer_wait_minutes}
                                onChange={(event) =>
                                    setAlertForm({
                                        ...alertForm,
                                        customer_wait_minutes: Number(
                                            event.target.value,
                                        ),
                                    })
                                }
                            />
                            <InputError
                                message={alertErrors.customer_wait_minutes}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="queue-alert-cooldown">
                                Repeat cooldown (minutes)
                            </Label>
                            <Input
                                id="queue-alert-cooldown"
                                type="number"
                                min={1}
                                max={1440}
                                value={alertForm.cooldown_minutes}
                                onChange={(event) =>
                                    setAlertForm({
                                        ...alertForm,
                                        cooldown_minutes: Number(
                                            event.target.value,
                                        ),
                                    })
                                }
                            />
                            <InputError
                                message={alertErrors.cooldown_minutes}
                            />
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        {[
                            ['no_counter_available', 'No counter available'],
                            ['capacity_reached', 'Queue capacity reached'],
                            ['counter_offline', 'Counter paused / offline'],
                        ].map(([key, label]) => (
                            <label
                                key={key}
                                className="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    checked={Boolean(
                                        alertForm[
                                            key as keyof QueueAlertSettings
                                        ],
                                    )}
                                    onCheckedChange={(checked) =>
                                        setAlertForm({
                                            ...alertForm,
                                            [key]: checked === true,
                                        })
                                    }
                                />
                                {label}
                            </label>
                        ))}
                    </div>
                    {permissions.canManageSettings && (
                        <Button
                            type="button"
                            data-test="save-queue-alerts"
                            onClick={saveAlerts}
                        >
                            Save alert rules
                        </Button>
                    )}
                </section>

                <section className="dashboard-card space-y-4 p-5">
                    <div>
                        <h2 className="text-base font-semibold">
                            Voice announcements
                        </h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Announce called ticket numbers on displays whose
                            queue widget has Voice announcements enabled.
                        </p>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            data-test="queue-voice-enabled"
                            checked={voiceForm.enabled}
                            onCheckedChange={(checked) =>
                                setVoiceForm({
                                    ...voiceForm,
                                    enabled: checked === true,
                                })
                            }
                        />
                        Enable automatic voice announcements
                    </label>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Announcement languages</Label>
                            <div className="flex flex-wrap gap-4">
                                {languageOptions.map((option) => (
                                    <label
                                        key={option.value}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={voiceForm.languages.includes(
                                                option.value,
                                            )}
                                            onCheckedChange={(checked) =>
                                                setVoiceForm({
                                                    ...voiceForm,
                                                    languages:
                                                        checked === true
                                                            ? [
                                                                  ...new Set([
                                                                      ...voiceForm.languages,
                                                                      option.value,
                                                                  ]),
                                                              ]
                                                            : voiceForm.languages.filter(
                                                                  (language) =>
                                                                      language !==
                                                                      option.value,
                                                              ),
                                                })
                                            }
                                        />
                                        {option.label}
                                    </label>
                                ))}
                            </div>
                            <InputError message={voiceErrors.languages} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="queue-voice-name">
                                Preferred system voice
                            </Label>
                            <Input
                                id="queue-voice-name"
                                value={voiceForm.voice ?? ''}
                                onChange={(event) =>
                                    setVoiceForm({
                                        ...voiceForm,
                                        voice: event.target.value || null,
                                    })
                                }
                                placeholder="Automatic for each language"
                            />
                            <InputError message={voiceErrors.voice} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="queue-voice-speed">
                                Speed ({voiceForm.speed.toFixed(1)}×)
                            </Label>
                            <Input
                                id="queue-voice-speed"
                                type="range"
                                min={0.5}
                                max={2}
                                step={0.1}
                                value={voiceForm.speed}
                                onChange={(event) =>
                                    setVoiceForm({
                                        ...voiceForm,
                                        speed: Number(event.target.value),
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="queue-voice-volume">
                                Volume ({Math.round(voiceForm.volume * 100)}%)
                            </Label>
                            <Input
                                id="queue-voice-volume"
                                type="range"
                                min={0}
                                max={1}
                                step={0.05}
                                value={voiceForm.volume}
                                onChange={(event) =>
                                    setVoiceForm({
                                        ...voiceForm,
                                        volume: Number(event.target.value),
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="queue-voice-repeat">
                                Repeat count
                            </Label>
                            <Input
                                id="queue-voice-repeat"
                                type="number"
                                min={1}
                                max={5}
                                value={voiceForm.repeat_count}
                                onChange={(event) =>
                                    setVoiceForm({
                                        ...voiceForm,
                                        repeat_count: Number(
                                            event.target.value,
                                        ),
                                    })
                                }
                            />
                            <InputError message={voiceErrors.repeat_count} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="queue-announcement-delay">
                                Delay between announcements (seconds)
                            </Label>
                            <Input
                                id="queue-announcement-delay"
                                data-test="queue-announcement-delay"
                                type="number"
                                min={0}
                                max={30}
                                step={0.5}
                                value={voiceForm.announcement_delay_seconds}
                                onChange={(event) =>
                                    setVoiceForm({
                                        ...voiceForm,
                                        announcement_delay_seconds: Number(
                                            event.target.value,
                                        ),
                                    })
                                }
                            />
                            <InputError
                                message={voiceErrors.announcement_delay_seconds}
                            />
                        </div>
                        <label className="flex items-center gap-2 self-end pb-2 text-sm">
                            <Checkbox
                                checked={voiceForm.chime}
                                onCheckedChange={(checked) =>
                                    setVoiceForm({
                                        ...voiceForm,
                                        chime: checked === true,
                                    })
                                }
                            />
                            Play an announcement chime
                        </label>
                    </div>
                    {permissions.canManageSettings && (
                        <Button
                            type="button"
                            data-test="save-queue-voice"
                            onClick={saveVoice}
                        >
                            Save voice settings
                        </Button>
                    )}
                </section>

                <section className="dashboard-card space-y-4 p-5">
                    <div>
                        <h2 className="text-base font-semibold">
                            Customer notifications
                        </h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Queue delivery is processed asynchronously. Email
                            uses the configured mailer; SMS, WhatsApp, and push
                            use the server provider webhooks.
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[620px] text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="px-2 py-2">Event</th>
                                    {customerNotifications.channels.map(
                                        (channel) => (
                                            <th
                                                key={channel.value}
                                                className="px-2 py-2"
                                            >
                                                {channel.label}
                                            </th>
                                        ),
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {customerNotifications.events.map((event) => (
                                    <tr key={event.value} className="border-b">
                                        <td className="px-2 py-2 font-medium">
                                            {event.label}
                                        </td>
                                        {customerNotifications.channels.map(
                                            (channel) => (
                                                <td
                                                    key={channel.value}
                                                    className="px-2 py-2"
                                                >
                                                    <Checkbox
                                                        checked={
                                                            notificationRules[
                                                                event.value
                                                            ]?.[
                                                                channel.value
                                                            ] ?? false
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setNotificationRules(
                                                                (current) => ({
                                                                    ...current,
                                                                    [event.value]:
                                                                        {
                                                                            ...current[
                                                                                event
                                                                                    .value
                                                                            ],
                                                                            [channel.value]:
                                                                                checked ===
                                                                                true,
                                                                        },
                                                                }),
                                                            )
                                                        }
                                                    />
                                                </td>
                                            ),
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="grid max-w-sm gap-2">
                        <Label htmlFor="appointment-notice-minutes">
                            Appointment reminder lead time (minutes)
                        </Label>
                        <Input
                            id="appointment-notice-minutes"
                            type="number"
                            min={1}
                            max={10080}
                            value={appointmentNoticeMinutes}
                            onChange={(event) =>
                                setAppointmentNoticeMinutes(
                                    Number(event.target.value),
                                )
                            }
                        />
                        <InputError
                            message={
                                notificationErrors.appointment_minutes_before
                            }
                        />
                    </div>
                    {permissions.canManageSettings ? (
                        <Button
                            data-test="save-customer-notifications"
                            onClick={saveCustomerNotifications}
                        >
                            Save customer notification rules
                        </Button>
                    ) : null}
                </section>

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <p className="text-muted-foreground text-sm">
                        Higher weight is served first. Defaults (Normal, Senior,
                        VIP, Emergency) are seeded once and fully editable.
                    </p>
                    {permissions.canManageSettings && (
                        <Button
                            onClick={openCreate}
                            data-test="create-queue-priority"
                        >
                            <Flag className="size-4" />
                            Add priority
                        </Button>
                    )}
                </div>

                <section className="dashboard-card overflow-x-auto">
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="border-b text-xs tracking-wide uppercase">
                            <tr className="text-muted-foreground">
                                <th className="px-5 py-3 font-medium">Name</th>
                                <th className="px-3 py-3 font-medium">Code</th>
                                <th className="px-3 py-3 font-medium">
                                    Weight
                                </th>
                                <th className="px-3 py-3 font-medium">Color</th>
                                <th className="px-3 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-5 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {priorities.map((priority) => (
                                <tr
                                    key={priority.id}
                                    data-test={`queue-priority-row-${priority.name}`}
                                >
                                    <td className="px-5 py-3.5 font-medium">
                                        {priority.name}
                                    </td>
                                    <td className="px-3 py-3.5 font-mono text-xs">
                                        {priority.code}
                                    </td>
                                    <td className="px-3 py-3.5 tabular-nums">
                                        {priority.weight}
                                    </td>
                                    <td className="px-3 py-3.5">
                                        {priority.color ? (
                                            <span
                                                className="inline-block size-4 rounded-full border"
                                                style={{
                                                    backgroundColor:
                                                        priority.color,
                                                }}
                                                title={priority.color}
                                            />
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="px-3 py-3.5">
                                        <Badge
                                            variant={
                                                priority.is_active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {priority.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </td>
                                    <td className="px-5 py-3.5">
                                        {permissions.canManageSettings && (
                                            <div className="flex flex-wrap justify-end gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    data-test={`edit-queue-priority-${priority.name}`}
                                                    onClick={() =>
                                                        openEdit(priority)
                                                    }
                                                >
                                                    Edit
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    data-test={`delete-queue-priority-${priority.name}`}
                                                    onClick={() =>
                                                        setDeleting(priority)
                                                    }
                                                >
                                                    Delete
                                                </Button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </QueuePageShell>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit priority' : 'Add priority'}
                        </DialogTitle>
                        <DialogDescription>
                            Weight is copied onto tickets at issue time. Higher
                            numbers are served first on Priority services.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="queue-priority-name">Name</Label>
                            <Input
                                id="queue-priority-name"
                                data-test="queue-priority-name"
                                value={form.name}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        name: event.target.value,
                                    })
                                }
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="queue-priority-code">
                                    Code
                                </Label>
                                <Input
                                    id="queue-priority-code"
                                    value={form.code}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            code: event.target.value,
                                        })
                                    }
                                    placeholder="auto from name"
                                />
                                <InputError message={errors.code} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="queue-priority-weight">
                                    Weight
                                </Label>
                                <Input
                                    id="queue-priority-weight"
                                    data-test="queue-priority-weight"
                                    type="number"
                                    min={0}
                                    value={form.weight}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            weight: event.target.value,
                                        })
                                    }
                                />
                                <InputError message={errors.weight} />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="queue-priority-color">
                                    Color
                                </Label>
                                <Input
                                    id="queue-priority-color"
                                    type="color"
                                    value={form.color}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            color: event.target.value,
                                        })
                                    }
                                />
                            </div>
                            <label className="flex items-center gap-2 self-end pb-2 text-sm">
                                <Checkbox
                                    checked={form.is_active}
                                    onCheckedChange={(checked) =>
                                        setForm({
                                            ...form,
                                            is_active: checked === true,
                                        })
                                    }
                                />
                                Active
                            </label>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            data-test="save-queue-priority"
                            onClick={savePriority}
                        >
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                title="Delete priority"
                description="Issued tickets keep the weight they were given. At least one priority must remain."
                confirmLabel="Delete"
                onOpenChange={(next) => {
                    if (!next) {
                        setDeleting(null);
                    }
                }}
                onConfirm={() => {
                    if (!deleting || !slug) {
                        return;
                    }

                    router.delete(destroy.url([slug, deleting.id]), {
                        ...stayOnPage(),
                        onFinish: () => setDeleting(null),
                    });
                }}
            />
        </>
    );
}

QueueSettings.layout = queueBreadcrumbs('Settings', '/settings');
