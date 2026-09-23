import { Head, router } from '@inertiajs/react';
import { Clock3, MapPin, Smartphone, Users } from 'lucide-react';
import { useRef, useState } from 'react';
import { createQueueRequestKey } from '@/lib/queue-idempotency';

type Service = {
    id: number;
    name: string;
    description: string | null;
    location_name: string | null;
    display_color: string;
    waiting_count: number;
    estimated_wait_minutes: number;
};

type Props = {
    team: { name: string; slug: string };
    location: { id: number; name: string } | null;
    services: Service[];
};

export default function VirtualQueueJoin({ team, location, services }: Props) {
    const [selected, setSelected] = useState<Service | null>(
        services.length === 1 ? services[0] : null,
    );
    const [customerName, setCustomerName] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const requestKey = useRef(createQueueRequestKey());

    const join = () => {
        if (!selected || busy) return;

        setBusy(true);
        setError('');
        router.post(
            '/join/' + team.slug + '/tickets',
            {
                queue_service_id: selected.id,
                location_id: location?.id ?? null,
                customer_name: customerName || null,
                idempotency_key: requestKey.current,
            },
            {
                onError: (errors) =>
                    setError(
                        errors.queue_service_id ??
                            'We could not join this queue. Please try again.',
                    ),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <>
            <Head title={'Join queue - ' + team.name} />
            <main className="min-h-screen bg-slate-50 px-4 py-8 text-slate-950 sm:py-12">
                <div className="mx-auto max-w-lg">
                    <div className="mb-8 text-center">
                        <span className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-lg shadow-blue-600/20">
                            <Smartphone className="size-6" />
                        </span>
                        <p className="mt-5 text-sm font-semibold tracking-[0.18em] text-blue-600 uppercase">
                            Virtual queue
                        </p>
                        <h1 className="mt-2 text-3xl font-bold tracking-tight">
                            {team.name}
                        </h1>
                        {location ? (
                            <p className="mt-2 flex items-center justify-center gap-1.5 text-sm text-slate-500">
                                <MapPin className="size-4" />
                                {location.name}
                            </p>
                        ) : null}
                    </div>

                    <section className="rounded-3xl border bg-white p-5 shadow-sm sm:p-7">
                        <h2 className="text-xl font-semibold">
                            Select a service
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Join from your phone and keep this page open for
                            live updates.
                        </p>

                        <div className="mt-6 grid gap-3">
                            {services.length === 0 ? (
                                <p className="rounded-2xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                    No services are accepting virtual tickets.
                                </p>
                            ) : (
                                services.map((service) => (
                                    <button
                                        key={service.id}
                                        type="button"
                                        onClick={() => setSelected(service)}
                                        className={
                                            selected?.id === service.id
                                                ? 'rounded-2xl border-2 border-blue-600 bg-blue-50 p-4 text-left'
                                                : 'rounded-2xl border-2 border-slate-100 p-4 text-left hover:border-blue-200'
                                        }
                                    >
                                        <span className="flex items-center gap-3">
                                            <span
                                                className="size-3 rounded-full"
                                                style={{
                                                    background:
                                                        service.display_color,
                                                }}
                                            />
                                            <span className="font-semibold">
                                                {service.name}
                                            </span>
                                        </span>
                                        {service.description ? (
                                            <span className="mt-2 block text-sm text-slate-500">
                                                {service.description}
                                            </span>
                                        ) : null}
                                        <span className="mt-3 flex flex-wrap gap-4 text-xs text-slate-500">
                                            <span className="flex items-center gap-1">
                                                <Users className="size-3.5" />
                                                {service.waiting_count} waiting
                                            </span>
                                            <span className="flex items-center gap-1">
                                                <Clock3 className="size-3.5" />
                                                About{' '}
                                                {
                                                    service.estimated_wait_minutes
                                                }{' '}
                                                min
                                            </span>
                                        </span>
                                    </button>
                                ))
                            )}
                        </div>

                        {selected ? (
                            <div className="mt-6">
                                <label
                                    htmlFor="virtual-customer-name"
                                    className="text-sm font-medium"
                                >
                                    Your name{' '}
                                    <span className="text-slate-400">
                                        (optional)
                                    </span>
                                </label>
                                <input
                                    id="virtual-customer-name"
                                    value={customerName}
                                    onChange={(event) =>
                                        setCustomerName(event.target.value)
                                    }
                                    className="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                                    autoComplete="name"
                                />
                            </div>
                        ) : null}

                        {error ? (
                            <p
                                className="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700"
                                role="alert"
                            >
                                {error}
                            </p>
                        ) : null}

                        <button
                            type="button"
                            disabled={!selected || busy}
                            onClick={join}
                            className="mt-6 min-h-14 w-full rounded-2xl bg-blue-600 px-5 text-lg font-semibold text-white shadow-lg shadow-blue-600/20 disabled:cursor-not-allowed disabled:opacity-50"
                            data-test="join-virtual-queue"
                        >
                            {busy ? 'Joining…' : 'Join queue'}
                        </button>
                    </section>
                </div>
            </main>
        </>
    );
}
