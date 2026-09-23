import { router, usePage } from '@inertiajs/react';
import { CalendarPlus, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { submitAppointmentRequest } from '@/lib/queue-appointments';
import { QueuePageShell, queueBreadcrumbs } from '@/pages/queue/queue-page-shell';
import type { QueueAppointmentRecord, QueueAppointmentRules, QueueAppointmentStatus, QueuePermissions } from '@/types';
import type { Paginated } from '@/types/signage';

type Option = { id: number; name: string; location_id?: number | null; location_name?: string | null; weight?: number };
type StatusOption = { value: QueueAppointmentStatus; label: string };
type Props = { appointments: Paginated<QueueAppointmentRecord>; services: Option[]; locations: Option[]; priorities: Option[]; rules: QueueAppointmentRules; statuses: StatusOption[]; permissions: QueuePermissions };
const emptyForm = { customer_name: '', customer_phone: '', customer_email: '', queue_service_id: '', location_id: '', scheduled_at: '', reference: '', status: 'scheduled' as QueueAppointmentStatus };
const visitOptions = () => ({ preserveScroll: true, headers: { 'X-Stay-On-Page': window.location.href } });

export default function QueueAppointments({ appointments, services, locations, priorities, rules, statuses, permissions }: Props) {
    const slug = usePage().props.currentTeam?.slug ?? '';
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<QueueAppointmentRecord | null>(null);
    const [deleting, setDeleting] = useState<QueueAppointmentRecord | null>(null);
    const [form, setForm] = useState(emptyForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [ruleForm, setRuleForm] = useState(rules);
    const [ruleErrors, setRuleErrors] = useState<Record<string, string>>({});

    const openCreate = () => { setEditing(null); setForm(emptyForm); setErrors({}); setOpen(true); };
    const openEdit = (appointment: QueueAppointmentRecord) => {
        setEditing(appointment);
        setForm({ customer_name: appointment.customer_name, customer_phone: appointment.customer_phone ?? '', customer_email: appointment.customer_email ?? '', queue_service_id: String(appointment.queue_service_id), location_id: appointment.location_id ? String(appointment.location_id) : '', scheduled_at: appointment.scheduled_at.slice(0, 16), reference: appointment.reference, status: appointment.status });
        setErrors({}); setOpen(true);
    };
    const save = () => {
        const payload = { ...form, queue_service_id: Number(form.queue_service_id), location_id: form.location_id ? Number(form.location_id) : null };
        const url = editing ? `/${slug}/queue/appointments/${editing.id}` : `/${slug}/queue/appointments`;
        const options = {
            ...visitOptions(),
            onStart: () => setProcessing(true),
            onError: setErrors,
            onSuccess: () => setOpen(false),
            onFinish: () => setProcessing(false),
        };

        submitAppointmentRequest(router, editing !== null, url, payload, options);
    };

    return <QueuePageShell title="Appointments" description="Schedule customers and convert arrivals into prioritized queue tickets from staff, kiosk, reference, or QR check-in.">
        <section className="dashboard-card space-y-4 p-5">
            <div><h2 className="font-semibold">Check-in and priority rules</h2><p className="text-muted-foreground mt-1 text-sm">Control when appointment holders can enter the queue and which priority they receive.</p></div>
            <div className="grid gap-4 md:grid-cols-3">
                <div className="grid gap-2"><Label>Appointment priority</Label><select className="border-input h-9 rounded-md border bg-transparent px-3 text-sm" value={ruleForm.priority_id ?? ''} onChange={(event) => setRuleForm({ ...ruleForm, priority_id: event.target.value ? Number(event.target.value) : null })}><option value="">Normal service priority</option>{priorities.map((item) => <option key={item.id} value={item.id}>{item.name} ({item.weight})</option>)}</select><InputError message={ruleErrors.priority_id} /></div>
                <div className="grid gap-2"><Label>Open before appointment (minutes)</Label><Input type="number" min={0} max={1440} value={ruleForm.check_in_before_minutes} onChange={(event) => setRuleForm({ ...ruleForm, check_in_before_minutes: Number(event.target.value) })} /><InputError message={ruleErrors.check_in_before_minutes} /></div>
                <div className="grid gap-2"><Label>Close after appointment (minutes)</Label><Input type="number" min={0} max={1440} value={ruleForm.check_in_after_minutes} onChange={(event) => setRuleForm({ ...ruleForm, check_in_after_minutes: Number(event.target.value) })} /><InputError message={ruleErrors.check_in_after_minutes} /></div>
            </div>
            {permissions.canManageQueue ? <Button data-test="save-appointment-rules" onClick={() => router.patch(`/${slug}/queue/appointments/rules`, ruleForm, { ...visitOptions(), onError: setRuleErrors })}>Save rules</Button> : null}
        </section>

        <div className="flex items-center justify-between gap-4"><p className="text-muted-foreground text-sm">{appointments.total} appointment{appointments.total === 1 ? '' : 's'}</p>{permissions.canManageQueue ? <Button onClick={openCreate}><CalendarPlus className="size-4" />Add appointment</Button> : null}</div>
        {appointments.data.length === 0 ? <EmptyState title="No appointments" description="Schedule the first customer appointment." action={permissions.canManageQueue ? <Button onClick={openCreate}>Add appointment</Button> : undefined} /> : <section className="dashboard-card overflow-x-auto"><table className="w-full min-w-[900px] text-left text-sm"><thead className="border-b text-xs uppercase"><tr className="text-muted-foreground"><th className="px-5 py-3">Customer</th><th className="px-3 py-3">Service</th><th className="px-3 py-3">Appointment</th><th className="px-3 py-3">Reference</th><th className="px-3 py-3">Status</th><th className="px-5 py-3 text-right">Actions</th></tr></thead><tbody className="divide-y">{appointments.data.map((appointment) => <tr key={appointment.id}><td className="px-5 py-4"><p className="font-medium">{appointment.customer_name}</p><p className="text-muted-foreground text-xs">{appointment.customer_email ?? appointment.customer_phone ?? '—'}</p></td><td className="px-3 py-4"><p>{appointment.service_name}</p><p className="text-muted-foreground text-xs">{appointment.location_name ?? 'All locations'}</p></td><td className="px-3 py-4">{new Date(appointment.scheduled_at).toLocaleString()}</td><td className="px-3 py-4 font-mono">{appointment.reference}</td><td className="px-3 py-4"><Badge variant={appointment.status === 'checked_in' ? 'secondary' : 'outline'}>{appointment.status_label}</Badge>{appointment.ticket_number ? <p className="mt-1 text-xs">Ticket {appointment.ticket_number}</p> : null}</td><td className="px-5 py-4"><div className="flex justify-end gap-2"><Button size="sm" variant="outline" asChild><a href={'https://api.qrserver.com/v1/create-qr-code/?size=480x480&margin=12&data=' + encodeURIComponent(appointment.check_in_url)} target="_blank" rel="noreferrer"><ExternalLink className="size-3" />QR code</a></Button>{permissions.canManageQueue && appointment.status === 'scheduled' ? <Button size="sm" onClick={() => router.post(`/${slug}/queue/appointments/${appointment.id}/check-in`, {}, visitOptions())}>Check in</Button> : null}{permissions.canManageQueue ? <><Button size="sm" variant="outline" onClick={() => openEdit(appointment)}>Edit</Button><Button size="sm" variant="destructive" onClick={() => setDeleting(appointment)}>Delete</Button></> : null}</div></td></tr>)}</tbody></table></section>}

        <Dialog open={open} onOpenChange={setOpen}><DialogContent className="sm:max-w-xl"><DialogHeader><DialogTitle>{editing ? 'Edit appointment' : 'Add appointment'}</DialogTitle><DialogDescription>The reference may be entered at a kiosk or opened as a QR check-in link.</DialogDescription></DialogHeader><div className="grid gap-4"><div className="grid gap-2"><Label>Customer</Label><Input value={form.customer_name} onChange={(event) => setForm({ ...form, customer_name: event.target.value })} /><InputError message={errors.customer_name} /></div><div className="grid gap-4 sm:grid-cols-2"><div className="grid gap-2"><Label>Email</Label><Input type="email" value={form.customer_email} onChange={(event) => setForm({ ...form, customer_email: event.target.value })} /></div><div className="grid gap-2"><Label>Phone</Label><Input value={form.customer_phone} onChange={(event) => setForm({ ...form, customer_phone: event.target.value })} /></div></div><div className="grid gap-4 sm:grid-cols-2"><div className="grid gap-2"><Label>Service</Label><select className="border-input h-9 rounded-md border bg-transparent px-3 text-sm" value={form.queue_service_id} onChange={(event) => { const service = services.find((item) => item.id === Number(event.target.value)); setForm({ ...form, queue_service_id: event.target.value, location_id: service?.location_id ? String(service.location_id) : form.location_id }); }}><option value="">Select service</option>{services.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select><InputError message={errors.queue_service_id} /></div><div className="grid gap-2"><Label>Location</Label><select className="border-input h-9 rounded-md border bg-transparent px-3 text-sm" value={form.location_id} onChange={(event) => setForm({ ...form, location_id: event.target.value })}><option value="">All locations</option>{locations.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select><InputError message={errors.location_id} /></div></div><div className="grid gap-4 sm:grid-cols-2"><div className="grid gap-2"><Label>Appointment date and time</Label><Input type="datetime-local" value={form.scheduled_at} onChange={(event) => setForm({ ...form, scheduled_at: event.target.value })} /><InputError message={errors.scheduled_at} /></div><div className="grid gap-2"><Label>Reference</Label><Input value={form.reference} onChange={(event) => setForm({ ...form, reference: event.target.value.toUpperCase() })} placeholder="Generated automatically" /><InputError message={errors.reference} /></div></div>{editing ? <div className="grid gap-2"><Label>Status</Label><select className="border-input h-9 rounded-md border bg-transparent px-3 text-sm" value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value as QueueAppointmentStatus })}>{statuses.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select></div> : null}</div><DialogFooter><Button type="button" data-test="save-appointment" disabled={processing} onClick={save}>{processing ? 'Saving…' : 'Save appointment'}</Button></DialogFooter></DialogContent></Dialog>
        <ConfirmDialog open={deleting !== null} title="Delete appointment" description="This removes the appointment record. An already-issued queue ticket is retained." confirmLabel="Delete" onOpenChange={(next) => { if (!next) setDeleting(null); }} onConfirm={() => { if (!deleting) return; router.delete(`/${slug}/queue/appointments/${deleting.id}`, { ...visitOptions(), onFinish: () => setDeleting(null) }); }} />
    </QueuePageShell>;
}

QueueAppointments.layout = queueBreadcrumbs('Appointments', '/appointments');
