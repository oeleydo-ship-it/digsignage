import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type {
    EmergencyAuditRecord,
    EmergencyDeliveryRecord,
    EmergencyDetail,
    EmergencyPermissions,
} from '@/types';

type Props = {
    emergency: EmergencyDetail;
    deliveries: EmergencyDeliveryRecord[];
    audits: EmergencyAuditRecord[];
    permissions: EmergencyPermissions;
};

export default function EmergencyShow({
    emergency,
    deliveries,
    audits,
    permissions,
}: Props) {
    const slug = usePage().props.currentTeam?.slug ?? '';
    const [confirmStart, setConfirmStart] = useState(false);
    const [confirmStop, setConfirmStop] = useState(false);
    const [processing, setProcessing] = useState(false);
    const canStart =
        permissions.canStartEmergency &&
        (emergency.status === 'draft' || emergency.status === 'scheduled');
    const canStop =
        permissions.canStopEmergency &&
        (emergency.status === 'active' || emergency.status === 'scheduled');

    return (
        <>
            <Head title={emergency.title} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={emergency.title}
                        description="Start requires confirmation. Active broadcasts override scheduled playback until they are stopped or expire."
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/${slug}/emergencies`}>Back</Link>
                        </Button>
                        {canStart && (
                            <Button onClick={() => setConfirmStart(true)}>
                                Start broadcast
                            </Button>
                        )}
                        {canStop && (
                            <Button
                                variant="destructive"
                                onClick={() => setConfirmStop(true)}
                            >
                                Stop broadcast
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <InfoCard label="Severity" value={emergency.severity_label} />
                    <InfoCard label="Status" value={emergency.status_label} />
                    <InfoCard
                        label="Audience"
                        value={`${emergency.audience_count} screens`}
                    />
                </div>

                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2">
                    <Field label="Message" value={emergency.message} />
                    <Field label="Instructions" value={emergency.instructions} />
                    <Field label="Image" value={emergency.image_name} />
                    <Field label="Video" value={emergency.video_name} />
                    <Field
                        label="Start"
                        value={
                            emergency.starts_at
                                ? new Date(emergency.starts_at).toLocaleString()
                                : null
                        }
                    />
                    <Field
                        label="Expiration"
                        value={
                            emergency.expires_at
                                ? new Date(emergency.expires_at).toLocaleString()
                                : null
                        }
                    />
                    <Field label="Created by" value={emergency.created_by} />
                    <Field label="Started by" value={emergency.started_by} />
                </div>

                <section className="rounded-xl border">
                    <div className="border-b px-4 py-3">
                        <h2 className="font-semibold">Delivery status</h2>
                        <p className="text-muted-foreground text-sm">
                            Pairing is required to send the overlay. Unpaired
                            screens stay unreachable until they check in.
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Screen
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">Sent</th>
                                    <th className="px-4 py-3 font-medium">
                                        Acknowledged
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {deliveries.length === 0 ? (
                                    <tr>
                                        <td
                                            className="text-muted-foreground px-4 py-6"
                                            colSpan={4}
                                        >
                                            Delivery rows appear after start.
                                        </td>
                                    </tr>
                                ) : (
                                    deliveries.map((delivery) => (
                                        <tr
                                            key={delivery.id}
                                            className="border-t"
                                        >
                                            <td className="px-4 py-3">
                                                {delivery.screen_name}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant="outline">
                                                    {delivery.status_label}
                                                </Badge>
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {delivery.sent_at
                                                    ? new Date(
                                                          delivery.sent_at,
                                                      ).toLocaleString()
                                                    : '—'}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {delivery.acknowledged_at
                                                    ? new Date(
                                                          delivery.acknowledged_at,
                                                      ).toLocaleString()
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="rounded-xl border">
                    <div className="border-b px-4 py-3">
                        <h2 className="font-semibold">Audit trail</h2>
                    </div>
                    <ul className="divide-y">
                        {audits.length === 0 ? (
                            <li className="text-muted-foreground px-4 py-6 text-sm">
                                No audit events yet.
                            </li>
                        ) : (
                            audits.map((audit) => (
                                <li key={audit.id} className="px-4 py-3 text-sm">
                                    <span className="font-medium">
                                        {audit.action_label}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        {audit.user ?? 'System'}
                                        {audit.created_at
                                            ? ` · ${new Date(audit.created_at).toLocaleString()}`
                                            : ''}
                                    </span>
                                </li>
                            ))
                        )}
                    </ul>
                </section>
            </div>

            <ConfirmDialog
                open={confirmStart}
                title="Start emergency broadcast?"
                description={`This will override playback on ${emergency.audience_count} screen(s). Confirm only if this message must go out now.`}
                confirmLabel="Start broadcast"
                processing={processing}
                onOpenChange={setConfirmStart}
                onConfirm={() => {
                    setProcessing(true);
                    router.post(
                        `/${slug}/emergencies/${emergency.id}/start`,
                        { confirmed: true },
                        {
                            preserveScroll: true,
                            onFinish: () => {
                                setProcessing(false);
                                setConfirmStart(false);
                            },
                        },
                    );
                }}
            />
            <ConfirmDialog
                open={confirmStop}
                title="Stop emergency broadcast?"
                description="Players will drop the overlay and return to scheduled or playlist playback."
                confirmLabel="Stop broadcast"
                processing={processing}
                onOpenChange={setConfirmStop}
                onConfirm={() => {
                    setProcessing(true);
                    router.post(
                        `/${slug}/emergencies/${emergency.id}/stop`,
                        { confirmed: true },
                        {
                            preserveScroll: true,
                            onFinish: () => {
                                setProcessing(false);
                                setConfirmStop(false);
                            },
                        },
                    );
                }}
            />
        </>
    );
}

function InfoCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-muted-foreground text-sm">{label}</p>
            <p className="mt-2 text-xl font-semibold">{value}</p>
        </div>
    );
}

function Field({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <p className="text-muted-foreground text-sm">{label}</p>
            <p className="mt-1 text-sm">{value || '—'}</p>
        </div>
    );
}

EmergencyShow.layout = (props: {
    currentTeam?: { slug: string } | null;
    emergency?: { title: string; id: number };
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Emergencies',
            href: props.currentTeam
                ? `/${props.currentTeam.slug}/emergencies`
                : '/',
        },
        {
            title: props.emergency?.title ?? 'Broadcast',
            href:
                props.currentTeam && props.emergency
                    ? `/${props.currentTeam.slug}/emergencies/${props.emergency.id}`
                    : '/',
        },
    ],
});
