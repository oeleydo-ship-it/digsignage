import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';

type PairingState =
    | { status: 'idle' }
    | { status: 'pending'; code: string; expires_at: string }
    | { status: 'paired'; device_uuid: string }
    | { status: 'expired' }
    | { status: 'error'; message: string };

export default function PlayerSetup() {
    const [state, setState] = useState<PairingState>({ status: 'idle' });

    const start = async () => {
        setState({ status: 'idle' });

        const response = await fetch('/api/player/v1/registrations', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
        });

        if (!response.ok) {
            setState({
                status: 'error',
                message: 'Unable to start pairing. Try again shortly.',
            });
            return;
        }

        const payload = (await response.json()) as {
            code: string;
            expires_at: string;
        };

        setState({
            status: 'pending',
            code: payload.code,
            expires_at: payload.expires_at,
        });
    };

    useEffect(() => {
        if (state.status !== 'pending') {
            return;
        }

        const timer = window.setInterval(async () => {
            const response = await fetch(
                `/api/player/v1/registrations/${encodeURIComponent(state.code)}`,
                { headers: { Accept: 'application/json' } },
            );
            const payload = (await response.json()) as {
                status: string;
                device_uuid?: string;
                device_token?: string;
            };

            if (payload.status === 'expired') {
                setState({ status: 'expired' });
            }

            if (payload.status === 'paired' && payload.device_uuid) {
                if (payload.device_token) {
                    window.localStorage.setItem(
                        'digsignage.device_token',
                        payload.device_token,
                    );
                    window.localStorage.setItem(
                        'digsignage.device_uuid',
                        payload.device_uuid,
                    );
                }

                setState({
                    status: 'paired',
                    device_uuid: payload.device_uuid,
                });
                window.location.href = '/player';
            }
        }, 2500);

        return () => window.clearInterval(timer);
    }, [state]);

    return (
        <>
            <Head title="Player setup" />
            <div className="bg-background flex min-h-screen flex-col items-center justify-center p-6">
                <div className="w-full max-w-lg space-y-6 text-center">
                    <p className="text-muted-foreground text-sm tracking-[0.3em] uppercase">
                        Digital signage player
                    </p>
                    {state.status === 'idle' && (
                        <>
                            <h1 className="text-3xl font-semibold">
                                Register this display
                            </h1>
                            <Button onClick={start} data-test="start-pairing">
                                Show pairing code
                            </Button>
                        </>
                    )}
                    {state.status === 'pending' && (
                        <>
                            <h1 className="text-3xl font-semibold">
                                Enter this code in the dashboard
                            </h1>
                            <div
                                className="font-mono text-5xl tracking-[0.25em]"
                                data-test="player-code"
                            >
                                {state.code}
                            </div>
                            <p className="text-muted-foreground text-sm">
                                Waiting for authorization…
                            </p>
                        </>
                    )}
                    {state.status === 'paired' && (
                        <>
                            <h1 className="text-3xl font-semibold">
                                Display paired
                            </h1>
                            <p className="text-muted-foreground">
                                Starting playback…
                            </p>
                        </>
                    )}
                    {state.status === 'expired' && (
                        <>
                            <h1 className="text-3xl font-semibold">
                                Code expired
                            </h1>
                            <Button onClick={start}>Get a new code</Button>
                        </>
                    )}
                    {state.status === 'error' && (
                        <>
                            <h1 className="text-3xl font-semibold">
                                Pairing failed
                            </h1>
                            <p className="text-muted-foreground">
                                {state.message}
                            </p>
                            <Button onClick={start}>Try again</Button>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}
