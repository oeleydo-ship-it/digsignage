import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { subscribeQueueDashboardUpdates } from '@/lib/queue-echo';
import type { ReverbConfig } from '@/lib/player-echo';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    QueueCounterServiceOption,
    QueueDeskCounter,
    QueueDeskTicket,
    QueueDeskTransferCounter,
    QueuePermissions,
} from '@/types';

type Props = {
    counter: QueueDeskCounter;
    current: QueueDeskTicket | null;
    waiting_count: number;
    held_count: number;
    average_wait_seconds: number | null;
    transferServices: QueueCounterServiceOption[];
    transferCounters: QueueDeskTransferCounter[];
    permissions: QueuePermissions;
    reverb: ReverbConfig;
};

function stayOnPage() {
    return {
        preserveScroll: true,
        headers: {
            'X-Stay-On-Page': window.location.href,
        },
    };
}

function formatClock(totalSeconds: number): string {
    const safe = Math.max(0, totalSeconds);
    const minutes = Math.floor(safe / 60);
    const seconds = safe % 60;

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

function elapsedSeconds(iso: string | null): number {
    if (!iso) {
        return 0;
    }

    return Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));
}

export default function QueueDesk({
    counter,
    current,
    waiting_count,
    held_count,
    average_wait_seconds,
    transferServices,
    transferCounters = [],
    permissions,
    reverb,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [servingSeconds, setServingSeconds] = useState(() =>
        elapsedSeconds(current?.service_started_at ?? current?.called_at ?? null),
    );
    const [transferOpen, setTransferOpen] = useState(false);
    const [transferServiceId, setTransferServiceId] = useState('');
    const [transferCounterId, setTransferCounterId] = useState('none');
    const [transferReason, setTransferReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [callingNext, setCallingNext] = useState(false);
    const [liveWaitingCount, setLiveWaitingCount] = useState(waiting_count);
    const [liveHeldCount, setLiveHeldCount] = useState(held_count);
    const actionInFlight = useRef(false);
    const serviceKey = counter.services.map((service) => service.id).join(',');

    useEffect(() => setLiveWaitingCount(waiting_count), [waiting_count]);
    useEffect(() => setLiveHeldCount(held_count), [held_count]);

    useEffect(() => {
        const serviceIds = serviceKey === '' ? [] : serviceKey.split(',').map(Number);
        let stopped = false;
        let unsubscribe: () => void = () => undefined;
        let refreshTimer: number | undefined;
        let statusRequest: AbortController | null = null;
        const fetchStatus = () => {
            if (stopped || actionInFlight.current || statusRequest) return;
            const request = new AbortController();
            statusRequest = request;
            void fetch(`/${slug}/queue/counters/${counter.id}/desk/status`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
                signal: request.signal,
            }).then(async (response) => {
                if (!response.ok) return;
                const status = await response.json() as { waiting_count: number; held_count: number };
                if (!stopped && !actionInFlight.current) {
                    setLiveWaitingCount(status.waiting_count);
                    setLiveHeldCount(status.held_count);
                }
            }).catch(() => undefined).finally(() => {
                if (statusRequest === request) statusRequest = null;
            });
        };
        const refresh = () => {
            window.clearTimeout(refreshTimer);
            refreshTimer = window.setTimeout(() => {
                if (actionInFlight.current) return;
                statusRequest?.abort();
                statusRequest = null;
                fetchStatus();
                router.reload({
                    only: ['counter', 'current', 'waiting_count', 'held_count', 'average_wait_seconds'],
                });
            }, 150);
        };

        void subscribeQueueDashboardUpdates(serviceIds, reverb, refresh).then((cleanup) => {
            if (stopped) cleanup();
            else unsubscribe = cleanup;
        });
        fetchStatus();
        const poll = window.setInterval(fetchStatus, 2_000);

        return () => {
            stopped = true;
            window.clearInterval(poll);
            window.clearTimeout(refreshTimer);
            statusRequest?.abort();
            unsubscribe();
        };
    }, [slug, counter.id, serviceKey, reverb.enabled, reverb.key, reverb.host, reverb.port, reverb.scheme]);

    useEffect(() => {
        setServingSeconds(
            elapsedSeconds(current?.service_started_at ?? current?.called_at ?? null),
        );

        if (!current) {
            return;
        }

        const timer = window.setInterval(() => {
            setServingSeconds(
                elapsedSeconds(
                    current.service_started_at ?? current.called_at ?? null,
                ),
            );
        }, 1000);

        return () => window.clearInterval(timer);
    }, [current]);

    const action = (
        path: string,
        data: Record<string, number | string | null> = {},
    ) => {
        setErrors({});
        actionInFlight.current = true;
        router.post(`/${slug}/queue/counters/${counter.id}/${path}`, data, {
            ...stayOnPage(),
            onStart: () => {
                if (path === 'call-next') setCallingNext(true);
            },
            onError: (next) => setErrors(next),
            onFinish: () => {
                actionInFlight.current = false;
                if (path === 'call-next') setCallingNext(false);
            },
        });
    };

    const destinationCounters = transferCounters.filter((option) => {
        if (option.id === counter.id) {
            return false;
        }

        if (transferServiceId === '') {
            return true;
        }

        return option.service_ids.includes(Number(transferServiceId));
    });

    const serviceNames = counter.services.map((service) => service.name).join(', ');
    const averageMinutes =
        average_wait_seconds === null
            ? '—'
            : `${Math.max(1, Math.round(average_wait_seconds / 60))} min`;

    return (
        <>
            <Head title={`${counter.name} desk`} />
            <div className="bg-background min-h-screen px-4 py-8 sm:px-8">
                <div className="mx-auto flex w-full max-w-xl flex-col gap-6">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.18em] uppercase">
                                Counter desk
                            </p>
                            <h1
                                className="mt-1 text-3xl font-bold tracking-tight"
                                data-test="queue-desk-name"
                            >
                                {counter.name}
                            </h1>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {serviceNames || 'No services assigned'}
                            </p>
                        </div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={`/${slug}/queue/counters`}>Back</Link>
                        </Button>
                    </div>

                    <section className="rounded-2xl border px-6 py-8 text-center">
                        <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                            Currently serving
                        </p>
                        <p
                            className="mt-3 font-mono text-6xl font-semibold tracking-tight"
                            data-test="queue-desk-ticket"
                        >
                            {current?.number ?? '—'}
                        </p>
                        <p className="text-muted-foreground mt-4 text-sm">
                            Serving time
                        </p>
                        <p className="font-mono text-2xl tabular-nums">
                            {current ? formatClock(servingSeconds) : '00:00'}
                        </p>
                    </section>

                    <div className="grid grid-cols-2 gap-3">
                        <Button
                            className="h-14 text-base"
                            onClick={() => action('call-next')}
                            data-test="queue-desk-call-next"
                            disabled={!permissions.canCallQueue || (Boolean(current) && !permissions.canCompleteQueue) || callingNext}
                        >
                            {callingNext ? 'Calling…' : current ? 'Complete & call next' : 'Call next'}
                        </Button>
                        <Button
                            variant="secondary"
                            className="h-14 text-base"
                            onClick={() => action('recall')}
                            data-test="queue-desk-recall"
                            disabled={!permissions.canCallQueue || !current}
                        >
                            Recall
                        </Button>
                        <Button
                            variant="secondary"
                            className="h-14 text-base"
                            onClick={() => action('hold')}
                            disabled={!permissions.canCallQueue || !current}
                        >
                            Hold
                        </Button>
                        <Button
                            variant="secondary"
                            className="h-14 text-base"
                            onClick={() => action('resume')}
                            disabled={
                                !permissions.canCallQueue ||
                                Boolean(current) ||
                                liveHeldCount === 0
                            }
                            data-test="queue-desk-resume"
                        >
                            Resume
                        </Button>
                        <Button
                            variant="secondary"
                            className="h-14 text-base"
                            onClick={() => setTransferOpen(true)}
                            disabled={!permissions.canTransferQueue || !current}
                            data-test="queue-desk-transfer"
                        >
                            Transfer
                        </Button>
                        <Button
                            variant="secondary"
                            className="h-14 text-base"
                            onClick={() => action('complete')}
                            disabled={!permissions.canCompleteQueue || !current}
                            data-test="queue-desk-complete"
                        >
                            Complete
                        </Button>
                        <Button
                            variant="outline"
                            className="h-14 text-base"
                            onClick={() => action('no-show')}
                            disabled={!permissions.canCompleteQueue || !current}
                        >
                            No show
                        </Button>
                    </div>

                    {(errors.ticket || errors.status || errors.service_ids) && (
                        <InputError
                            message={
                                errors.ticket ?? errors.status ?? errors.service_ids
                            }
                        />
                    )}

                    <div className="text-muted-foreground flex justify-between gap-3 rounded-xl border px-5 py-4 text-sm">
                        <span>
                            Waiting:{' '}
                            <strong className="text-foreground">{liveWaitingCount}</strong>
                        </span>
                        <span>
                            Held:{' '}
                            <strong className="text-foreground">{liveHeldCount}</strong>
                        </span>
                        <span>
                            Average wait:{' '}
                            <strong className="text-foreground">{averageMinutes}</strong>
                        </span>
                    </div>
                </div>
            </div>

            <Dialog
                open={transferOpen}
                onOpenChange={(next) => {
                    setTransferOpen(next);

                    if (!next) {
                        setTransferServiceId('');
                        setTransferCounterId('none');
                        setTransferReason('');
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Transfer ticket</DialogTitle>
                        <DialogDescription>
                            Send the current customer to another service or counter.
                            The ticket number stays the same and they wait in the
                            destination line.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label>Service</Label>
                            <Select
                                value={transferServiceId}
                                onValueChange={(value) => {
                                    setTransferServiceId(value);
                                    setTransferCounterId('none');
                                }}
                            >
                                <SelectTrigger data-test="queue-desk-transfer-service">
                                    <SelectValue placeholder="Choose a service" />
                                </SelectTrigger>
                                <SelectContent>
                                    {transferServices.map((service) => (
                                        <SelectItem
                                            key={service.id}
                                            value={String(service.id)}
                                        >
                                            {service.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.queue_service_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Counter (optional)</Label>
                            <Select
                                value={transferCounterId}
                                onValueChange={setTransferCounterId}
                            >
                                <SelectTrigger data-test="queue-desk-transfer-counter">
                                    <SelectValue placeholder="Any counter" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Any counter</SelectItem>
                                    {destinationCounters.map((option) => (
                                        <SelectItem
                                            key={option.id}
                                            value={String(option.id)}
                                        >
                                            {option.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.counter_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="queue-desk-transfer-reason">
                                Reason
                            </Label>
                            <textarea
                                id="queue-desk-transfer-reason"
                                data-test="queue-desk-transfer-reason"
                                value={transferReason}
                                onChange={(event) =>
                                    setTransferReason(event.target.value)
                                }
                                rows={3}
                                className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                placeholder="Why is this customer moving?"
                            />
                            <InputError message={errors.reason} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setTransferOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            data-test="queue-desk-transfer-confirm"
                            disabled={transferServiceId === ''}
                            onClick={() => {
                                action('transfer', {
                                    queue_service_id: Number(transferServiceId),
                                    counter_id:
                                        transferCounterId === 'none'
                                            ? null
                                            : Number(transferCounterId),
                                    reason: transferReason.trim() || null,
                                });
                                setTransferOpen(false);
                            }}
                        >
                            Transfer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
