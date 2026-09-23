import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type AppField = {
    name: string;
    type: string;
    label: string;
    help?: string | null;
    options?: Array<{ value: string; label: string }>;
};

type ContentApp = {
    key: string;
    label: string;
    description: string;
    widgets: string[];
    fields: AppField[];
    enabled: boolean;
    connected: boolean;
    values: Record<string, string | number | boolean | null>;
};

type Props = {
    apps: ContentApp[];
    canManage: boolean;
};

export default function ContentAppsSettings({ apps, canManage }: Props) {
    const slug = usePage().props.currentTeam?.slug ?? '';
    const [rows, setRows] = useState(apps);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const update = (
        key: string,
        field: string,
        value: string | number | boolean | null,
    ) => {
        setRows((current) =>
            current.map((app) => {
                if (app.key !== key) {
                    return app;
                }

                if (field === 'enabled') {
                    return { ...app, enabled: Boolean(value) };
                }

                return {
                    ...app,
                    values: { ...app.values, [field]: value },
                };
            }),
        );
    };

    const save = () => {
        const payload: Record<
            string,
            Record<string, string | number | boolean | null>
        > = {};

        for (const app of rows) {
            payload[app.key] = {
                enabled: app.enabled,
                ...app.values,
            };
        }

        setSaving(true);
        setErrors({});

        router.patch(
            `/${slug}/apps`,
            { apps: payload },
            {
                preserveScroll: true,
                headers: {
                    'X-Stay-On-Page': window.location.href,
                },
                onError: (next) => setErrors(next),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Head title="Apps" />

            <h1 className="sr-only">Apps and integrations</h1>

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        variant="small"
                        title="Apps"
                        description="Connect first-party data sources used by widgets. Empty widget fields fall back to these organization defaults."
                    />
                    {canManage && (
                        <Button
                            onClick={save}
                            disabled={saving}
                            data-testid="apps-save"
                        >
                            Save apps
                        </Button>
                    )}
                </div>

                <p className="text-muted-foreground text-sm">
                    Partner REST tokens and outbound webhooks stay on{' '}
                    <a className="underline" href="/settings/api">
                        Settings → API
                    </a>
                    .
                </p>

                <div className="space-y-4">
                    {rows.map((app) => (
                        <section
                            key={app.key}
                            className="space-y-4 rounded-xl border p-5"
                            data-testid={`app-card-${app.key}`}
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h2 className="text-sm font-semibold">
                                            {app.label}
                                        </h2>
                                        <Badge
                                            variant={
                                                app.enabled && app.connected
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {app.enabled && app.connected
                                                ? 'Connected'
                                                : app.enabled
                                                  ? 'Needs setup'
                                                  : 'Off'}
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {app.description}
                                    </p>
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={app.enabled}
                                        disabled={!canManage}
                                        onCheckedChange={(checked) =>
                                            update(
                                                app.key,
                                                'enabled',
                                                checked === true,
                                            )
                                        }
                                        data-testid={`app-enabled-${app.key}`}
                                    />
                                    Enable
                                </label>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                {app.fields.map((field) => {
                                    const errorKey = `apps.${app.key}.${field.name}`;
                                    const value = app.values[field.name] ?? '';
                                    const id = `${app.key}-${field.name}`;

                                    return (
                                        <div
                                            key={field.name}
                                            className={
                                                field.type === 'textarea'
                                                    ? 'md:col-span-2'
                                                    : undefined
                                            }
                                        >
                                            <Label htmlFor={id}>
                                                {field.label}
                                            </Label>
                                            {field.type === 'select' ? (
                                                <select
                                                    id={id}
                                                    className="border-input mt-1 h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                                                    value={String(value ?? '')}
                                                    disabled={!canManage}
                                                    onChange={(event) =>
                                                        update(
                                                            app.key,
                                                            field.name,
                                                            event.target.value,
                                                        )
                                                    }
                                                >
                                                    {(field.options ?? []).map(
                                                        (option) => (
                                                            <option
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            ) : field.type === 'textarea' ? (
                                                <textarea
                                                    id={id}
                                                    className="border-input mt-1 min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                                                    value={String(value ?? '')}
                                                    disabled={!canManage}
                                                    onChange={(event) =>
                                                        update(
                                                            app.key,
                                                            field.name,
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            ) : (
                                                <Input
                                                    id={id}
                                                    className="mt-1"
                                                    type={
                                                        field.type ===
                                                        'password'
                                                            ? 'password'
                                                            : field.type ===
                                                                'number'
                                                              ? 'number'
                                                              : 'text'
                                                    }
                                                    value={
                                                        field.type ===
                                                        'password'
                                                            ? String(
                                                                  value ?? '',
                                                              )
                                                            : String(
                                                                  value ?? '',
                                                              )
                                                    }
                                                    placeholder={
                                                        field.type ===
                                                            'password' &&
                                                        app.values.has_token
                                                            ? 'Token saved — leave blank to keep'
                                                            : undefined
                                                    }
                                                    disabled={!canManage}
                                                    data-testid={`app-field-${app.key}-${field.name}`}
                                                    onChange={(event) =>
                                                        update(
                                                            app.key,
                                                            field.name,
                                                            field.type ===
                                                                'number'
                                                                ? event.target
                                                                      .value
                                                                : event.target
                                                                      .value,
                                                        )
                                                    }
                                                />
                                            )}
                                            {field.help && (
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    {field.help}
                                                </p>
                                            )}
                                            <InputError
                                                message={errors[errorKey]}
                                                className="mt-1"
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                        </section>
                    ))}
                </div>
            </div>
        </>
    );
}

ContentAppsSettings.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Settings',
            href: '/settings/profile',
        },
        {
            title: 'Apps',
            href: props.currentTeam ? `/${props.currentTeam.slug}/apps` : '/',
        },
    ],
});
