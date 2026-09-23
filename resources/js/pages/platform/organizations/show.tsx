import { Head, router, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Option = { value: string; label: string };

type Organization = {
    id: number;
    name: string;
    slug: string;
    plan_key: string;
    plan_name: string;
    subscription_status: string;
    status_label: string;
    suspended_at: string | null;
    screens_count: number;
    members_count: number;
    storage_bytes: number;
    bandwidth_used_bytes: number;
    members: { id: number; name: string; email: string }[];
};

type Props = {
    organization: Organization;
    plans: Option[];
    statuses: Option[];
};

export default function PlatformOrganizationShow({ organization, plans, statuses }: Props) {
    const form = useForm({
        plan_key: organization.plan_key,
        subscription_status: organization.subscription_status,
    });

    return (
        <>
            <Head title={organization.name} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={organization.name}
                    description={`${organization.screens_count} screens · ${organization.members_count} users`}
                />
                {organization.suspended_at && (
                    <p className="text-destructive">This organization is suspended.</p>
                )}
                <form
                    className="grid max-w-xl gap-3 rounded-lg border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(`/platform/organizations/${organization.slug}`);
                    }}
                >
                    <div className="space-y-1">
                        <Label htmlFor="plan_key">Plan</Label>
                        <select
                            id="plan_key"
                            className="w-full rounded-md border bg-background p-2"
                            value={form.data.plan_key}
                            onChange={(event) => form.setData('plan_key', event.target.value)}
                        >
                            {plans.map((plan) => (
                                <option key={plan.value} value={plan.value}>
                                    {plan.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="subscription_status">Subscription</Label>
                        <select
                            id="subscription_status"
                            className="w-full rounded-md border bg-background p-2"
                            value={form.data.subscription_status}
                            onChange={(event) => form.setData('subscription_status', event.target.value)}
                        >
                            {statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button type="submit">Save</Button>
                </form>
                <div className="flex gap-2">
                    {organization.suspended_at ? (
                        <Button onClick={() => router.post(`/platform/organizations/${organization.slug}/restore`)}>
                            Restore
                        </Button>
                    ) : (
                        <Button
                            variant="destructive"
                            onClick={() => router.post(`/platform/organizations/${organization.slug}/suspend`)}
                        >
                            Suspend
                        </Button>
                    )}
                </div>
                <section>
                    <h2 className="mb-2 font-medium">Members</h2>
                    <ul className="space-y-1 text-sm">
                        {organization.members.map((member) => (
                            <li key={member.id}>
                                {member.name} · {member.email}
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

PlatformOrganizationShow.layout = () => ({
    breadcrumbs: [
        { title: 'Platform', href: '/platform' },
        { title: 'Organizations', href: '/platform/organizations' },
        { title: 'Organization', href: '/platform/organizations' },
    ],
});
