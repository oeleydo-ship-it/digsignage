import { Head, router, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleAlert,
    CircleDashed,
    Copy,
    Mail,
    Send,
} from 'lucide-react';
import { type ReactNode, useEffect, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LOGO_TONE_FILTERS } from '@/components/app-logo';

type Plan = {
    key: string;
    name: string;
    price_cents: number | null;
    paid: boolean;
    stripe_price_id: string | null;
};

type ChecklistItem = {
    key: string;
    label: string;
    done: boolean;
    required: boolean;
    tab: Tab;
    hint: string;
};

type PaymentCheck = {
    account: string;
    livemode: boolean;
    plans: Record<string, { ok: boolean; message: string }>;
};

type Props = {
    general: {
        name: string;
        support_email: string | null;
        allow_registration: boolean;
        terms_url: string | null;
        privacy_url: string | null;
    };
    brand: {
        logo_url: string | null;
        favicon_url: string | null;
        logo_tone: LogoTone;
        show_name: boolean;
    };
    payments: {
        driver: string;
        currency: string;
        trial_days: number;
        stripe_key: string | null;
        has_secret: boolean;
        has_webhook_secret: boolean;
        secret_hint: string | null;
        webhook_url: string;
        plans: Plan[];
    };
    mail: {
        mailer: 'smtp' | 'log';
        host: string | null;
        port: number | null;
        username: string | null;
        has_password: boolean;
        encryption: 'tls' | 'ssl' | 'none';
        from_address: string | null;
        from_name: string | null;
    };
    checklist: ChecklistItem[];
};

const TABS = [
    { value: 'general', label: 'General' },
    { value: 'branding', label: 'Branding' },
    { value: 'payments', label: 'Payments' },
    { value: 'mail', label: 'Email (SMTP)' },
] as const;

type Tab = (typeof TABS)[number]['value'];

type Errors = Record<string, string>;

type LogoTone = 'original' | 'white' | 'black';

const options = (onError: (errors: Errors) => void) => ({
    preserveScroll: true,
    preserveState: true,
    onError,
    onSuccess: () => onError({}),
});

function initialTab(): Tab {
    if (typeof window === 'undefined') {
        return 'general';
    }

    const hash = window.location.hash.replace('#', '');

    return TABS.find((tab) => tab.value === hash)?.value ?? 'general';
}

export default function PlatformSettingsPage(props: Props) {
    const [tab, setTab] = useState<Tab>(initialTab);

    const selectTab = (next: Tab) => {
        setTab(next);
        window.history.replaceState(window.history.state, '', `#${next}`);
    };

    return (
        <>
            <Head title="General settings" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="General settings"
                    description="Name and brand the platform, connect the payment gateway to your plans, and set up outgoing email."
                />

                <Checklist items={props.checklist} onOpen={selectTab} />

                <div
                    role="tablist"
                    aria-label="General settings"
                    className="bg-muted flex gap-1 overflow-x-auto rounded-lg p-1"
                >
                    {TABS.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            role="tab"
                            aria-selected={tab === option.value}
                            data-test={`settings-tab-${option.value}`}
                            onClick={() => selectTab(option.value)}
                            className={`shrink-0 rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-colors ${
                                tab === option.value
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>

                <div role="tabpanel">
                    {tab === 'general' && (
                        <GeneralTab general={props.general} />
                    )}
                    {tab === 'branding' && (
                        <BrandingTab branding={props.brand} />
                    )}
                    {tab === 'payments' && (
                        <PaymentsTab payments={props.payments} />
                    )}
                    {tab === 'mail' && <MailTab mail={props.mail} />}
                </div>
            </div>
        </>
    );
}

function Checklist({
    items,
    onOpen,
}: {
    items: ChecklistItem[];
    onOpen: (tab: Tab) => void;
}) {
    const required = items.filter((item) => item.required);
    const done = required.filter((item) => item.done).length;

    return (
        <section
            className="dashboard-card space-y-3 p-5"
            data-test="settings-checklist"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-base font-semibold">Setup checklist</h2>
                <span
                    className={`text-sm font-medium ${done === required.length ? 'text-emerald-600' : 'text-amber-600'}`}
                >
                    {done} of {required.length} required settings done
                </span>
            </div>
            <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                {items.map((item) => (
                    <li key={item.key}>
                        <button
                            type="button"
                            onClick={() => onOpen(item.tab)}
                            className="hover:bg-accent flex w-full items-start gap-2 rounded-md border p-3 text-left"
                        >
                            {item.done ? (
                                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                            ) : item.required ? (
                                <CircleAlert className="mt-0.5 size-4 shrink-0 text-amber-600" />
                            ) : (
                                <CircleDashed className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                            )}
                            <span className="min-w-0">
                                <span className="block text-sm font-medium">
                                    {item.label}
                                    {!item.required && (
                                        <span className="text-muted-foreground font-normal">
                                            {' '}
                                            (optional)
                                        </span>
                                    )}
                                </span>
                                <span className="text-muted-foreground block text-xs">
                                    {item.hint}
                                </span>
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function Card({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section className="dashboard-card space-y-4 p-5">
            <div>
                <h2 className="text-base font-semibold">{title}</h2>
                {description && (
                    <p className="text-muted-foreground mt-1 text-sm">
                        {description}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}

function Field({
    id,
    label,
    error,
    hint,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-muted-foreground text-xs">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

function GeneralTab({ general }: { general: Props['general'] }) {
    const [form, setForm] = useState({
        name: general.name,
        support_email: general.support_email ?? '',
        allow_registration: general.allow_registration,
        terms_url: general.terms_url ?? '',
        privacy_url: general.privacy_url ?? '',
    });
    const [errors, setErrors] = useState<Errors>({});

    return (
        <Card
            title="Platform"
            description="The name appears in the browser tab, the sidebar, sign-in pages and every email."
        >
            <div className="grid gap-4 md:grid-cols-2">
                <Field
                    id="platform-name"
                    label="Platform name"
                    error={errors.name}
                >
                    <Input
                        id="platform-name"
                        data-test="platform-name"
                        value={form.name}
                        maxLength={60}
                        onChange={(event) =>
                            setForm({ ...form, name: event.target.value })
                        }
                    />
                </Field>
                <Field
                    id="support-email"
                    label="Support email"
                    error={errors.support_email}
                >
                    <Input
                        id="support-email"
                        type="email"
                        value={form.support_email}
                        placeholder="support@example.com"
                        onChange={(event) =>
                            setForm({
                                ...form,
                                support_email: event.target.value,
                            })
                        }
                    />
                </Field>
                <Field
                    id="terms-url"
                    label="Terms of service link"
                    error={errors.terms_url}
                >
                    <Input
                        id="terms-url"
                        value={form.terms_url}
                        placeholder="https://example.com/terms"
                        onChange={(event) =>
                            setForm({ ...form, terms_url: event.target.value })
                        }
                    />
                </Field>
                <Field
                    id="privacy-url"
                    label="Privacy policy link"
                    error={errors.privacy_url}
                >
                    <Input
                        id="privacy-url"
                        value={form.privacy_url}
                        placeholder="https://example.com/privacy"
                        onChange={(event) =>
                            setForm({
                                ...form,
                                privacy_url: event.target.value,
                            })
                        }
                    />
                </Field>
            </div>
            <label className="flex items-start gap-2 text-sm">
                <Checkbox
                    className="mt-0.5"
                    checked={form.allow_registration}
                    data-test="allow-registration"
                    onCheckedChange={(checked) =>
                        setForm({
                            ...form,
                            allow_registration: checked === true,
                        })
                    }
                />
                <span>
                    Anyone can sign up
                    <span className="text-muted-foreground block text-xs">
                        When off, only people invited to an organization can
                        create an account.
                    </span>
                </span>
            </label>
            <Button
                data-test="save-general"
                onClick={() =>
                    router.put(
                        '/platform/settings/general',
                        form,
                        options(setErrors),
                    )
                }
            >
                Save
            </Button>
        </Card>
    );
}

function BrandingTab({ branding }: { branding: Props['brand'] }) {
    const [logo, setLogo] = useState<File | null>(null);
    const [favicon, setFavicon] = useState<File | null>(null);
    const [errors, setErrors] = useState<Errors>({});
    const [inputKey, setInputKey] = useState(0);
    const [tone, setTone] = useState<LogoTone>(branding.logo_tone);
    const [showName, setShowName] = useState(branding.show_name);
    const { name } = usePage().props;
    const logoPreview = logo ? URL.createObjectURL(logo) : branding.logo_url;

    const submit = (data: Record<string, File | boolean | string | null>) =>
        router.post('/platform/settings/branding', data, {
            ...options(setErrors),
            forceFormData: true,
            onSuccess: () => {
                setErrors({});
                setLogo(null);
                setFavicon(null);
                setInputKey((key) => key + 1);
            },
        });

    return (
        <Card
            title="Branding"
            description="PNG, JPG or WebP logo up to 2 MB with a transparent background. Wide logos are shown at full width. Favicon: PNG or ICO, 512 KB."
        >
            <div className="grid gap-6 md:grid-cols-2">
                <div className="space-y-3">
                    <Label>Logo</Label>
                    <div className="bg-muted flex h-24 items-center justify-center rounded-lg border">
                        {logo || branding.logo_url ? (
                            <img
                                src={
                                    logo
                                        ? URL.createObjectURL(logo)
                                        : (branding.logo_url ?? '')
                                }
                                alt="Logo preview"
                                className="max-h-16 max-w-[90%] object-contain"
                            />
                        ) : (
                            <span className="text-muted-foreground text-sm">
                                Built-in logo
                            </span>
                        )}
                    </div>
                    <Input
                        key={`logo-${inputKey}`}
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        aria-label="Logo file"
                        data-test="logo-file"
                        onChange={(event) =>
                            setLogo(event.target.files?.[0] ?? null)
                        }
                    />
                    <InputError message={errors.logo} />
                    {branding.logo_url && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => submit({ remove_logo: true })}
                        >
                            Use the built-in logo
                        </Button>
                    )}
                </div>
                <div className="space-y-3">
                    <Label>Favicon</Label>
                    <div className="bg-muted flex h-24 items-center justify-center rounded-lg border">
                        {favicon || branding.favicon_url ? (
                            <img
                                src={
                                    favicon
                                        ? URL.createObjectURL(favicon)
                                        : (branding.favicon_url ?? '')
                                }
                                alt="Favicon preview"
                                className="size-10 object-contain"
                            />
                        ) : (
                            <span className="text-muted-foreground text-sm">
                                Built-in icon
                            </span>
                        )}
                    </div>
                    <Input
                        key={`favicon-${inputKey}`}
                        type="file"
                        accept="image/png,image/x-icon,.ico"
                        aria-label="Favicon file"
                        onChange={(event) =>
                            setFavicon(event.target.files?.[0] ?? null)
                        }
                    />
                    <InputError message={errors.favicon} />
                    {branding.favicon_url && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => submit({ remove_favicon: true })}
                        >
                            Use the built-in icon
                        </Button>
                    )}
                </div>
            </div>

            <div className="grid gap-6 border-t pt-5 md:grid-cols-2">
                <div className="space-y-4">
                    <div className="grid gap-2">
                        <Label>Logo colour in the sidebar</Label>
                        <div
                            className="bg-muted inline-flex w-fit gap-1 rounded-lg p-1"
                            role="radiogroup"
                            aria-label="Logo colour"
                        >
                            {(
                                [
                                    ['original', 'Original'],
                                    ['white', 'White'],
                                    ['black', 'Black'],
                                ] as const
                            ).map(([value, label]) => (
                                <button
                                    key={value}
                                    type="button"
                                    role="radio"
                                    aria-checked={tone === value}
                                    data-test={`logo-tone-${value}`}
                                    onClick={() => setTone(value)}
                                    className={`rounded-md px-3 py-1.5 text-sm font-medium ${
                                        tone === value
                                            ? 'bg-background shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                        <p className="text-muted-foreground text-xs">
                            White suits the dark sidebar; black suits light
                            backgrounds. Original keeps the logo's own colours.
                        </p>
                    </div>
                    <label className="flex items-start gap-2 text-sm">
                        <Checkbox
                            className="mt-0.5"
                            checked={showName}
                            data-test="show-platform-name"
                            onCheckedChange={(checked) =>
                                setShowName(checked === true)
                            }
                        />
                        <span>
                            Show the platform name next to the logo
                            <span className="text-muted-foreground block text-xs">
                                Turn off when the logo already contains the
                                name.
                            </span>
                        </span>
                    </label>
                </div>
                <div className="grid gap-2">
                    <Label>Sidebar preview</Label>
                    <div
                        className="bg-sidebar text-sidebar-foreground flex h-16 items-center justify-center gap-2 rounded-lg border px-4"
                        data-test="sidebar-preview"
                    >
                        {logoPreview ? (
                            <img
                                src={logoPreview}
                                alt=""
                                className="h-10 max-w-[70%] object-contain object-center"
                                style={{ filter: LOGO_TONE_FILTERS[tone] }}
                            />
                        ) : (
                            <span className="bg-sidebar-primary flex size-8 items-center justify-center rounded-md text-xs text-white">
                                ◆
                            </span>
                        )}
                        {showName && (
                            <span className="truncate text-sm font-semibold">
                                {name}
                            </span>
                        )}
                    </div>
                </div>
            </div>

            <Button
                data-test="save-branding"
                onClick={() =>
                    submit({
                        logo,
                        favicon,
                        logo_tone: tone,
                        show_name: showName,
                    })
                }
            >
                Save branding
            </Button>
        </Card>
    );
}

function PaymentsTab({ payments }: { payments: Props['payments'] }) {
    const [form, setForm] = useState({
        driver: payments.driver,
        currency: payments.currency,
        trial_days: payments.trial_days,
        stripe_key: payments.stripe_key ?? '',
        stripe_secret: '',
        stripe_webhook_secret: '',
    });
    const [prices, setPrices] = useState<Record<string, string>>(
        Object.fromEntries(
            payments.plans.map((plan) => [
                plan.key,
                plan.stripe_price_id ?? '',
            ]),
        ),
    );
    const [errors, setErrors] = useState<Errors>({});
    const [check, setCheck] = useState<PaymentCheck | null>(null);
    const [testing, setTesting] = useState(false);
    const stripe = form.driver === 'stripe';

    useEffect(
        () =>
            router.on('flash', (event) => {
                const flash = (event as CustomEvent).detail?.flash;

                if (flash?.paymentCheck) {
                    setCheck(flash.paymentCheck as PaymentCheck);
                }
            }),
        [],
    );

    return (
        <div className="space-y-4">
            <Card
                title="Payment gateway"
                description="Choose how organizations pay for their plan."
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    {[
                        {
                            value: 'stripe',
                            title: 'Stripe',
                            text: 'Card payments and subscriptions through Stripe Checkout and the customer portal.',
                        },
                        {
                            value: 'fake',
                            title: 'Test mode',
                            text: 'No gateway. Plan changes apply instantly and nobody is charged.',
                        },
                    ].map((option) => (
                        <label
                            key={option.value}
                            className={`flex cursor-pointer gap-3 rounded-lg border p-4 ${
                                form.driver === option.value
                                    ? 'border-primary ring-primary/30 ring-2'
                                    : ''
                            }`}
                        >
                            <input
                                type="radio"
                                name="payment-driver"
                                value={option.value}
                                checked={form.driver === option.value}
                                data-test={`driver-${option.value}`}
                                onChange={() =>
                                    setForm({ ...form, driver: option.value })
                                }
                                className="mt-1"
                            />
                            <span>
                                <span className="block font-medium">
                                    {option.title}
                                </span>
                                <span className="text-muted-foreground text-sm">
                                    {option.text}
                                </span>
                            </span>
                        </label>
                    ))}
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        id="currency"
                        label="Currency"
                        error={errors.currency}
                    >
                        <Input
                            id="currency"
                            value={form.currency}
                            maxLength={3}
                            className="uppercase"
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    currency: event.target.value.toUpperCase(),
                                })
                            }
                        />
                    </Field>
                    <Field
                        id="trial-days"
                        label="Free trial (days)"
                        error={errors.trial_days}
                        hint="Applied to new organizations."
                    >
                        <Input
                            id="trial-days"
                            type="number"
                            min={0}
                            max={365}
                            value={form.trial_days}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    trial_days: Number(event.target.value),
                                })
                            }
                        />
                    </Field>
                </div>
            </Card>

            {stripe && (
                <Card
                    title="Stripe keys"
                    description="From the Stripe dashboard → Developers → API keys. Secret fields are write-only: leave them blank to keep the saved value."
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field
                            id="stripe-key"
                            label="Publishable key"
                            error={errors.stripe_key}
                        >
                            <Input
                                id="stripe-key"
                                value={form.stripe_key}
                                placeholder="pk_live_…"
                                className="font-mono text-xs"
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        stripe_key: event.target.value.trim(),
                                    })
                                }
                            />
                        </Field>
                        <Field
                            id="stripe-secret"
                            label="Secret key"
                            error={errors.stripe_secret}
                            hint={
                                payments.has_secret
                                    ? `Saved (${payments.secret_hint ?? 'hidden'}).`
                                    : 'Not set yet.'
                            }
                        >
                            <Input
                                id="stripe-secret"
                                type="password"
                                autoComplete="off"
                                value={form.stripe_secret}
                                placeholder={
                                    payments.has_secret
                                        ? 'Leave blank to keep'
                                        : 'sk_live_…'
                                }
                                className="font-mono text-xs"
                                data-test="stripe-secret"
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        stripe_secret:
                                            event.target.value.trim(),
                                    })
                                }
                            />
                        </Field>
                        <Field
                            id="stripe-webhook"
                            label="Webhook signing secret"
                            error={errors.stripe_webhook_secret}
                            hint={
                                payments.has_webhook_secret
                                    ? 'Saved.'
                                    : 'Not set yet.'
                            }
                        >
                            <Input
                                id="stripe-webhook"
                                type="password"
                                autoComplete="off"
                                value={form.stripe_webhook_secret}
                                placeholder={
                                    payments.has_webhook_secret
                                        ? 'Leave blank to keep'
                                        : 'whsec_…'
                                }
                                className="font-mono text-xs"
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        stripe_webhook_secret:
                                            event.target.value.trim(),
                                    })
                                }
                            />
                        </Field>
                        <div className="grid gap-2">
                            <Label>Webhook endpoint</Label>
                            <div className="flex gap-2">
                                <Input
                                    readOnly
                                    value={payments.webhook_url}
                                    className="font-mono text-xs"
                                    aria-label="Webhook endpoint"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    aria-label="Copy webhook endpoint"
                                    onClick={() =>
                                        void navigator.clipboard?.writeText(
                                            payments.webhook_url,
                                        )
                                    }
                                >
                                    <Copy className="size-4" />
                                </Button>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Add this in Stripe → Developers → Webhooks with
                                the checkout.session.completed,
                                customer.subscription.* and invoice.* events.
                            </p>
                        </div>
                    </div>
                </Card>
            )}

            <Card
                title="Plans and prices"
                description={
                    stripe
                        ? 'Wire each paid plan to its recurring Stripe price. Checkout uses this price, and Verify confirms the amount and currency match.'
                        : 'Stripe prices are used once the Stripe gateway is on.'
                }
            >
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[560px] text-left text-sm">
                        <thead className="text-muted-foreground border-b text-xs tracking-wide uppercase">
                            <tr>
                                <th className="py-2 pr-3 font-medium">Plan</th>
                                <th className="px-3 py-2 font-medium">Price</th>
                                <th className="px-3 py-2 font-medium">
                                    Stripe price ID
                                </th>
                                {check && (
                                    <th className="px-3 py-2 font-medium">
                                        Check
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {payments.plans.map((plan) => {
                                const result = check?.plans[plan.key];

                                return (
                                    <tr key={plan.key}>
                                        <td className="py-2.5 pr-3 font-medium">
                                            {plan.name}
                                        </td>
                                        <td className="px-3 py-2.5 tabular-nums">
                                            {plan.price_cents === null
                                                ? 'Custom'
                                                : `${(plan.price_cents / 100).toFixed(2)} ${form.currency}`}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <Input
                                                value={prices[plan.key] ?? ''}
                                                disabled={!plan.paid}
                                                placeholder={
                                                    plan.paid
                                                        ? 'price_…'
                                                        : 'Not needed'
                                                }
                                                className="h-8 font-mono text-xs"
                                                aria-label={`${plan.name} Stripe price ID`}
                                                data-test={`price-${plan.key}`}
                                                onChange={(event) =>
                                                    setPrices({
                                                        ...prices,
                                                        [plan.key]:
                                                            event.target.value.trim(),
                                                    })
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors[`prices.${plan.key}`]
                                                }
                                            />
                                        </td>
                                        {check && (
                                            <td
                                                className={`px-3 py-2.5 text-xs ${result?.ok ? 'text-emerald-600' : 'text-amber-600'}`}
                                            >
                                                {result?.message ?? '—'}
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                {check && (
                    <p className="text-muted-foreground text-xs">
                        Connected to {check.account} (
                        {check.livemode ? 'live' : 'test'} mode).
                    </p>
                )}
            </Card>

            <div className="flex flex-wrap gap-2">
                <Button
                    data-test="save-payments"
                    onClick={() =>
                        router.put(
                            '/platform/settings/payments',
                            {
                                ...form,
                                prices: Object.fromEntries(
                                    Object.entries(prices).map(
                                        ([key, value]) => [
                                            key,
                                            value === '' ? null : value,
                                        ],
                                    ),
                                ),
                            },
                            {
                                ...options(setErrors),
                                onSuccess: () => {
                                    setErrors({});
                                    setForm((current) => ({
                                        ...current,
                                        stripe_secret: '',
                                        stripe_webhook_secret: '',
                                    }));
                                },
                            },
                        )
                    }
                >
                    Save payment settings
                </Button>
                {stripe && (
                    <Button
                        variant="outline"
                        disabled={testing || !payments.has_secret}
                        title={
                            payments.has_secret
                                ? undefined
                                : 'Save a secret key first'
                        }
                        onClick={() => {
                            setTesting(true);
                            router.post(
                                '/platform/settings/payments/test',
                                {},
                                {
                                    preserveScroll: true,
                                    preserveState: true,
                                    onFinish: () => setTesting(false),
                                },
                            );
                        }}
                    >
                        Verify Stripe and prices
                    </Button>
                )}
            </div>
        </div>
    );
}

function MailTab({ mail }: { mail: Props['mail'] }) {
    const [form, setForm] = useState({
        mailer: mail.mailer,
        host: mail.host ?? '',
        port: mail.port ?? 587,
        username: mail.username ?? '',
        password: '',
        encryption: mail.encryption,
        from_address: mail.from_address ?? '',
        from_name: mail.from_name ?? '',
    });
    const [errors, setErrors] = useState<Errors>({});
    const [sending, setSending] = useState(false);
    const smtp = form.mailer === 'smtp';

    return (
        <Card
            title="Outgoing email"
            description="Used for invitations, booking confirmations, password resets and alerts. Save before sending a test."
        >
            <div className="grid gap-3 sm:grid-cols-2">
                {[
                    {
                        value: 'smtp' as const,
                        title: 'SMTP server',
                        text: 'Send through your mail provider (Microsoft 365, Google Workspace, SendGrid, Mailgun, SES…).',
                    },
                    {
                        value: 'log' as const,
                        title: 'Log only',
                        text: 'Write emails to the application log. For development; nobody receives them.',
                    },
                ].map((option) => (
                    <label
                        key={option.value}
                        className={`flex cursor-pointer gap-3 rounded-lg border p-4 ${
                            form.mailer === option.value
                                ? 'border-primary ring-primary/30 ring-2'
                                : ''
                        }`}
                    >
                        <input
                            type="radio"
                            name="mailer"
                            value={option.value}
                            checked={form.mailer === option.value}
                            onChange={() =>
                                setForm({ ...form, mailer: option.value })
                            }
                            className="mt-1"
                        />
                        <span>
                            <span className="block font-medium">
                                {option.title}
                            </span>
                            <span className="text-muted-foreground text-sm">
                                {option.text}
                            </span>
                        </span>
                    </label>
                ))}
            </div>

            {smtp && (
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="md:col-span-2">
                        <Field id="smtp-host" label="Host" error={errors.host}>
                            <Input
                                id="smtp-host"
                                data-test="smtp-host"
                                value={form.host}
                                placeholder="smtp.office365.com"
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        host: event.target.value.trim(),
                                    })
                                }
                            />
                        </Field>
                    </div>
                    <Field id="smtp-port" label="Port" error={errors.port}>
                        <Input
                            id="smtp-port"
                            type="number"
                            value={form.port}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    port: Number(event.target.value),
                                })
                            }
                        />
                    </Field>
                    <Field
                        id="smtp-username"
                        label="Username"
                        error={errors.username}
                    >
                        <Input
                            id="smtp-username"
                            autoComplete="off"
                            value={form.username}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    username: event.target.value,
                                })
                            }
                        />
                    </Field>
                    <Field
                        id="smtp-password"
                        label="Password"
                        error={errors.password}
                        hint={
                            mail.has_password
                                ? 'Saved. Leave blank to keep it.'
                                : undefined
                        }
                    >
                        <Input
                            id="smtp-password"
                            type="password"
                            autoComplete="new-password"
                            value={form.password}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    password: event.target.value,
                                })
                            }
                        />
                    </Field>
                    <Field
                        id="smtp-encryption"
                        label="Encryption"
                        error={errors.encryption}
                    >
                        <select
                            id="smtp-encryption"
                            value={form.encryption}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    encryption: event.target
                                        .value as typeof form.encryption,
                                })
                            }
                            className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        >
                            <option value="tls">STARTTLS (port 587)</option>
                            <option value="ssl">SSL/TLS (port 465)</option>
                            <option value="none">None</option>
                        </select>
                    </Field>
                </div>
            )}

            <div className="grid gap-4 md:grid-cols-2">
                <Field
                    id="from-address"
                    label="From address"
                    error={errors.from_address}
                >
                    <Input
                        id="from-address"
                        type="email"
                        value={form.from_address}
                        placeholder="no-reply@example.com"
                        onChange={(event) =>
                            setForm({
                                ...form,
                                from_address: event.target.value.trim(),
                            })
                        }
                    />
                </Field>
                <Field
                    id="from-name"
                    label="From name"
                    error={errors.from_name}
                >
                    <Input
                        id="from-name"
                        value={form.from_name}
                        onChange={(event) =>
                            setForm({ ...form, from_name: event.target.value })
                        }
                    />
                </Field>
            </div>

            <div className="flex flex-wrap gap-2">
                <Button
                    data-test="save-mail"
                    onClick={() =>
                        router.put('/platform/settings/mail', form, {
                            ...options(setErrors),
                            onSuccess: () => {
                                setErrors({});
                                setForm((current) => ({
                                    ...current,
                                    password: '',
                                }));
                            },
                        })
                    }
                >
                    <Mail className="size-4" />
                    Save email settings
                </Button>
                <Button
                    variant="outline"
                    disabled={sending}
                    onClick={() => {
                        setSending(true);
                        router.post(
                            '/platform/settings/mail/test',
                            {},
                            {
                                preserveScroll: true,
                                preserveState: true,
                                onFinish: () => setSending(false),
                            },
                        );
                    }}
                >
                    <Send className="size-4" />
                    Send test email to me
                </Button>
            </div>
        </Card>
    );
}
