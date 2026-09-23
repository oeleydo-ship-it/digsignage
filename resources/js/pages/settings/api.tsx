import { Form, Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    ApiScopeOption,
    PartnerApiToken,
    PartnerWebhookDelivery,
    PartnerWebhookEndpoint,
} from '@/types';

type Props = {
    tokens: PartnerApiToken[];
    endpoints: PartnerWebhookEndpoint[];
    deliveries: PartnerWebhookDelivery[];
    scopes: ApiScopeOption[];
    events: ApiScopeOption[];
    openapi_url: string;
    plain_token?: string;
    plain_secret?: string;
};

export default function ApiSettings({
    tokens,
    endpoints,
    deliveries,
    scopes,
    events,
    openapi_url,
    plain_token,
    plain_secret,
}: Props) {
    const slug = usePage().props.currentTeam?.slug;

    return (
        <>
            <Head title="API" />

            <h1 className="sr-only">API and webhooks</h1>

            <div className="space-y-10">
                <Heading
                    variant="small"
                    title="Partner API"
                    description="Issue scoped tokens, subscribe to webhooks, and open the versioned REST documentation."
                />

                <p className="text-sm text-muted-foreground">
                    Base URL{' '}
                    <code className="rounded bg-muted px-1 py-0.5">/api/v1</code>
                    . Documentation:{' '}
                    <a
                        className="underline"
                        href={openapi_url}
                        target="_blank"
                        rel="noreferrer"
                    >
                        OpenAPI
                    </a>
                    . Widget defaults live on{' '}
                    {slug ? (
                        <a className="underline" href={`/${slug}/apps`}>
                            Workspace → Apps
                        </a>
                    ) : (
                        'Workspace → Apps'
                    )}
                    .
                </p>

                {plain_token && (
                    <div className="rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                        Copy this token now. It will not be shown again.
                        <code className="mt-2 block break-all rounded bg-background p-2">
                            {plain_token}
                        </code>
                    </div>
                )}

                {plain_secret && (
                    <div className="rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                        Copy this webhook signing secret now.
                        <code className="mt-2 block break-all rounded bg-background p-2">
                            {plain_secret}
                        </code>
                    </div>
                )}

                <Form
                    action="/settings/api/tokens"
                    method="post"
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="token-name">Token name</Label>
                                <Input
                                    id="token-name"
                                    name="name"
                                    required
                                    placeholder="Inventory sync"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <fieldset className="space-y-2">
                                <legend className="text-sm font-medium">
                                    Scopes
                                </legend>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {scopes.map((scope) => (
                                        <label
                                            key={scope.value}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                name="scopes[]"
                                                value={scope.value}
                                                className="size-4 rounded border"
                                            />
                                            {scope.label}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.scopes} />
                            </fieldset>

                            <Button type="submit" disabled={processing}>
                                Create token
                            </Button>
                        </>
                    )}
                </Form>

                <div className="space-y-3">
                    {tokens.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No API tokens yet.
                        </p>
                    )}
                    {tokens.map((token) => (
                        <div
                            key={token.id}
                            className="flex items-start justify-between gap-4 rounded-lg border p-3"
                        >
                            <div>
                                <p className="font-medium">{token.name}</p>
                                <p className="text-xs text-muted-foreground">
                                    …{token.token_prefix} ·{' '}
                                    {token.scopes.join(', ')}
                                </p>
                            </div>
                            <div className="flex shrink-0 gap-1">
                                <Form
                                    action={`/settings/api/tokens/${token.id}/rotate`}
                                    method="post"
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="ghost"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Rotate
                                        </Button>
                                    )}
                                </Form>
                                <Form
                                    action={`/settings/api/tokens/${token.id}`}
                                    method="delete"
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="ghost"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Revoke
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        </div>
                    ))}
                </div>

                <Heading
                    variant="small"
                    title="Webhooks"
                    description="HMAC SHA-256 of the raw JSON body is sent as X-DigSignage-Signature."
                />

                <Form
                    action="/settings/api/webhooks"
                    method="post"
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="webhook-url">Endpoint URL</Label>
                                <Input
                                    id="webhook-url"
                                    name="url"
                                    type="url"
                                    required
                                    placeholder="https://example.com/webhooks/signage"
                                />
                                <InputError message={errors.url} />
                            </div>

                            <fieldset className="space-y-2">
                                <legend className="text-sm font-medium">
                                    Events
                                </legend>
                                <div className="grid gap-2">
                                    {events.map((event) => (
                                        <label
                                            key={event.value}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                name="events[]"
                                                value={event.value}
                                                className="size-4 rounded border"
                                            />
                                            {event.label}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.events} />
                            </fieldset>

                            <Button type="submit" disabled={processing}>
                                Add webhook
                            </Button>
                        </>
                    )}
                </Form>

                <div className="space-y-3">
                    {endpoints.map((endpoint) => (
                        <div
                            key={endpoint.id}
                            className="flex items-start justify-between gap-4 rounded-lg border p-3"
                        >
                            <div>
                                <p className="break-all text-sm">
                                    {endpoint.url}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {endpoint.is_active ? 'Active' : 'Paused'} ·{' '}
                                    {endpoint.events.join(', ')}
                                </p>
                            </div>
                            <div className="flex shrink-0 gap-1">
                                <Form
                                    action={`/settings/api/webhooks/${endpoint.id}/rotate`}
                                    method="post"
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="ghost"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Rotate secret
                                        </Button>
                                    )}
                                </Form>
                                <Form
                                    action={`/settings/api/webhooks/${endpoint.id}`}
                                    method="delete"
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="ghost"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Remove
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        </div>
                    ))}
                </div>

                {deliveries.length > 0 && (
                    <div className="space-y-2">
                        <h2 className="text-sm font-medium">Recent deliveries</h2>
                        <ul className="space-y-1 text-xs text-muted-foreground">
                            {deliveries.map((delivery) => (
                                <li key={delivery.id}>
                                    {delivery.event} · {delivery.status}
                                    {delivery.response_code
                                        ? ` · ${delivery.response_code}`
                                        : ''}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </>
    );
}

ApiSettings.layout = {
    breadcrumbs: [
        {
            title: 'API',
            href: '/settings/api',
        },
    ],
};
