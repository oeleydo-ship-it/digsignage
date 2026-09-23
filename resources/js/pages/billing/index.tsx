import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    BillingInvoice,
    BillingPlan,
    BillingSubscription,
    BillingUsage,
} from '@/types';

type Props = {
    driver: string;
    subscription: BillingSubscription;
    usage: BillingUsage;
    plans: BillingPlan[];
    featureOptions: { value: string; label: string }[];
    coupons: string[];
    invoices: BillingInvoice[];
};

function money(cents: number | null, currency: string): string {
    if (cents === null) {
        return 'Custom';
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currency.toUpperCase(),
    }).format(cents / 100);
}

function meter(used: number, limit: number | null): string {
    if (limit === null) {
        return `${used.toLocaleString()} / unlimited`;
    }

    return `${used.toLocaleString()} / ${limit.toLocaleString()}`;
}

function bytes(value: number): string {
    if (value < 1024) {
        return `${value} B`;
    }

    const units = ['KB', 'MB', 'GB', 'TB'];
    let size = value / 1024;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit += 1;
    }

    return `${size.toFixed(1)} ${units[unit]}`;
}

function when(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

export default function BillingIndex({
    driver,
    subscription,
    usage,
    plans,
    featureOptions,
    coupons,
    invoices,
}: Props) {
    const slug = usePage().props.currentTeam?.slug ?? '';
    const [coupon, setCoupon] = useState(subscription.coupon_code ?? '');

    const post = (path: string, data: Record<string, string> = {}) => {
        router.post(`/${slug}/billing/${path}`, data);
    };

    return (
        <>
            <Head title="Billing" />
            <div className="flex flex-1 flex-col gap-6">
                <Heading
                    title="Billing"
                    description="Plans, usage limits, invoices, and subscription changes for this organization. Limits are enforced on the server."
                />

                <section className="rounded-lg border p-4">
                    <h2 className="text-lg font-medium">
                        Current subscription
                    </h2>
                    <p className="text-muted-foreground mt-2 text-sm">
                        {subscription.plan_name ?? 'No plan'} ·{' '}
                        {subscription.status_label ?? 'Unknown'}
                        {subscription.trial_ends_at
                            ? ` · Trial ends ${when(subscription.trial_ends_at)}`
                            : ''}
                        {subscription.subscription_ends_at
                            ? ` · Access through ${when(subscription.subscription_ends_at)}`
                            : ''}
                    </p>
                    <p className="mt-1 text-sm">
                        Resource changes are{' '}
                        {subscription.allows_mutations
                            ? 'allowed'
                            : 'blocked until billing is current'}
                        .
                    </p>
                    {subscription.coupon_code && (
                        <p className="mt-1 text-sm">
                            Coupon: {subscription.coupon_code}
                        </p>
                    )}
                    <div className="mt-4 flex flex-wrap gap-2">
                        {subscription.status === 'canceled' ? (
                            <Button onClick={() => post('resume')}>
                                Resume subscription
                            </Button>
                        ) : (
                            <Button
                                variant="outline"
                                onClick={() => post('cancel')}
                            >
                                Cancel at period end
                            </Button>
                        )}
                        <Button
                            variant="secondary"
                            onClick={() => post('portal')}
                        >
                            {driver === 'stripe'
                                ? 'Open billing portal'
                                : 'Customer portal'}
                        </Button>
                    </div>
                </section>

                <section className="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                    <article className="rounded-lg border p-4">
                        <p className="text-muted-foreground text-sm">Screens</p>
                        <p className="text-lg font-medium">
                            {meter(usage.screens, usage.screens_limit)}
                        </p>
                    </article>
                    <article className="rounded-lg border p-4">
                        <p className="text-muted-foreground text-sm">Users</p>
                        <p className="text-lg font-medium">
                            {meter(usage.users, usage.users_limit)}
                        </p>
                    </article>
                    <article className="rounded-lg border p-4">
                        <p className="text-muted-foreground text-sm">Storage</p>
                        <p className="text-lg font-medium">
                            {bytes(usage.storage_bytes)}
                            {usage.storage_limit_bytes
                                ? ` / ${bytes(usage.storage_limit_bytes)}`
                                : ' / unlimited'}
                        </p>
                    </article>
                    <article className="rounded-lg border p-4">
                        <p className="text-muted-foreground text-sm">
                            Bandwidth
                        </p>
                        <p className="text-lg font-medium">
                            {bytes(usage.bandwidth_bytes)}
                            {usage.bandwidth_limit_bytes
                                ? ` / ${bytes(usage.bandwidth_limit_bytes)}`
                                : ' / unlimited'}
                        </p>
                    </article>
                </section>

                <section className="grid gap-4 md:grid-cols-3">
                    {plans.map((plan) => (
                        <article
                            key={plan.key}
                            className="flex flex-col rounded-lg border p-4"
                        >
                            <h3 className="text-lg font-medium">{plan.name}</h3>
                            <p className="mt-1 text-2xl font-semibold">
                                {money(plan.price_cents, 'usd')}
                            </p>
                            <ul className="text-muted-foreground mt-3 space-y-1 text-sm">
                                <li>
                                    {plan.screens === null
                                        ? 'Unlimited screens'
                                        : `${plan.screens} screens`}
                                </li>
                                <li>
                                    {plan.storage_gb === null
                                        ? 'Unlimited storage'
                                        : `${plan.storage_gb} GB storage`}
                                </li>
                                <li>
                                    {plan.users === null
                                        ? 'Unlimited users'
                                        : `${plan.users} users`}
                                </li>
                                <li>
                                    {plan.bandwidth_gb === null
                                        ? 'Unlimited bandwidth'
                                        : `${plan.bandwidth_gb} GB bandwidth`}
                                </li>
                                <li>
                                    {plan.advanced
                                        ? 'Multi-zone channels included'
                                        : 'Playlist and live channels'}
                                </li>
                                {featureOptions
                                    .filter(
                                        (feature) =>
                                            plan.features[feature.value] &&
                                            feature.value !== 'multi_zone',
                                    )
                                    .map((feature) => (
                                        <li key={feature.value}>
                                            {feature.label}
                                        </li>
                                    ))}
                            </ul>
                            <div className="mt-auto flex flex-col gap-2 pt-4">
                                {subscription.plan_key === plan.key ? (
                                    <Button disabled>Current plan</Button>
                                ) : subscription.plan_key ? (
                                    <Button
                                        onClick={() =>
                                            post('swap', { plan_key: plan.key })
                                        }
                                    >
                                        Switch to {plan.name}
                                    </Button>
                                ) : (
                                    <Button
                                        onClick={() =>
                                            post('checkout', {
                                                plan_key: plan.key,
                                                coupon_code: coupon,
                                            })
                                        }
                                    >
                                        Subscribe
                                    </Button>
                                )}
                                {subscription.plan_key &&
                                    subscription.plan_key !== plan.key && (
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                post('checkout', {
                                                    plan_key: plan.key,
                                                    coupon_code: coupon,
                                                })
                                            }
                                        >
                                            Checkout {plan.name}
                                        </Button>
                                    )}
                            </div>
                        </article>
                    ))}
                </section>

                <section className="rounded-lg border p-4">
                    <h2 className="text-lg font-medium">Coupon</h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Known codes: {coupons.join(', ') || 'none configured'}.
                    </p>
                    <form
                        className="mt-3 flex max-w-md gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            post('coupon', { coupon_code: coupon });
                        }}
                    >
                        <div className="flex-1 space-y-1">
                            <Label htmlFor="coupon_code">Coupon code</Label>
                            <Input
                                id="coupon_code"
                                value={coupon}
                                onChange={(event) =>
                                    setCoupon(event.target.value)
                                }
                            />
                        </div>
                        <Button className="self-end" type="submit">
                            Apply
                        </Button>
                    </form>
                </section>

                <section className="rounded-lg border">
                    <h2 className="px-4 pt-4 text-lg font-medium">Invoices</h2>
                    <div className="overflow-x-auto p-4">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="py-2">Number</th>
                                    <th className="py-2">Amount</th>
                                    <th className="py-2">Status</th>
                                    <th className="py-2">Paid</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.length === 0 ? (
                                    <tr>
                                        <td
                                            className="text-muted-foreground py-3"
                                            colSpan={4}
                                        >
                                            No invoices yet.
                                        </td>
                                    </tr>
                                ) : (
                                    invoices.map((invoice) => (
                                        <tr
                                            key={invoice.id}
                                            className="border-b"
                                        >
                                            <td className="py-2">
                                                {invoice.hosted_invoice_url ? (
                                                    <a
                                                        className="underline"
                                                        href={
                                                            invoice.hosted_invoice_url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        {invoice.number}
                                                    </a>
                                                ) : (
                                                    invoice.number
                                                )}
                                            </td>
                                            <td className="py-2">
                                                {money(
                                                    invoice.amount_cents,
                                                    invoice.currency,
                                                )}
                                            </td>
                                            <td className="py-2">
                                                {invoice.status}
                                            </td>
                                            <td className="py-2">
                                                {when(invoice.paid_at)}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

BillingIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Settings',
            href: '/settings/profile',
        },
        {
            title: 'Billing',
            href: props.currentTeam
                ? `/${props.currentTeam.slug}/billing`
                : '/',
        },
    ],
});
