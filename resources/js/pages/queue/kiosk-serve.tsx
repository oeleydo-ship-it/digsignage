import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { createQueueRequestKey } from '@/lib/queue-idempotency';
import type {
    QueueKioskIssuedTicket,
    QueueKioskServeKiosk,
    QueueKioskServeService,
} from '@/types';

type Props = {
    kiosk: QueueKioskServeKiosk;
    services: QueueKioskServeService[];
    issuedTicket: QueueKioskIssuedTicket | null;
};

function stayOnPage() {
    return {
        preserveScroll: true,
        headers: {
            'X-Stay-On-Page': window.location.href,
        },
    };
}

export default function QueueKioskServe({
    kiosk,
    services,
    issuedTicket,
}: Props) {
    const colors = kiosk.branding.colors;
    const title = kiosk.branding.title || 'WELCOME';
    const [busy, setBusy] = useState(false);
    const [issueError, setIssueError] = useState('');
    const [appointmentReference, setAppointmentReference] = useState('');
    const [isFullscreen, setIsFullscreen] = useState(false);
    const [qrReady, setQrReady] = useState(!kiosk.branding.print_qr_code);
    const printedFor = useRef<number | null>(null);
    const issueRequestKey = useRef(createQueueRequestKey());

    useEffect(() => {
        const updateFullscreen = () =>
            setIsFullscreen(document.fullscreenElement !== null);

        document.addEventListener('fullscreenchange', updateFullscreen);

        return () =>
            document.removeEventListener('fullscreenchange', updateFullscreen);
    }, []);

    useEffect(() => {
        if (!issuedTicket || !kiosk.printer_enabled || !qrReady) {
            return;
        }

        if (printedFor.current === issuedTicket.id) {
            return;
        }

        printedFor.current = issuedTicket.id;
        const timer = window.setTimeout(() => window.print(), 400);

        return () => window.clearTimeout(timer);
    }, [issuedTicket, kiosk.printer_enabled, qrReady]);

    useEffect(() => {
        setQrReady(!kiosk.branding.print_qr_code);
    }, [issuedTicket?.id, kiosk.branding.print_qr_code]);

    useEffect(() => {
        if (!issuedTicket) {
            return;
        }

        const timer = window.setTimeout(() => {
            router.get(window.location.pathname, {}, { preserveScroll: true });
        }, 30_000);

        return () => window.clearTimeout(timer);
    }, [issuedTicket]);

    const issue = (serviceId: number) => {
        if (busy) {
            return;
        }

        setBusy(true);
        setIssueError('');
        router.post(
            window.location.pathname + '/tickets',
            {
                queue_service_id: serviceId,
                idempotency_key: issueRequestKey.current,
            },
            {
                ...stayOnPage(),
                onError: () =>
                    setIssueError(
                        'This service could not issue a ticket. Please ask a staff member for help.',
                    ),
                onSuccess: () => {
                    issueRequestKey.current = createQueueRequestKey();
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    const reset = () => {
        router.get(window.location.pathname, {}, { preserveScroll: true });
    };

    const checkInAppointment = () => {
        if (busy || appointmentReference.trim() === '') return;
        setBusy(true);
        setIssueError('');
        router.post(
            window.location.pathname + '/appointments/check-in',
            { reference: appointmentReference.trim().toUpperCase() },
            {
                ...stayOnPage(),
                onError: (errors) => setIssueError(errors.reference ?? 'The appointment could not be checked in.'),
                onFinish: () => setBusy(false),
            },
        );
    };

    const toggleFullscreen = async () => {
        try {
            if (document.fullscreenElement) {
                await document.exitFullscreen();
            } else {
                await document.documentElement.requestFullscreen();
            }
        } catch {
            setIssueError(
                'Full-screen mode is unavailable in this browser. Use the browser menu instead.',
            );
        }
    };

    return (
        <>
            <Head title={`${kiosk.name} kiosk`} />
            <style>{`
                @page { size: 80mm auto; margin: 6mm; }
                @media print {
                    .kiosk-screen { display: none !important; }
                    .kiosk-print-slip { display: block !important; }
                }
                @media screen {
                    .kiosk-print-slip { display: none; }
                }
            `}</style>
            <div
                className="kiosk-screen flex min-h-screen flex-col px-6 py-10 sm:px-12"
                style={{ background: colors.background, color: colors.text }}
            >
                <button
                    type="button"
                    onClick={toggleFullscreen}
                    className="fixed top-4 right-4 rounded-lg border border-current/20 px-3 py-2 text-xs font-semibold opacity-70 hover:opacity-100"
                    data-test="kiosk-fullscreen"
                >
                    {isFullscreen ? 'Exit full screen' : 'Full screen'}
                </button>
                <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col">
                    {kiosk.branding.logo_url ? (
                        <img
                            src={kiosk.branding.logo_url}
                            alt=""
                            className="mb-8 h-16 w-auto object-contain"
                        />
                    ) : null}
                    <p className="text-sm font-semibold tracking-[0.22em] uppercase opacity-80">
                        {kiosk.team_name}
                    </p>
                    {issuedTicket ? (
                        <TicketScreen
                            kiosk={kiosk}
                            ticket={issuedTicket}
                            onDone={reset}
                            buttonColor={colors.primary}
                            buttonText={colors.button_text}
                        />
                    ) : (
                        <WelcomeScreen
                            title={title}
                            services={services}
                            busy={busy}
                            error={issueError}
                            onSelect={issue}
                            appointmentReference={appointmentReference}
                            onAppointmentReference={setAppointmentReference}
                            onAppointmentCheckIn={checkInAppointment}
                            buttonColor={colors.primary}
                            buttonText={colors.button_text}
                        />
                    )}
                    {kiosk.branding.footer ? (
                        <p className="mt-auto pt-10 text-center text-sm opacity-70">
                            {kiosk.branding.footer}
                        </p>
                    ) : null}
                </div>
            </div>
            {issuedTicket ? (
                <PrintSlip
                    kiosk={kiosk}
                    ticket={issuedTicket}
                    onQrReady={() => setQrReady(true)}
                />
            ) : null}
        </>
    );
}

function WelcomeScreen({
    title,
    services,
    busy,
    error,
    onSelect,
    appointmentReference,
    onAppointmentReference,
    onAppointmentCheckIn,
    buttonColor,
    buttonText,
}: {
    title: string;
    services: QueueKioskServeService[];
    busy: boolean;
    error: string;
    onSelect: (id: number) => void;
    appointmentReference: string;
    onAppointmentReference: (value: string) => void;
    onAppointmentCheckIn: () => void;
    buttonColor: string;
    buttonText: string;
}) {
    return (
        <div className="mt-8 flex flex-1 flex-col">
            <h1
                className="text-5xl font-bold tracking-tight sm:text-6xl"
                data-test="kiosk-welcome-title"
            >
                {title}
            </h1>
            <p className="mt-4 text-2xl font-medium opacity-80">
                Please select a service
            </p>
            {error ? (
                <p
                    className="mt-5 rounded-xl border border-red-300/40 bg-red-500/15 px-4 py-3 text-sm"
                    role="alert"
                >
                    {error}
                </p>
            ) : null}
            <div className="mt-8 rounded-2xl border border-current/20 p-5">
                <p className="text-lg font-semibold">Have an appointment?</p>
                <div className="mt-3 flex flex-col gap-3 sm:flex-row">
                    <input
                        value={appointmentReference}
                        onChange={(event) => onAppointmentReference(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') onAppointmentCheckIn();
                        }}
                        placeholder="Appointment reference"
                        className="min-h-14 flex-1 rounded-xl border border-current/25 bg-white/10 px-4 text-lg uppercase outline-none"
                        data-test="kiosk-appointment-reference"
                    />
                    <button
                        type="button"
                        disabled={busy || appointmentReference.trim() === ''}
                        onClick={onAppointmentCheckIn}
                        className="min-h-14 rounded-xl px-6 font-semibold disabled:opacity-50"
                        style={{ background: buttonColor, color: buttonText }}
                    >
                        Check in
                    </button>
                </div>
            </div>
            <div className="mt-10 grid gap-4">
                {services.length === 0 ? (
                    <p className="rounded-2xl border border-white/15 px-6 py-10 text-center text-lg opacity-80">
                        No services are available at this kiosk.
                    </p>
                ) : (
                    services.map((service) => (
                        <button
                            key={service.id}
                            type="button"
                            disabled={busy}
                            onClick={() => onSelect(service.id)}
                            data-test={`kiosk-service-${service.id}`}
                            data-service-name={service.name}
                            className="min-h-20 rounded-2xl px-6 py-5 text-left text-2xl font-semibold shadow-lg transition hover:brightness-110 disabled:opacity-60"
                            style={{
                                background:
                                    service.display_color || buttonColor,
                                color: buttonText,
                            }}
                        >
                            <span>{service.name}</span>
                            <span className="mt-1 block text-sm font-normal opacity-80">
                                {service.waiting_count} waiting
                            </span>
                        </button>
                    ))
                )}
            </div>
        </div>
    );
}

function TicketScreen({
    kiosk,
    ticket,
    onDone,
    buttonColor,
    buttonText,
}: {
    kiosk: QueueKioskServeKiosk;
    ticket: QueueKioskIssuedTicket;
    onDone: () => void;
    buttonColor: string;
    buttonText: string;
}) {
    return (
        <div className="mt-8 flex flex-1 flex-col items-center text-center">
            <p className="text-xl tracking-[0.2em] uppercase opacity-80">
                Your ticket
            </p>
            <p
                className="mt-4 font-mono text-8xl font-semibold tracking-tight"
                data-test="kiosk-ticket-number"
            >
                {ticket.number}
            </p>
            <p className="mt-8 text-2xl" data-test="kiosk-people-ahead">
                People ahead: {ticket.people_ahead}
            </p>
            <p className="mt-3 text-2xl" data-test="kiosk-estimated-wait">
                Estimated waiting time:{' '}
                {ticket.people_ahead === 0
                    ? 'You are next'
                    : `${ticket.estimated_wait_minutes} minutes`}
            </p>
            <div className="mt-12 flex w-full max-w-md flex-col gap-4">
                {kiosk.printer_enabled ? (
                    <button
                        type="button"
                        onClick={() => window.print()}
                        data-test="kiosk-print"
                        className="min-h-16 rounded-2xl text-xl font-semibold"
                        style={{ background: buttonColor, color: buttonText }}
                    >
                        Print ticket
                    </button>
                ) : null}
                <button
                    type="button"
                    onClick={onDone}
                    data-test="kiosk-done"
                    className="min-h-16 rounded-2xl border border-white/20 text-xl font-semibold"
                >
                    Done
                </button>
            </div>
        </div>
    );
}

function PrintSlip({
    kiosk,
    ticket,
    onQrReady,
}: {
    kiosk: QueueKioskServeKiosk;
    ticket: QueueKioskIssuedTicket;
    onQrReady: () => void;
}) {
    return (
        <div className="kiosk-print-slip font-sans text-black">
            {kiosk.branding.logo_url ? (
                <img
                    src={kiosk.branding.logo_url}
                    alt=""
                    className="mx-auto mb-3 h-10 w-auto"
                />
            ) : null}
            <p className="text-center text-sm font-semibold">
                {kiosk.team_name}
            </p>
            {ticket.location_name ? (
                <p className="text-center text-xs">{ticket.location_name}</p>
            ) : null}
            <p className="mt-4 text-center text-xs tracking-widest uppercase">
                Your ticket
            </p>
            <p className="text-center font-mono text-5xl font-bold">
                {ticket.number}
            </p>
            {ticket.service_name ? (
                <p className="mt-2 text-center text-sm">
                    {ticket.service_name}
                </p>
            ) : null}
            {ticket.issued_at ? (
                <p className="mt-1 text-center text-xs">{ticket.issued_at}</p>
            ) : null}
            <p className="mt-3 text-center text-sm">
                People ahead: {ticket.people_ahead}
            </p>
            <p className="text-center text-sm">
                ETA:{' '}
                {ticket.people_ahead === 0
                    ? 'You are next'
                    : `${ticket.estimated_wait_minutes} min`}
            </p>
            {kiosk.branding.print_qr_code ? (
                <img
                    src={
                        'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data=' +
                        encodeURIComponent(ticket.qr_value)
                    }
                    alt="Ticket QR code"
                    className="mx-auto mt-4 size-28"
                    onLoad={onQrReady}
                    onError={onQrReady}
                />
            ) : null}
            {kiosk.branding.footer ? (
                <p className="mt-4 text-center text-xs">
                    {kiosk.branding.footer}
                </p>
            ) : null}
        </div>
    );
}
