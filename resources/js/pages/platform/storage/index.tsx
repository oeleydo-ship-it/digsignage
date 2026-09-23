import { Form, Head, router } from '@inertiajs/react';
import { type ComponentProps, useMemo, useState } from 'react';
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
import type {
    StorageDisk,
    StorageOrganization,
    StorageProviderOption,
} from '@/types/storage';

type Props = {
    disks: StorageDisk[];
    providers: StorageProviderOption[];
    organizations: StorageOrganization[];
    fallbackDisk: string;
    unassignedBytes: number;
};

function formatBytes(bytes: number): string {
    if (!bytes) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    const exponent = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );
    const value = bytes / 1024 ** exponent;

    return `${value.toFixed(value >= 10 || exponent === 0 ? 0 : 1)} ${units[exponent]}`;
}

export default function PlatformStorage({
    disks,
    providers,
    organizations,
    fallbackDisk,
    unassignedBytes,
}: Props) {
    const defaultDisk = disks.find((disk) => disk.is_default) ?? null;

    return (
        <>
            <Head title="Storage" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Storage"
                        description="Configure where uploaded media is stored. Add S3, Wasabi, DigitalOcean Spaces, Cloudflare R2, Backblaze B2, MinIO or a server directory, then assign backends to organizations."
                    />
                    <StorageDiskDialog
                        providers={providers}
                        organizations={organizations}
                    />
                </div>

                {!defaultDisk && (
                    <div className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                        No platform default is set, so uploads fall back to the{' '}
                        <code className="font-mono">{fallbackDisk}</code> disk
                        from your environment configuration. Add a backend and
                        mark it as the default to take over.
                    </div>
                )}

                <div className="grid gap-4 xl:grid-cols-2">
                    {disks.map((disk) => (
                        <DiskCard
                            key={disk.id}
                            disk={disk}
                            providers={providers}
                            organizations={organizations}
                        />
                    ))}
                    {disks.length === 0 && (
                        <p className="text-muted-foreground text-sm">
                            No storage backends configured yet.
                        </p>
                    )}
                </div>

                <OrganizationAssignments
                    disks={disks}
                    organizations={organizations}
                    defaultDisk={defaultDisk}
                    unassignedBytes={unassignedBytes}
                />
            </div>
        </>
    );
}

function DiskCard({
    disk,
    providers,
    organizations,
}: {
    disk: StorageDisk;
    providers: StorageProviderOption[];
    organizations: StorageOrganization[];
}) {
    const [confirming, setConfirming] = useState(false);

    return (
        <div className="bg-card flex flex-col gap-4 rounded-xl border p-5 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="truncate text-lg font-semibold">
                        {disk.name}
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        {[disk.provider_label, disk.bucket, disk.region]
                            .filter(Boolean)
                            .join(' / ')}
                    </p>
                </div>
                <div className="flex shrink-0 flex-wrap justify-end gap-1">
                    {disk.is_default && <Badge>Default</Badge>}
                    {!disk.is_active && (
                        <Badge variant="outline">Disabled</Badge>
                    )}
                    {disk.team_name && (
                        <Badge variant="outline">{disk.team_name} only</Badge>
                    )}
                </div>
            </div>

            <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <Detail label="Stored" value={formatBytes(disk.stored_bytes)} />
                <Detail
                    label="Organizations"
                    value={String(disk.assigned_teams_count)}
                />
                {disk.endpoint && (
                    <Detail
                        label="Endpoint"
                        value={disk.endpoint}
                        className="col-span-2 truncate"
                    />
                )}
                {disk.access_key && (
                    <Detail label="Access key" value={disk.access_key} />
                )}
                <Detail label="Visibility" value={disk.visibility} />
            </dl>

            {disk.last_tested_at && (
                <p
                    className={
                        disk.last_test_error
                            ? 'text-destructive text-sm'
                            : 'text-muted-foreground text-sm'
                    }
                >
                    {disk.last_test_error
                        ? `Last test failed: ${disk.last_test_error}`
                        : `Connection verified ${new Date(disk.last_tested_at).toLocaleString()}`}
                </p>
            )}

            <div className="mt-auto flex flex-wrap gap-2">
                <StorageDiskDialog
                    providers={providers}
                    organizations={organizations}
                    disk={disk}
                />
                <Button
                    variant="secondary"
                    onClick={() =>
                        router.post(
                            `/platform/storage/${disk.id}/test`,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Test connection
                </Button>
                {confirming ? (
                    <>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.delete(`/platform/storage/${disk.id}`, {
                                    preserveScroll: true,
                                    onFinish: () => setConfirming(false),
                                })
                            }
                        >
                            Confirm delete
                        </Button>
                        <Button
                            variant="ghost"
                            onClick={() => setConfirming(false)}
                        >
                            Cancel
                        </Button>
                    </>
                ) : (
                    <Button
                        variant="ghost"
                        className="text-destructive"
                        onClick={() => setConfirming(true)}
                    >
                        Delete
                    </Button>
                )}
            </div>
        </div>
    );
}

function Detail({
    label,
    value,
    className = '',
}: {
    label: string;
    value: string;
    className?: string;
}) {
    return (
        <div className={className}>
            <dt className="text-muted-foreground text-xs uppercase">{label}</dt>
            <dd className="font-medium">{value}</dd>
        </div>
    );
}

function StorageDiskDialog({
    providers,
    organizations,
    disk,
}: {
    providers: StorageProviderOption[];
    organizations: StorageOrganization[];
    disk?: StorageDisk;
}) {
    const [open, setOpen] = useState(false);
    const [provider, setProvider] = useState(
        disk?.provider ?? providers[0]?.value ?? 'local',
    );

    const selected = useMemo(
        () => providers.find((option) => option.value === provider) ?? null,
        [providers, provider],
    );
    const isS3 = selected?.driver === 's3';
    const editing = disk !== undefined;
    const idSuffix = disk?.id ?? 'new';

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) {
                    setProvider(
                        disk?.provider ?? providers[0]?.value ?? 'local',
                    );
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    variant={editing ? 'secondary' : 'default'}
                    data-test={editing ? `edit-disk-${disk.id}` : 'add-disk'}
                >
                    {editing ? 'Edit' : 'Add storage backend'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <Form
                    key={String(open)}
                    action={
                        editing
                            ? `/platform/storage/${disk.id}`
                            : '/platform/storage'
                    }
                    method={editing ? 'patch' : 'post'}
                    options={{ preserveScroll: true }}
                    className="space-y-5"
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {editing
                                        ? `Edit ${disk.name}`
                                        : 'Add storage backend'}
                                </DialogTitle>
                                <DialogDescription>
                                    Credentials are encrypted at rest. Test the
                                    connection before assigning organizations to
                                    it.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor={`name-${idSuffix}`}>
                                    Display name
                                </Label>
                                <Input
                                    id={`name-${idSuffix}`}
                                    name="name"
                                    data-test="disk-name"
                                    defaultValue={disk?.name}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`provider-${idSuffix}`}>
                                    Provider
                                </Label>
                                <select
                                    id={`provider-${idSuffix}`}
                                    name="provider"
                                    data-test="disk-provider"
                                    className="bg-background w-full rounded-md border p-2"
                                    value={provider}
                                    onChange={(event) =>
                                        setProvider(event.target.value)
                                    }
                                >
                                    {providers.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.provider} />
                            </div>

                            {isS3 ? (
                                <>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <Field
                                            id={`bucket-${idSuffix}`}
                                            name="bucket"
                                            label="Bucket"
                                            defaultValue={disk?.bucket ?? ''}
                                            error={errors.bucket}
                                        />
                                        <Field
                                            id={`region-${idSuffix}`}
                                            name="region"
                                            label="Region"
                                            placeholder={selected?.region_hint}
                                            defaultValue={disk?.region ?? ''}
                                            error={errors.region}
                                        />
                                    </div>

                                    <Field
                                        id={`endpoint-${idSuffix}`}
                                        name="endpoint"
                                        label={
                                            selected?.requires_endpoint
                                                ? 'Endpoint'
                                                : 'Endpoint (optional)'
                                        }
                                        placeholder={
                                            selected?.endpoint_template ??
                                            'https://s3.example.com'
                                        }
                                        defaultValue={disk?.endpoint ?? ''}
                                        error={errors.endpoint}
                                        hint={
                                            selected?.endpoint_template
                                                ? 'Derived from the region when left blank.'
                                                : undefined
                                        }
                                    />

                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <Field
                                            id={`access-${idSuffix}`}
                                            name="access_key"
                                            label="Access key"
                                            placeholder={
                                                disk?.has_credentials
                                                    ? 'Unchanged'
                                                    : undefined
                                            }
                                            error={errors.access_key}
                                            autoComplete="off"
                                        />
                                        <Field
                                            id={`secret-${idSuffix}`}
                                            name="secret_key"
                                            label="Secret key"
                                            type="password"
                                            placeholder={
                                                disk?.has_credentials
                                                    ? 'Unchanged'
                                                    : undefined
                                            }
                                            error={errors.secret_key}
                                            autoComplete="new-password"
                                        />
                                    </div>

                                    <Field
                                        id={`url-${idSuffix}`}
                                        name="url"
                                        label="Public / CDN base URL (optional)"
                                        placeholder="https://cdn.example.com"
                                        defaultValue={disk?.url ?? ''}
                                        error={errors.url}
                                    />

                                    <Field
                                        id={`root-${idSuffix}`}
                                        name="root"
                                        label="Path prefix (optional)"
                                        placeholder="digsignage"
                                        defaultValue={disk?.root ?? ''}
                                        error={errors.root}
                                    />
                                </>
                            ) : (
                                <Field
                                    id={`root-${idSuffix}`}
                                    name="root"
                                    label="Directory"
                                    placeholder="/var/www/storage/media"
                                    defaultValue={disk?.root ?? ''}
                                    error={errors.root}
                                    hint="Absolute path on the server. Defaults to the application's private media directory."
                                />
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor={`team-${idSuffix}`}>
                                    Availability
                                </Label>
                                <select
                                    id={`team-${idSuffix}`}
                                    name="team_id"
                                    className="bg-background w-full rounded-md border p-2"
                                    defaultValue={disk?.team_id ?? ''}
                                >
                                    <option value="">
                                        Shared with every organization
                                    </option>
                                    {organizations.map((organization) => (
                                        <option
                                            key={organization.id}
                                            value={organization.id}
                                        >
                                            {organization.name} only
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.team_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`visibility-${idSuffix}`}>
                                    Object visibility
                                </Label>
                                <select
                                    id={`visibility-${idSuffix}`}
                                    name="visibility"
                                    className="bg-background w-full rounded-md border p-2"
                                    defaultValue={disk?.visibility ?? 'private'}
                                >
                                    <option value="private">
                                        Private, served through signed URLs
                                    </option>
                                    <option value="public">
                                        Public, objects readable directly
                                    </option>
                                </select>
                                <InputError message={errors.visibility} />
                            </div>

                            <div className="space-y-2">
                                {isS3 && (
                                    <Toggle
                                        name="path_style_endpoint"
                                        label="Use path-style endpoint"
                                        defaultChecked={
                                            disk?.path_style_endpoint ??
                                            selected?.default_path_style ??
                                            false
                                        }
                                    />
                                )}
                                <Toggle
                                    name="is_default"
                                    label="Platform default for new uploads"
                                    defaultChecked={disk?.is_default ?? false}
                                />
                                <Toggle
                                    name="is_active"
                                    label="Enabled"
                                    defaultChecked={disk?.is_active ?? true}
                                />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    data-test="save-disk"
                                    disabled={processing}
                                >
                                    {editing ? 'Save changes' : 'Add backend'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    id,
    name,
    label,
    error,
    hint,
    ...props
}: {
    id: string;
    name: string;
    label: string;
    error?: string;
    hint?: string;
} & ComponentProps<typeof Input>) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input id={id} name={name} data-test={name} {...props} />
            {hint && <p className="text-muted-foreground text-xs">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

function Toggle({
    name,
    label,
    defaultChecked,
}: {
    name: string;
    label: string;
    defaultChecked: boolean;
}) {
    return (
        <label className="hover:bg-muted/50 flex items-center gap-2 rounded-md border px-2 py-1.5 text-sm">
            <input type="hidden" name={name} value="0" />
            <input
                type="checkbox"
                name={name}
                value="1"
                data-test={name}
                defaultChecked={defaultChecked}
                className="size-4 rounded border"
            />
            {label}
        </label>
    );
}

function OrganizationAssignments({
    disks,
    organizations,
    defaultDisk,
    unassignedBytes,
}: {
    disks: StorageDisk[];
    organizations: StorageOrganization[];
    defaultDisk: StorageDisk | null;
    unassignedBytes: number;
}) {
    return (
        <div className="bg-card rounded-xl border shadow-sm">
            <div className="flex flex-col gap-1 border-b p-5">
                <h2 className="text-lg font-semibold">
                    Organization assignments
                </h2>
                <p className="text-muted-foreground text-sm">
                    New uploads go to the assigned backend. Existing files stay
                    where they were written.
                    {unassignedBytes > 0 &&
                        ` ${formatBytes(unassignedBytes)} was uploaded before storage backends were configured and still lives on the environment disk.`}
                </p>
            </div>
            <div className="divide-y">
                {organizations.map((organization) => {
                    const available = disks.filter(
                        (disk) =>
                            disk.is_active &&
                            (disk.team_id === null ||
                                disk.team_id === organization.id),
                    );

                    return (
                        <div
                            key={organization.id}
                            className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="min-w-0">
                                <p className="truncate font-medium">
                                    {organization.name}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {organization.slug}
                                </p>
                            </div>
                            <select
                                aria-label={`Storage backend for ${organization.name}`}
                                data-test={`assign-${organization.id}`}
                                className="bg-background w-full rounded-md border p-2 sm:w-80"
                                value={organization.storage_disk_id ?? ''}
                                onChange={(event) =>
                                    router.patch(
                                        `/platform/storage/organizations/${organization.id}`,
                                        {
                                            storage_disk_id:
                                                event.target.value === ''
                                                    ? null
                                                    : Number(
                                                          event.target.value,
                                                      ),
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <option value="">
                                    {defaultDisk
                                        ? `Platform default (${defaultDisk.name})`
                                        : 'Platform default'}
                                </option>
                                {available.map((disk) => (
                                    <option key={disk.id} value={disk.id}>
                                        {disk.name} ({disk.provider_label})
                                    </option>
                                ))}
                            </select>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

PlatformStorage.layout = () => ({
    breadcrumbs: [
        { title: 'Platform', href: '/platform' },
        { title: 'Storage', href: '/platform/storage' },
    ],
});
