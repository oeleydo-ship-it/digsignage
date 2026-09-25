import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    ExternalLink,
    GitBranch,
    Loader2,
    RefreshCw,
    RotateCcw,
    Upload,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import updates from '@/routes/platform/updates';
import type {
    AppRelease,
    AppReleaseStatus,
    AvailableRelease,
    UpdaterStatus,
} from '@/types/release';

type Props = {
    current: { version: string; release: AppRelease | null };
    updater: UpdaterStatus;
    busy: boolean;
    releases: AppRelease[];
    github: { repositories: string[]; has_token: boolean };
    maxPackageMb: number;
    available?: {
        error: string | null;
        checked_at: string;
        releases: AvailableRelease[];
    };
};

type Errors = Record<string, string>;

type PendingAction = {
    title: string;
    description: string;
    confirmLabel: string;
    destructive?: boolean;
    submit: (
        password: string,
        callbacks: { onError: (errors: Errors) => void; onSuccess: () => void },
    ) => void;
};

const STATUS_STYLES: Record<AppReleaseStatus, string> = {
    active: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    inactive: 'bg-muted text-muted-foreground',
    ready: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    queued: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    installing: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    failed: 'bg-rose-500/15 text-rose-700 dark:text-rose-300',
};

function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';
}

function formatBytes(bytes: number | null): string {
    if (!bytes) {
        return '';
    }

    return bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.round(bytes / 1024)} KB`;
}

export default function PlatformUpdates({
    current,
    updater,
    busy,
    releases,
    github,
    maxPackageMb,
    available,
}: Props) {
    const [pending, setPending] = useState<PendingAction | null>(null);
    const [logFor, setLogFor] = useState<AppRelease | null>(null);
    const [checking, setChecking] = useState(false);
    const running = releases.find(
        (release) =>
            release.status === 'queued' || release.status === 'installing',
    );

    // Follow an install until it finishes.
    useEffect(() => {
        if (!busy) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only: ['releases', 'busy', 'current', 'updater'] });
        }, 3000);

        return () => window.clearInterval(timer);
    }, [busy]);

    const checkForUpdates = () => {
        setChecking(true);
        router.reload({
            only: ['available'],
            onFinish: () => setChecking(false),
        });
    };

    const installFromGitHub = (url: string, version: string) =>
        setPending({
            title: `Install ${version}`,
            description:
                'The new version is prepared beside the live site and switched over when it is ready. Visitors and screens stay connected.',
            confirmLabel: 'Install update',
            submit: (password, callbacks) =>
                router.post(
                    updates.github.url(),
                    { url, current_password: password },
                    { preserveScroll: true, ...callbacks },
                ),
        });

    return (
        <>
            <Head title="Updates" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Updates"
                    description="See what is running, what is new, and install updates from GitHub or an uploaded package without taking the platform offline."
                />

                <div className="grid gap-4 lg:grid-cols-3">
                    <section className="dashboard-card space-y-3 p-5 lg:col-span-2">
                        <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                            Live version
                        </p>
                        <div className="flex flex-wrap items-baseline gap-3">
                            <span
                                className="text-3xl font-semibold tabular-nums"
                                data-test="current-version"
                            >
                                v{current.version}
                            </span>
                            {current.release?.title && (
                                <span className="text-muted-foreground">
                                    {current.release.title}
                                </span>
                            )}
                        </div>
                        {current.release && (
                            <p className="text-muted-foreground text-sm">
                                {current.release.source_label} · live since{' '}
                                {formatDate(current.release.activated_at)}
                            </p>
                        )}
                        {current.release &&
                            current.release.features.length > 0 && (
                                <FeatureList
                                    features={current.release.features}
                                    limit={6}
                                />
                            )}
                    </section>

                    <section
                        className={`space-y-2 rounded-xl border p-5 text-sm ${
                            updater.enabled
                                ? 'border-emerald-500/30 bg-emerald-500/5'
                                : 'border-amber-500/40 bg-amber-500/10'
                        }`}
                        data-test="updater-status"
                    >
                        <p className="font-semibold">
                            {updater.enabled
                                ? 'Zero-downtime installs are on'
                                : 'Installs are off on this server'}
                        </p>
                        <p className="text-muted-foreground">
                            {updater.enabled
                                ? 'Updates are built in a new release folder and go live with an instant switch. Older releases are kept for rollback.'
                                : updater.reason}
                        </p>
                        {updater.current_path && (
                            <p className="font-mono text-xs break-all">
                                current → {updater.current_path}
                            </p>
                        )}
                        {!updater.enabled && (
                            <p className="text-muted-foreground text-xs">
                                You can still upload packages and review what is
                                new; the GitHub Actions deploy also works
                                without the in-app updater.
                            </p>
                        )}
                    </section>
                </div>

                {running && (
                    <section
                        className="flex flex-wrap items-center gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm"
                        role="status"
                    >
                        <Loader2 className="size-4 animate-spin" />
                        <span className="font-medium">
                            {running.status_label} v{running.version}…
                        </span>
                        <span className="text-muted-foreground">
                            The site stays online until the switch.
                        </span>
                        <Button
                            size="sm"
                            variant="outline"
                            className="ml-auto"
                            onClick={() => setLogFor(running)}
                        >
                            Watch progress
                        </Button>
                    </section>
                )}

                <section className="dashboard-card space-y-4 p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-base font-semibold">
                                New on GitHub
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Releases newer than v{current.version} from{' '}
                                {github.repositories.join(', ') ||
                                    'no repositories'}
                                .
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            onClick={checkForUpdates}
                            disabled={checking}
                            data-test="check-updates"
                        >
                            <RefreshCw
                                className={`size-4 ${checking ? 'animate-spin' : ''}`}
                            />
                            Check for updates
                        </Button>
                    </div>

                    {available === undefined ? (
                        <p className="text-muted-foreground text-sm">
                            Check GitHub to see which new versions are available
                            and what they add.
                        </p>
                    ) : available.error ? (
                        <p className="text-sm text-rose-600 dark:text-rose-400">
                            {available.error}
                        </p>
                    ) : available.releases.length === 0 ? (
                        <p className="flex items-center gap-2 text-sm">
                            <CheckCircle2 className="size-4 text-emerald-600" />
                            You are on the latest version.
                        </p>
                    ) : (
                        <div className="grid gap-3 md:grid-cols-2">
                            {available.releases.map((release) => (
                                <article
                                    key={`${release.repository}-${release.tag}`}
                                    className="space-y-3 rounded-lg border p-4"
                                    data-test={`available-${release.version}`}
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-lg font-semibold">
                                            v{release.version}
                                        </span>
                                        {release.prerelease && (
                                            <Badge variant="outline">
                                                Pre-release
                                            </Badge>
                                        )}
                                        <span className="text-muted-foreground ml-auto text-xs">
                                            {formatDate(release.published_at)}
                                        </span>
                                    </div>
                                    {release.name &&
                                        release.name !== release.tag && (
                                            <p className="text-sm font-medium">
                                                {release.name}
                                            </p>
                                        )}
                                    {release.features.length > 0 ? (
                                        <FeatureList
                                            features={release.features}
                                            limit={8}
                                        />
                                    ) : (
                                        <p className="text-muted-foreground text-sm">
                                            No release notes.
                                        </p>
                                    )}
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Button
                                            size="sm"
                                            disabled={
                                                busy ||
                                                release.installed ||
                                                !updater.enabled
                                            }
                                            onClick={() =>
                                                installFromGitHub(
                                                    release.url,
                                                    release.version,
                                                )
                                            }
                                        >
                                            {release.installed
                                                ? 'Installed'
                                                : 'Install'}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            asChild
                                        >
                                            <a
                                                href={release.url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <ExternalLink className="size-3.5" />
                                                GitHub
                                            </a>
                                        </Button>
                                        {!release.has_package && (
                                            <span className="text-muted-foreground text-xs">
                                                Source only: dependencies are
                                                built on the server.
                                            </span>
                                        )}
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </section>

                <div className="grid gap-4 lg:grid-cols-2">
                    <GitHubLinkForm
                        disabled={busy || !updater.enabled}
                        onSubmit={(url) => installFromGitHub(url, 'from link')}
                    />
                    <UploadForm
                        busy={busy}
                        maxPackageMb={maxPackageMb}
                        requestPassword={setPending}
                    />
                </div>

                <section className="dashboard-card overflow-x-auto">
                    <div className="p-5 pb-3">
                        <h2 className="text-base font-semibold">
                            Release history
                        </h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Every version installed on this platform. Roll back
                            to any previous release that is still on the server.
                        </p>
                    </div>
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="border-y text-xs tracking-wide uppercase">
                            <tr className="text-muted-foreground">
                                <th className="px-5 py-3 font-medium">
                                    Version
                                </th>
                                <th className="px-3 py-3 font-medium">
                                    What's new
                                </th>
                                <th className="px-3 py-3 font-medium">
                                    Source
                                </th>
                                <th className="px-3 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-3 font-medium">When</th>
                                <th className="px-5 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {releases.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="text-muted-foreground px-5 py-6 text-center"
                                    >
                                        No releases recorded yet.
                                    </td>
                                </tr>
                            )}
                            {releases.map((release) => (
                                <HistoryRow
                                    key={release.id}
                                    release={release}
                                    busy={busy}
                                    onLog={() => setLogFor(release)}
                                    requestPassword={setPending}
                                />
                            ))}
                        </tbody>
                    </table>
                </section>
            </div>

            <PasswordDialog action={pending} onClose={() => setPending(null)} />
            <LogDialog release={logFor} onClose={() => setLogFor(null)} />
        </>
    );
}

function FeatureList({
    features,
    limit,
}: {
    features: string[];
    limit: number;
}) {
    const [expanded, setExpanded] = useState(false);
    const shown = expanded ? features : features.slice(0, limit);

    return (
        <div className="space-y-1">
            <ul className="list-disc space-y-1 pl-5 text-sm">
                {shown.map((feature, index) => (
                    <li key={index}>{feature}</li>
                ))}
            </ul>
            {features.length > limit && (
                <button
                    type="button"
                    className="text-primary text-xs font-medium hover:underline"
                    onClick={() => setExpanded(!expanded)}
                >
                    {expanded
                        ? 'Show less'
                        : `Show ${features.length - limit} more`}
                </button>
            )}
        </div>
    );
}

function HistoryRow({
    release,
    busy,
    onLog,
    requestPassword,
}: {
    release: AppRelease;
    busy: boolean;
    onLog: () => void;
    requestPassword: (action: PendingAction) => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <tr data-test={`release-row-${release.version}`} className="align-top">
            <td className="px-5 py-3.5">
                <div className="font-semibold tabular-nums">
                    v{release.version}
                </div>
                {release.title && (
                    <div className="text-muted-foreground text-xs">
                        {release.title}
                    </div>
                )}
            </td>
            <td className="max-w-md px-3 py-3.5">
                {release.features.length === 0 ? (
                    <span className="text-muted-foreground">—</span>
                ) : (
                    <>
                        <button
                            type="button"
                            className="text-primary text-left text-sm font-medium hover:underline"
                            onClick={() => setOpen(!open)}
                            aria-expanded={open}
                        >
                            {release.features.length}{' '}
                            {release.features.length === 1
                                ? 'change'
                                : 'changes'}
                        </button>
                        {open && (
                            <ul className="mt-2 list-disc space-y-1 pl-5 text-xs">
                                {release.features.map((feature, index) => (
                                    <li key={index}>{feature}</li>
                                ))}
                            </ul>
                        )}
                    </>
                )}
            </td>
            <td className="px-3 py-3.5">
                <div>{release.source_label}</div>
                {release.source_ref && (
                    <div
                        className="text-muted-foreground max-w-48 truncate font-mono text-xs"
                        title={release.source_ref}
                    >
                        {release.source_ref}
                    </div>
                )}
                {release.package_size ? (
                    <div className="text-muted-foreground text-xs">
                        {formatBytes(release.package_size)}
                    </div>
                ) : null}
            </td>
            <td className="px-3 py-3.5">
                <span
                    className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_STYLES[release.status]}`}
                >
                    {release.status_label}
                </span>
                {release.error && (
                    <p className="mt-1 max-w-56 text-xs text-rose-600 dark:text-rose-400">
                        {release.error}
                    </p>
                )}
            </td>
            <td className="text-muted-foreground px-3 py-3.5 text-xs whitespace-nowrap">
                {formatDate(release.activated_at ?? release.created_at)}
                {release.created_by && <div>by {release.created_by}</div>}
            </td>
            <td className="px-5 py-3.5">
                <div className="flex flex-wrap justify-end gap-2">
                    {release.can_install && (
                        <Button
                            size="sm"
                            disabled={busy}
                            data-test={`install-${release.version}`}
                            onClick={() =>
                                requestPassword({
                                    title: `Install v${release.version}`,
                                    description:
                                        'The site stays online while the release is prepared, then switches over instantly.',
                                    confirmLabel: 'Install update',
                                    submit: (password, callbacks) =>
                                        router.post(
                                            updates.install.url(release.id),
                                            { current_password: password },
                                            {
                                                preserveScroll: true,
                                                ...callbacks,
                                            },
                                        ),
                                })
                            }
                        >
                            {release.status === 'failed' ? 'Retry' : 'Install'}
                        </Button>
                    )}
                    {release.can_rollback && (
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={busy}
                            data-test={`rollback-${release.version}`}
                            onClick={() =>
                                requestPassword({
                                    title: `Roll back to v${release.version}`,
                                    description:
                                        'The live site switches back to this release immediately. Database changes made by newer versions are kept.',
                                    confirmLabel: 'Roll back',
                                    destructive: true,
                                    submit: (password, callbacks) =>
                                        router.post(
                                            updates.rollback.url(release.id),
                                            { current_password: password },
                                            {
                                                preserveScroll: true,
                                                ...callbacks,
                                            },
                                        ),
                                })
                            }
                        >
                            <RotateCcw className="size-3.5" />
                            Roll back
                        </Button>
                    )}
                    {release.started_at ? (
                        <Button size="sm" variant="ghost" onClick={onLog}>
                            Log
                        </Button>
                    ) : null}
                    {release.can_delete && (
                        <Button
                            size="sm"
                            variant="ghost"
                            className="text-rose-600"
                            onClick={() =>
                                requestPassword({
                                    title: `Remove v${release.version}`,
                                    description:
                                        'Deletes the uploaded package. Nothing on the live site changes.',
                                    confirmLabel: 'Remove',
                                    destructive: true,
                                    submit: (password, callbacks) =>
                                        router.delete(
                                            updates.destroy.url(release.id),
                                            {
                                                data: {
                                                    current_password: password,
                                                },
                                                preserveScroll: true,
                                                ...callbacks,
                                            },
                                        ),
                                })
                            }
                        >
                            Remove
                        </Button>
                    )}
                </div>
            </td>
        </tr>
    );
}

function GitHubLinkForm({
    disabled,
    onSubmit,
}: {
    disabled: boolean;
    onSubmit: (url: string) => void;
}) {
    const [url, setUrl] = useState('');

    return (
        <section className="dashboard-card space-y-4 p-5">
            <div>
                <h2 className="flex items-center gap-2 text-base font-semibold">
                    <GitBranch className="size-4" />
                    Install from a GitHub link
                </h2>
                <p className="text-muted-foreground mt-1 text-sm">
                    Paste a release, tag or branch link, for example
                    github.com/owner/repo/releases/tag/v1.2.0 or
                    github.com/owner/repo/tree/main. A repository link installs
                    its latest release.
                </p>
            </div>
            <form
                className="flex flex-col gap-2 sm:flex-row"
                onSubmit={(event) => {
                    event.preventDefault();

                    if (url.trim() !== '') {
                        onSubmit(url.trim());
                    }
                }}
            >
                <Input
                    value={url}
                    onChange={(event) => setUrl(event.target.value)}
                    placeholder="https://github.com/owner/repo/releases/tag/v1.2.0"
                    aria-label="GitHub link"
                    data-test="github-url"
                />
                <Button type="submit" disabled={disabled || url.trim() === ''}>
                    Install
                </Button>
            </form>
        </section>
    );
}

function UploadForm({
    busy,
    maxPackageMb,
    requestPassword,
}: {
    busy: boolean;
    maxPackageMb: number;
    requestPassword: (action: PendingAction) => void;
}) {
    const [file, setFile] = useState<File | null>(null);
    const [checksum, setChecksum] = useState('');
    const [installNow, setInstallNow] = useState(false);
    const [progress, setProgress] = useState<number | null>(null);
    const input = useRef<HTMLInputElement>(null);

    return (
        <section className="dashboard-card space-y-4 p-5">
            <div>
                <h2 className="flex items-center gap-2 text-base font-semibold">
                    <Upload className="size-4" />
                    Upload an update package
                </h2>
                <p className="text-muted-foreground mt-1 text-sm">
                    A .zip built by the deploy workflow (or a source zip) with
                    VERSION and CHANGELOG.md at its root. Up to {maxPackageMb}{' '}
                    MB.
                </p>
            </div>
            <div className="grid gap-3">
                <Input
                    ref={input}
                    type="file"
                    accept=".zip,application/zip"
                    onChange={(event) =>
                        setFile(event.target.files?.[0] ?? null)
                    }
                    aria-label="Update package"
                    data-test="package-file"
                />
                <div className="grid gap-1.5">
                    <Label htmlFor="package-checksum">
                        SHA-256 checksum (optional)
                    </Label>
                    <Input
                        id="package-checksum"
                        value={checksum}
                        onChange={(event) => setChecksum(event.target.value)}
                        placeholder="Checked against the file before it is accepted"
                        className="font-mono text-xs"
                    />
                </div>
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={installNow}
                        onCheckedChange={(checked) =>
                            setInstallNow(checked === true)
                        }
                    />
                    Install as soon as it is uploaded
                </label>
                {progress !== null && (
                    <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                        <div
                            className="bg-primary h-full transition-all"
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                )}
                <Button
                    className="justify-self-start"
                    disabled={file === null || busy}
                    data-test="upload-package"
                    onClick={() =>
                        requestPassword({
                            title: 'Upload update package',
                            description: installNow
                                ? 'The package is checked, then installed with a zero-downtime switch.'
                                : 'The package is checked and kept ready to install.',
                            confirmLabel: 'Upload',
                            submit: (password, callbacks) =>
                                router.post(
                                    updates.upload.url(),
                                    {
                                        package: file,
                                        checksum: checksum.trim() || null,
                                        install_now: installNow,
                                        current_password: password,
                                    },
                                    {
                                        forceFormData: true,
                                        preserveScroll: true,
                                        onProgress: (event) =>
                                            setProgress(
                                                event?.percentage ?? null,
                                            ),
                                        onFinish: () => setProgress(null),
                                        onError: callbacks.onError,
                                        onSuccess: () => {
                                            callbacks.onSuccess();
                                            setFile(null);
                                            setChecksum('');

                                            if (input.current) {
                                                input.current.value = '';
                                            }
                                        },
                                    },
                                ),
                        })
                    }
                >
                    Upload package
                </Button>
            </div>
        </section>
    );
}

function PasswordDialog({
    action,
    onClose,
}: {
    action: PendingAction | null;
    onClose: () => void;
}) {
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState<Errors>({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        setPassword('');
        setErrors({});
        setProcessing(false);
    }, [action]);

    const messages = Object.entries(errors).filter(
        ([key]) => key !== 'current_password',
    );

    return (
        <Dialog
            open={action !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-md">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (!action) {
                            return;
                        }

                        setProcessing(true);
                        action.submit(password, {
                            onError: (next) => {
                                setErrors(next);
                                setProcessing(false);
                            },
                            onSuccess: () => {
                                setProcessing(false);
                                onClose();
                            },
                        });
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>{action?.title}</DialogTitle>
                        <DialogDescription>
                            {action?.description}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="release-password">
                            Confirm with your password
                        </Label>
                        <Input
                            id="release-password"
                            type="password"
                            autoComplete="current-password"
                            value={password}
                            onChange={(event) =>
                                setPassword(event.target.value)
                            }
                            data-test="release-password"
                            autoFocus
                        />
                        <InputError message={errors.current_password} />
                        {messages.map(([key, message]) => (
                            <InputError key={key} message={message} />
                        ))}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant={
                                action?.destructive ? 'destructive' : 'default'
                            }
                            disabled={processing || password === ''}
                            data-test="confirm-release-action"
                        >
                            {processing && (
                                <Loader2 className="size-4 animate-spin" />
                            )}
                            {action?.confirmLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function LogDialog({
    release,
    onClose,
}: {
    release: AppRelease | null;
    onClose: () => void;
}) {
    const [log, setLog] = useState('');
    const [status, setStatus] = useState<string | null>(null);
    const bottom = useRef<HTMLSpanElement>(null);

    useEffect(() => {
        if (!release) {
            return;
        }

        let cancelled = false;
        let timer: number | undefined;

        const load = async () => {
            try {
                const response = await fetch(updates.log.url(release.id), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const data = (await response.json()) as {
                    status: string;
                    log: string;
                };

                if (cancelled) {
                    return;
                }

                setLog(data.log);
                setStatus(data.status);

                if (data.status === 'queued' || data.status === 'installing') {
                    timer = window.setTimeout(load, 2000);
                }
            } catch {
                if (!cancelled) {
                    setLog('The log could not be loaded.');
                }
            }
        };

        setLog('');
        setStatus(null);
        void load();

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [release]);

    useEffect(() => {
        bottom.current?.scrollIntoView({ block: 'end' });
    }, [log]);

    return (
        <Dialog
            open={release !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Install log · v{release?.version}</DialogTitle>
                    <DialogDescription>
                        {status === 'queued' || status === 'installing'
                            ? 'Updating live while the install runs.'
                            : 'Output from the installer and deploy script.'}
                    </DialogDescription>
                </DialogHeader>
                <pre className="bg-muted max-h-[60vh] overflow-auto rounded-md p-3 font-mono text-xs whitespace-pre-wrap">
                    {log || 'No output yet.'}
                    <span ref={bottom} />
                </pre>
            </DialogContent>
        </Dialog>
    );
}
