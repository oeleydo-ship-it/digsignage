import { Head, router } from '@inertiajs/react';
import { CalendarClock, CheckCircle2, MapPin } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    appointment: {
        customer_name: string;
        service_name: string;
        location_name: string | null;
        scheduled_at: string;
        reference: string;
        status: string;
        ticket_number: string | null;
        team_name: string;
    };
    source: 'qr' | 'reference';
};

export default function AppointmentCheckIn({ appointment, source }: Props) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const checkedIn = appointment.status === 'checked_in';

    const checkIn = () => {
        setBusy(true);
        setError('');
        router.post(window.location.pathname, { source }, {
            preserveScroll: true,
            onError: (errors) => setError(errors.reference ?? 'Check-in is unavailable.'),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <main className="flex min-h-screen items-center justify-center bg-slate-100 p-5 text-slate-950">
            <Head title="Appointment check-in" />
            <section className="w-full max-w-lg rounded-3xl bg-white p-8 shadow-xl">
                <p className="text-sm font-semibold tracking-[0.2em] text-blue-600 uppercase">{appointment.team_name}</p>
                <h1 className="mt-3 text-3xl font-bold">Appointment check-in</h1>
                <p className="mt-2 text-slate-600">Welcome, {appointment.customer_name}.</p>

                <div className="mt-7 space-y-4 rounded-2xl bg-slate-50 p-5">
                    <div><span className="text-sm text-slate-500">Service</span><p className="font-semibold">{appointment.service_name}</p></div>
                    <div className="flex gap-3"><CalendarClock className="mt-0.5 size-5 text-blue-600" /><div><span className="text-sm text-slate-500">Appointment</span><p className="font-semibold">{new Date(appointment.scheduled_at).toLocaleString()}</p></div></div>
                    {appointment.location_name ? <div className="flex gap-3"><MapPin className="mt-0.5 size-5 text-blue-600" /><div><span className="text-sm text-slate-500">Location</span><p className="font-semibold">{appointment.location_name}</p></div></div> : null}
                    <div><span className="text-sm text-slate-500">Reference</span><p className="font-mono font-semibold">{appointment.reference}</p></div>
                </div>

                {checkedIn ? (
                    <div className="mt-7 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-center">
                        <CheckCircle2 className="mx-auto size-9 text-emerald-600" />
                        <p className="mt-2 font-semibold">You are checked in</p>
                        <p className="mt-2 text-4xl font-bold text-emerald-700">{appointment.ticket_number}</p>
                    </div>
                ) : (
                    <Button className="mt-7 h-14 w-full text-lg" disabled={busy} onClick={checkIn} data-test="appointment-public-check-in">
                        {busy ? 'Checking in…' : 'Check in now'}
                    </Button>
                )}
                {error ? <p className="mt-4 text-center text-sm text-red-600" role="alert">{error}</p> : null}
            </section>
        </main>
    );
}
