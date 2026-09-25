import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import {
    destroy,
    duplicate,
    edit,
    index,
    show,
    store,
} from '@/routes/designs';
import { index as templatesIndex } from '@/routes/templates';
import type { DesignPermissions, DesignRecord, Paginated } from '@/types';

type Preset = { label: string; width: number; height: number };
type Option = { value: string; label: string };

type Props = {
    designs: Paginated<DesignRecord>;
    filters: { search: string; status: string };
    statuses: Option[];
    presets: Preset[];
    permissions: DesignPermissions;
};

export default function DesignsIndex({
    designs,
    filters,
    statuses,
    presets,
    permissions,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [search, setSearch] = useState(filters.search);
    const [createOpen, setCreateOpen] = useState(false);
    const [deleting, setDeleting] = useState<DesignRecord | null>(null);

    return (
        <>
            <Head title="Designer" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Content designer"
                        description="Build canvas layouts as structured JSON, with revisions and preview."
                    />
                    {permissions.canCreateDesign && (
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={templatesIndex.url(slug)}>
                                    Start from a template
                                </Link>
                            </Button>
                            <Button
                                onClick={() => setCreateOpen(true)}
                                data-test="create-design"
                            >
                                New design
                            </Button>
                        </div>
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    <Input
                        value={search}
                        placeholder="Search designs"
                        className="max-w-xs"
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                router.get(
                                    index(slug),
                                    { search, status: filters.status },
                                    { preserveState: true, replace: true },
                                );
                            }
                        }}
                    />
                    <select
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        value={filters.status}
                        onChange={(event) =>
                            router.get(
                                index(slug),
                                { search, status: event.target.value },
                                { preserveState: true, replace: true },
                            )
                        }
                    >
                        <option value="">All statuses</option>
                        {statuses
                            .filter((status) =>
                                ['draft', 'published'].includes(status.value),
                            )
                            .map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                    </select>
                </div>

                {designs.data.length === 0 ? (
                    <EmptyState
                        title="No designs yet"
                        description="Use a ready-made template or create a blank landscape or portrait canvas."
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3">Name</th>
                                    <th className="p-3">Size</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3">Version</th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {designs.data.map((design) => (
                                    <tr
                                        key={design.id}
                                        className="border-t"
                                    >
                                        <td className="p-3 font-medium">
                                            {design.name}
                                        </td>
                                        <td className="p-3">
                                            {design.width} × {design.height}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    design.status ===
                                                    'published'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {design.status_label}
                                            </Badge>
                                        </td>
                                        <td className="p-3">v{design.version}</td>
                                        <td className="space-x-2 p-3 text-right">
                                            {design.status !== 'published' &&
                                                design.status !==
                                                    'pending_approval' && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            router.post(
                                                                `/${slug}/approvals/design/${design.id}/publish`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                    preserveState: true,
                                                                },
                                                            )
                                                        }
                                                        data-test="publish-design"
                                                    >
                                                        Publish
                                                    </Button>
                                                )}
                                            <Button size="sm" variant="outline" asChild>
                                                <Link href={show.url({ current_team: slug, design: design.id })}>
                                                    Preview
                                                </Link>
                                            </Button>
                                            <Button size="sm" asChild>
                                                <Link href={edit.url({ current_team: slug, design: design.id })}>
                                                    Open
                                                </Link>
                                            </Button>
                                            {permissions.canCreateDesign && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            duplicate.url({
                                                                current_team: slug,
                                                                design: design.id,
                                                            }),
                                                        )
                                                    }
                                                >
                                                    Duplicate
                                                </Button>
                                            )}
                                            {permissions.canDeleteDesign && (
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() => setDeleting(design)}
                                                >
                                                    Delete
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent>
                    <Form
                        {...store.form(slug)}
                        className="space-y-4"
                        onSuccess={() => setCreateOpen(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>New design</DialogTitle>
                                </DialogHeader>
                                <div className="space-y-2">
                                    <Label htmlFor="design-name">Name</Label>
                                    <Input id="design-name" name="name" />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="design-width">Width</Label>
                                        <Input
                                            id="design-width"
                                            name="width"
                                            type="number"
                                            defaultValue={1920}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="design-height">Height</Label>
                                        <Input
                                            id="design-height"
                                            name="height"
                                            type="number"
                                            defaultValue={1080}
                                        />
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {presets.map((preset) => (
                                        <span
                                            key={preset.label}
                                            className="text-muted-foreground text-xs"
                                        >
                                            {preset.label}
                                        </span>
                                    ))}
                                </div>
                                <select name="status" defaultValue="draft" className="hidden">
                                    {statuses.map((status) => (
                                        <option key={status.value} value={status.value}>
                                            {status.label}
                                        </option>
                                    ))}
                                </select>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Create
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                title="Delete design?"
                description="This removes the layout and its revision history."
                confirmLabel="Delete"
                onConfirm={() => {
                    if (!deleting) {
                        return;
                    }

                    router.delete(
                        destroy.url({
                            current_team: slug,
                            design: deleting.id,
                        }),
                    );
                    setDeleting(null);
                }}
                onOpenChange={(open) => !open && setDeleting(null)}
            />
        </>
    );
}

DesignsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Designer',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
