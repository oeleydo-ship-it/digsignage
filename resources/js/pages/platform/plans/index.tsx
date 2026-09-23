import { Form, Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Plan = {
    key: string;
    name: string;
    screens: number | null;
    storage_gb: number | null;
    users: number | null;
    bandwidth_gb: number | null;
    price_cents: number | null;
    price_usd: number | null;
    organizations: number;
    features: Record<string, boolean>;
};

type FeatureOption = { value: string; label: string };

type Props = { plans: Plan[]; features: FeatureOption[]; currency: string };

function dollarsFromPlan(plan: Plan): number | null {
    if (typeof plan.price_usd === 'number' && !Number.isNaN(plan.price_usd)) {
        return plan.price_usd;
    }

    if (typeof plan.price_cents === 'number' && !Number.isNaN(plan.price_cents)) {
        return plan.price_cents / 100;
    }

    return null;
}

function formatUsd(amount: number | null, currency: string): string {
    if (amount === null || Number.isNaN(amount)) {
        return 'Custom';
    }

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(amount);
}

function slugFromName(name: string): string {
    return name
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function PlatformPlans({
    plans,
    features,
    currency = 'USD',
}: Props) {
    return (
        <>
            <Head title="Plans" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Plans"
                        description="Set monthly USD pricing, quotas, and feature gates. Leave a quota empty for unlimited. Changes apply to every organization on that plan."
                    />
                    <CreatePlanDialog features={features} currency={currency} />
                </div>
                <div className="grid gap-4 xl:grid-cols-3">
                    {plans.map((plan) => {
                        const priceUsd = dollarsFromPlan(plan);

                        return (
                            <Form
                                key={plan.key}
                                action={`/platform/plans/${plan.key}`}
                                method="patch"
                                options={{ preserveScroll: true }}
                                className="bg-card flex flex-col gap-5 rounded-xl border p-5 shadow-sm"
                            >
                                {({ processing }) => (
                                    <>
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <h2 className="text-lg font-semibold">
                                                    {plan.name}
                                                </h2>
                                                <p className="text-muted-foreground text-sm">
                                                    {plan.key}
                                                </p>
                                            </div>
                                            <Badge variant="outline">
                                                {plan.organizations}{' '}
                                                {plan.organizations === 1
                                                    ? 'org'
                                                    : 'orgs'}
                                            </Badge>
                                        </div>

                                        <div>
                                            <p className="text-3xl font-semibold tracking-tight">
                                                {formatUsd(priceUsd, currency)}
                                            </p>
                                            <p className="text-muted-foreground text-sm">
                                                {priceUsd === null
                                                    ? 'Contact sales / custom quote'
                                                    : `${currency} per organization / month`}
                                            </p>
                                        </div>

                                        <PlanFields
                                            idPrefix={plan.key}
                                            name={plan.name}
                                            priceUsd={priceUsd}
                                            currency={currency}
                                            screens={plan.screens}
                                            users={plan.users}
                                            storageGb={plan.storage_gb}
                                            bandwidthGb={plan.bandwidth_gb}
                                            features={features}
                                            featureValues={plan.features}
                                        />

                                        <Button
                                            type="submit"
                                            className="mt-auto"
                                            disabled={processing}
                                        >
                                            Save {plan.name}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

function CreatePlanDialog({
    features,
    currency,
}: {
    features: FeatureOption[];
    currency: string;
}) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [slug, setSlug] = useState('');
    const [slugTouched, setSlugTouched] = useState(false);

    const derivedSlug = useMemo(() => slugFromName(name), [name]);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) {
                    setName('');
                    setSlug('');
                    setSlugTouched(false);
                }
            }}
        >
            <DialogTrigger asChild>
                <Button data-test="add-plan">Add plan</Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <Form
                    key={String(open)}
                    action="/platform/plans"
                    method="post"
                    options={{ preserveScroll: true }}
                    className="space-y-5"
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Add plan</DialogTitle>
                                <DialogDescription>
                                    Create a catalog plan with USD pricing,
                                    quotas, and feature gates. Leave price empty
                                    for a custom quote.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="create-name">Display name</Label>
                                <Input
                                    id="create-name"
                                    name="name"
                                    data-test="create-plan-name"
                                    value={name}
                                    onChange={(event) =>
                                        setName(event.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="create-slug">Slug</Label>
                                <Input
                                    id="create-slug"
                                    name="slug"
                                    data-test="create-plan-slug"
                                    value={slugTouched ? slug : derivedSlug}
                                    onChange={(event) => {
                                        setSlugTouched(true);
                                        setSlug(event.target.value);
                                    }}
                                    placeholder="auto from name"
                                />
                                <InputError message={errors.key ?? errors.slug} />
                            </div>

                            <PlanFields
                                idPrefix="create"
                                currency={currency}
                                features={features}
                                featureValues={{}}
                                defaultFeaturesChecked
                            />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    data-test="create-plan-submit"
                                    disabled={processing}
                                >
                                    Create plan
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function PlanFields({
    idPrefix,
    name,
    priceUsd,
    currency,
    screens,
    users,
    storageGb,
    bandwidthGb,
    features,
    featureValues,
    defaultFeaturesChecked = false,
}: {
    idPrefix: string;
    name?: string;
    priceUsd?: number | null;
    currency: string;
    screens?: number | null;
    users?: number | null;
    storageGb?: number | null;
    bandwidthGb?: number | null;
    features: FeatureOption[];
    featureValues: Record<string, boolean>;
    defaultFeaturesChecked?: boolean;
}) {
    return (
        <>
            {name !== undefined && (
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-name`}>Display name</Label>
                    <Input
                        id={`${idPrefix}-name`}
                        name="name"
                        defaultValue={name}
                        required
                    />
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-price`}>
                    Monthly price ({currency})
                </Label>
                <div className="relative">
                    <span className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm">
                        $
                    </span>
                    <Input
                        id={`${idPrefix}-price`}
                        name="price_usd"
                        type="number"
                        min={0}
                        step="0.01"
                        className="pl-7"
                        defaultValue={priceUsd ?? ''}
                        placeholder="Custom"
                        data-test={`${idPrefix}-price`}
                    />
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
                <QuotaField
                    id={`${idPrefix}-screens`}
                    name="screens"
                    label="Screens"
                    value={screens ?? null}
                />
                <QuotaField
                    id={`${idPrefix}-users`}
                    name="users"
                    label="Users"
                    value={users ?? null}
                />
                <QuotaField
                    id={`${idPrefix}-storage`}
                    name="storage_gb"
                    label="Storage (GB)"
                    value={storageGb ?? null}
                />
                <QuotaField
                    id={`${idPrefix}-bandwidth`}
                    name="bandwidth_gb"
                    label="Bandwidth (GB)"
                    value={bandwidthGb ?? null}
                />
            </div>

            <fieldset className="space-y-2">
                <legend className="text-sm font-medium">
                    Included features
                </legend>
                <div className="grid gap-2 sm:grid-cols-2">
                    {features.map((feature) => (
                        <label
                            key={feature.value}
                            className="hover:bg-muted/50 flex items-center gap-2 rounded-md border px-2 py-1.5 text-sm"
                        >
                            <input
                                type="hidden"
                                name={`features[${feature.value}]`}
                                value="0"
                            />
                            <input
                                type="checkbox"
                                name={`features[${feature.value}]`}
                                value="1"
                                defaultChecked={
                                    featureValues[feature.value] === true ||
                                    (defaultFeaturesChecked &&
                                        featureValues[feature.value] !== false)
                                }
                                className="size-4 rounded border"
                            />
                            {feature.label}
                        </label>
                    ))}
                </div>
            </fieldset>
        </>
    );
}

function QuotaField({
    id,
    name,
    label,
    value,
}: {
    id: string;
    name: string;
    label: string;
    value: number | null;
}) {
    return (
        <div className="grid gap-1">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                name={name}
                type="number"
                min={0}
                defaultValue={value ?? ''}
                placeholder="Unlimited"
                data-test={id}
            />
        </div>
    );
}

PlatformPlans.layout = () => ({
    breadcrumbs: [
        { title: 'Platform', href: '/platform' },
        { title: 'Plans', href: '/platform/plans' },
    ],
});
