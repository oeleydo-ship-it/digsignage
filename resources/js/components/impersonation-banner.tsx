import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export function ImpersonationBanner() {
    const impersonation = usePage().props.impersonation;

    if (!impersonation) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 bg-amber-500 px-4 py-2 text-sm text-black">
            <p>
                Viewing as <strong>{impersonation.target_name}</strong>. Signed in
                originally as {impersonation.actor_name} ({impersonation.actor_email}).
                Dangerous account and billing actions are blocked.
            </p>
            <Button
                size="sm"
                variant="secondary"
                onClick={() => router.post('/platform/impersonation/stop')}
            >
                Exit impersonation
            </Button>
        </div>
    );
}
