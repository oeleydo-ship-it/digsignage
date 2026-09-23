import { Form, Head, router, usePage } from '@inertiajs/react';
import { Layers, Monitor, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
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
import { dashboard } from '@/routes';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/screen-groups';
import { sync } from '@/routes/screen-groups/screens';
import type { ScreenGroupRecord, SignagePermissions } from '@/types';

type ScreenOption = { id: number; name: string; status: string };

type Props = {
    groups: ScreenGroupRecord[];
    screens: ScreenOption[];
    permissions: SignagePermissions;
};

function screenLabel(count: number): string {
    return `${count} screen${count === 1 ? '' : 's'}`;
}

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
}

export default function ScreenGroupsIndex({
    groups,
    screens,
    permissions,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<ScreenGroupRecord | null>(null);
    const [deleting, setDeleting] = useState<ScreenGroupRecord | null>(null);
    const [managing, setManaging] = useState<ScreenGroupRecord | null>(null);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [query, setQuery] = useState('');

    const visibleGroups = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (needle === '') {
            return groups;
        }

        return groups.filter((group) =>
            group.name.toLowerCase().includes(needle),
        );
    }, [groups, query]);

    const openCreate = () => {
        setEditing(null);
        setName('');
        setDescription('');
        setOpen(true);
    };

    const openEdit = (group: ScreenGroupRecord) => {
        setEditing(group);
        setName(group.name);
        setDescription(group.description ?? '');
        setOpen(true);
    };

    const openScreens = (group: ScreenGroupRecord) => {
        setManaging(group);
        setSelectedIds(group.screen_ids);
    };

    return (
        <>
            <Head title="Screen groups" />
            <div className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 sm:p-8">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-muted-foreground mb-2 text-[11px] font-semibold tracking-[0.18em] uppercase">
                            Display sets
                        </p>
                        <h1 className="text-3xl font-bold tracking-tight">
                            Screen groups
                        </h1>
                        <p className="text-muted-foreground mt-2 max-w-2xl text-sm leading-relaxed">
                            Bundle displays for bulk assignment now and for
                            schedule targeting later.
                        </p>
                    </div>
                    {permissions.canCreateScreenGroup && (
                        <Button onClick={openCreate} data-test="create-group">
                            <Layers className="size-4" />
                            Add group
                        </Button>
                    )}
                </div>

                {groups.length === 0 ? (
                    <section className="dashboard-card">
                        <div className="flex flex-col items-center px-6 py-16 text-center">
                            <div className="mb-5 flex size-16 items-center justify-center rounded-2xl border border-blue-100 bg-blue-50 text-blue-600 dark:border-blue-900 dark:bg-blue-950">
                                <Layers className="size-7" strokeWidth={1.5} />
                            </div>
                            <h2 className="text-base font-semibold">
                                No groups yet
                            </h2>
                            <p className="text-muted-foreground mt-2 max-w-sm text-sm leading-relaxed">
                                Create groups such as Reception, Elevators, or
                                Menu Boards, then assign screens in bulk.
                            </p>
                            {permissions.canCreateScreenGroup && (
                                <Button className="mt-6" onClick={openCreate}>
                                    <Layers className="size-4" />
                                    Add group
                                </Button>
                            )}
                        </div>
                    </section>
                ) : (
                    <>
                        <div className="dashboard-card flex flex-wrap items-center gap-3 px-4 py-3">
                            <div className="relative min-w-56 flex-1">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2" />
                                <Input
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Search by name"
                                    aria-label="Search screen groups"
                                    className="pl-9"
                                />
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {`${groups.length} group${groups.length === 1 ? '' : 's'}`}
                            </p>
                        </div>

                        {visibleGroups.length === 0 ? (
                            <section className="dashboard-card">
                                <p className="text-muted-foreground px-5 py-12 text-center text-sm">
                                    No groups match that name.
                                </p>
                            </section>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {visibleGroups.map((group) => {
                                    const preview = group.screens.slice(0, 6);
                                    const extra =
                                        group.screens.length - preview.length;

                                    return (
                                        <article
                                            key={group.id}
                                            className="dashboard-card flex flex-col p-5"
                                            data-test={`group-card-${group.id}`}
                                        >
                                            <div className="flex items-start gap-3">
                                                <div className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg border">
                                                    <Layers className="text-muted-foreground size-4" />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <h2 className="font-semibold tracking-tight">
                                                            {group.name}
                                                        </h2>
                                                        <Badge variant="secondary">
                                                            <Monitor className="size-3" />
                                                            {screenLabel(
                                                                group.screens_count,
                                                            )}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-muted-foreground mt-1 text-sm leading-relaxed">
                                                        {group.description ||
                                                            'No description'}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="mt-4 min-h-16 flex-1">
                                                {group.screens.length === 0 ? (
                                                    <p className="text-muted-foreground text-sm">
                                                        No screens assigned.
                                                        Use Screens to add
                                                        members.
                                                    </p>
                                                ) : (
                                                    <ul className="space-y-2">
                                                        {preview.map(
                                                            (screen) => (
                                                                <li
                                                                    key={
                                                                        screen.id
                                                                    }
                                                                    className="flex items-center gap-2 text-sm"
                                                                >
                                                                    <span
                                                                        className="bg-muted flex size-7 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold"
                                                                        aria-hidden
                                                                    >
                                                                        {initials(
                                                                            screen.name,
                                                                        )}
                                                                    </span>
                                                                    <span className="min-w-0 truncate font-medium">
                                                                        {
                                                                            screen.name
                                                                        }
                                                                    </span>
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="ml-auto capitalize"
                                                                    >
                                                                        {
                                                                            screen.status
                                                                        }
                                                                    </Badge>
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                )}
                                                {extra > 0 && (
                                                    <p className="text-muted-foreground mt-2 text-xs">
                                                        +{extra} more
                                                    </p>
                                                )}
                                            </div>

                                            <div className="mt-5 flex flex-wrap gap-2">
                                                {permissions.canUpdateScreenGroup && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            openScreens(group)
                                                        }
                                                    >
                                                        Screens
                                                    </Button>
                                                )}
                                                {permissions.canUpdateScreenGroup && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            openEdit(group)
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                )}
                                                {permissions.canDeleteScreenGroup && (
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() =>
                                                            setDeleting(group)
                                                        }
                                                    >
                                                        Delete
                                                    </Button>
                                                )}
                                            </div>
                                        </article>
                                    );
                                })}
                            </div>
                        )}
                    </>
                )}
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <Form
                        {...(editing
                            ? update.form([slug, editing.id])
                            : store.form(slug))}
                        className="space-y-4"
                        onSuccess={() => setOpen(false)}
                    >
                        {({ errors, processing }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        {editing ? 'Edit group' : 'Add group'}
                                    </DialogTitle>
                                    <DialogDescription>
                                        Groups collect screens for assignment
                                        and later scheduling.
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="grid gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            value={name}
                                            onChange={(event) =>
                                                setName(event.target.value)
                                            }
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="description">
                                            Description
                                        </Label>
                                        <Input
                                            id="description"
                                            name="description"
                                            value={description}
                                            onChange={(event) =>
                                                setDescription(
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Save
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={managing !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        setManaging(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Assign screens</DialogTitle>
                        <DialogDescription>
                            {managing
                                ? `Choose which screens belong to ${managing.name}.`
                                : 'Choose which screens belong to this group.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-80 space-y-2 overflow-y-auto">
                        {screens.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No screens are registered in this organization
                                yet.
                            </p>
                        ) : (
                            screens.map((screen) => (
                                <label
                                    key={screen.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={selectedIds.includes(
                                            screen.id,
                                        )}
                                        onCheckedChange={(checked) =>
                                            setSelectedIds((current) =>
                                                checked
                                                    ? [...current, screen.id]
                                                    : current.filter(
                                                          (id) =>
                                                              id !== screen.id,
                                                      ),
                                            )
                                        }
                                    />
                                    {screen.name}
                                </label>
                            ))
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            onClick={() => {
                                if (!managing) {
                                    return;
                                }

                                router.put(
                                    sync([slug, managing.id]),
                                    { screen_ids: selectedIds },
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setManaging(null),
                                    },
                                );
                            }}
                        >
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                title="Delete group"
                description="Screens stay registered; they are only removed from this group."
                confirmLabel="Delete"
                onOpenChange={(next) => {
                    if (!next) {
                        setDeleting(null);
                    }
                }}
                onConfirm={() => {
                    if (!deleting) {
                        return;
                    }

                    router.delete(destroy([slug, deleting.id]), {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    });
                }}
            />
        </>
    );
}

ScreenGroupsIndex.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Groups',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
