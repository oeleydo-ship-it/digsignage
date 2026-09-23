import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Clock3, MapPin, Radio, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReverbConfig } from '@/lib/player-echo';
import { subscribeQueueUpdates } from '@/lib/queue-echo';

type Ticket = {
    token: string;
    number: string;
    status: string;
    status_label: string;
    service_id: number;
    service_name: string;
    location_name: string | null;
    position: number | null;
    people_ahead: number;
    estimated_wait_minutes: number;
    counter_name: string | null;
    now_serving: { number: string; counter_name: string | null } | null;
    can_cancel: boolean;
    team_name: string;
};

type Props = { ticket: Ticket; reverb: ReverbConfig };

export default function VirtualQueueTicket({ ticket, reverb }: Props) {
    const [cancelling, setCancelling] = useState(false);

    useEffect(() => {
        const refresh = () =>
            router.reload({
                only: ['ticket'],
            });
        let unsubscribe: () => void = () => undefined;

        void subscribeQueueUpdates(ticket.service_id, reverb, refresh).then(
            (cleanup) => {
                unsubscribe = cleanup;
            },
        );

        const poll = window.setInterval(refresh, 10_000);

        return () => {
            window.clearInterval(poll);
            unsubscribe();
        };
    }, [ticket.service_id, reverb]);

    const cancel = () => {
        if (!window.confirm('Leave this queue and cancel your ticket?')) return;

        setCancelling(true);
        router.post(
            '/queue-ticket/' + ticket.token + '/cancel',
            {},
            { onFinish: () => setCancelling(false) },
        );
    };

    const active = ['waiting', 'called', 'serving', 'on_hold'].includes(
        ticket.status,
    );

    return (
        <>
            <Head title={'Ticket ' + ticket.number} />
            <main className="min-h-screen bg-slate-950 px-4 py-8 text-white sm:py-12">
                <div className="mx-auto max-w-lg">
                    <div className="text-center">
                        <p className="text-sm font-semibold tracking-[0.18em] text-blue-300 uppercase">
                            {ticket.team_name}
                        </p>
                        <p className="mt-6 text-sm tracking-[0.2em] text-slate-400 uppercase">
                            Your ticket
                        </p>
                        <h1
                            className="mt-2 font-mono text-7xl font-bold tracking-tight sm:text-8xl"
                            data-test="virtual-ticket-number"
                        >
                            {ticket.number}
                        </h1>
                        <span className="mt-5 inline-flex rounded-full bg-blue-500/15 px-4 py-1.5 text-sm font-semibold text-blue-200">
                            {ticket.status_label}
                        </span>
                    </div>

                    <section className="mt-8 overflow-hidden rounded-3xl border border-white/10 bg-white/5">
                        {ticket.status === 'waiting' ? (
                            <div className="grid grid-cols-2 divide-x divide-white/10 border-b border-white/10">
                                <Metric
                                    icon={Users}
                                    label="Current position"
                                    value={String(ticket.position ?? '—')}
                                />
                                <Metric
                                    icon={Clock3}
                                    label="Estimated wait"
                                    value={
                                        ticket.people_ahead === 0
                                            ? 'You are next'
                                            : ticket.estimated_wait_minutes +
                                              ' min'
                                    }
                                />
                            </div>
                        ) : null}

                        {ticket.status === 'serving' ||
                        ticket.status === 'called' ? (
                            <div className="border-b border-white/10 p-6 text-center">
                                <p className="text-sm text-slate-400">
                                    Please proceed to
                                </p>
                                <p className="mt-2 text-3xl font-bold text-emerald-300">
                                    {ticket.counter_name ?? 'the service desk'}
                                </p>
                            </div>
                        ) : null}

                        {!active ? (
                            <div className="border-b border-white/10 p-8 text-center">
                                <CheckCircle2 className="mx-auto size-10 text-emerald-400" />
                                <p className="mt-3 text-lg font-semibold">
                                    {ticket.status === 'cancelled'
                                        ? 'You have left the queue'
                                        : 'This ticket is finished'}
                                </p>
                            </div>
                        ) : null}

                        <div className="space-y-3 p-5 text-sm">
                            <p className="flex items-center justify-between gap-3">
                                <span className="text-slate-400">Service</span>
                                <span className="font-medium">
                                    {ticket.service_name}
                                </span>
                            </p>
                            {ticket.location_name ? (
                                <p className="flex items-center justify-between gap-3">
                                    <span className="flex items-center gap-1 text-slate-400">
                                        <MapPin className="size-3.5" />
                                        Location
                                    </span>
                                    <span className="font-medium">
                                        {ticket.location_name}
                                    </span>
                                </p>
                            ) : null}
                        </div>
                    </section>

                    <section className="mt-4 rounded-3xl border border-white/10 bg-white/5 p-5">
                        <p className="flex items-center gap-2 text-sm font-semibold text-slate-300">
                            <Radio className="size-4 text-blue-300" />
                            Now serving
                        </p>
                        <p className="mt-3 text-3xl font-bold">
                            {ticket.now_serving?.number ?? '—'}
                        </p>
                        {ticket.now_serving?.counter_name ? (
                            <p className="mt-1 text-sm text-slate-400">
                                {ticket.now_serving.counter_name}
                            </p>
                        ) : null}
                    </section>

                    <p className="mt-5 text-center text-xs text-slate-500">
                        Updates arrive automatically. You can keep this page
                        open.
                    </p>

                    {ticket.can_cancel ? (
                        <button
                            type="button"
                            onClick={cancel}
                            disabled={cancelling}
                            className="mt-5 min-h-12 w-full rounded-2xl border border-red-400/30 text-sm font-semibold text-red-300 hover:bg-red-500/10 disabled:opacity-50"
                            data-test="cancel-virtual-ticket"
                        >
                            {cancelling ? 'Leaving…' : 'Leave queue'}
                        </button>
                    ) : null}
                </div>
            </main>
        </>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof Users;
    label: string;
    value: string;
}) {
    return (
        <div className="p-5 text-center">
            <Icon className="mx-auto size-4 text-blue-300" />
            <p className="mt-2 text-xs text-slate-400">{label}</p>
            <p className="mt-1 text-xl font-semibold">{value}</p>
        </div>
    );
}
