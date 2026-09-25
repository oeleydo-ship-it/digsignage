import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import CanvasPreview from '@/components/canvas-preview';
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
    instantiate,
    show,
    store,
    thumbnail,
} from '@/routes/templates';
import type {
    DesignDocument,
    Paginated,
    TemplatePermissions,
    TemplateRecord,
} from '@/types';

type Option = { value: string; label: string };

type Card = TemplateRecord & {
    document?: DesignDocument | null;
    has_thumbnail?: boolean;
    thumbnail_url?: string | null;
};

type Orientation = 'landscape' | 'portrait' | 'square';

type Props = {
    templates: Paginated<Card>;
    featured: Card[];
    portraitFeatured: Card[];
    filters: {
        search: string;
        category: string;
        status: string;
        scope: string;
        orientation: string;
    };
    categories: Option[];
    statuses: Option[];
    permissions: TemplatePermissions;
};

const ORIENTATION_FILTERS: { value: string; label: string }[] = [
    { value: '', label: 'All orientations' },
    { value: 'landscape', label: 'Landscape' },
    { value: 'portrait', label: 'Portrait' },
    { value: 'square', label: 'Square' },
];

function canvasSize(template: Card): { width: number; height: number } {
    const width = Math.max(1, template.document?.width ?? template.width);
    const height = Math.max(1, template.document?.height ?? template.height);

    return { width, height };
}

function templateOrientation(width: number, height: number): Orientation {
    if (width === height) {
        return 'square';
    }

    return height > width ? 'portrait' : 'landscape';
}

function orientationLabel(width: number, height: number): string {
    const orientation = templateOrientation(width, height);

    if (orientation === 'square') {
        return 'Square';
    }

    if (orientation === 'portrait') {
        return 'Portrait';
    }

    return 'Landscape';
}

function TemplateCard({
    template,
    slug,
    designPermissions,
    permissions,
    onDelete,
    emphasizeUse = false,
}: {
    template: Card;
    slug: string;
    designPermissions?: { canCreateDesign?: boolean } | null;
    permissions: TemplatePermissions;
    onDelete: (template: TemplateRecord) => void;
    emphasizeUse?: boolean;
}) {
    const { width, height } = canvasSize(template);

    return (
        <article className="bg-card group overflow-hidden rounded-xl border shadow-sm transition hover:shadow-md">
            <Link
                href={show.url({
                    current_team: slug,
                    template: template.id,
                })}
                className="block"
            >
                <div
                    className="bg-muted relative w-full overflow-hidden"
                    style={{
                        // Landscape cards keep the design's own ratio; tall
                        // layouts are capped so a 9:16 card never towers over
                        // its row, and letterbox inside that frame instead.
                        aspectRatio: `${Math.max(width / height, 3 / 4)}`,
                    }}
                >
                    {template.document ? (
                        <CanvasPreview
                            document={template.document}
                            fit
                            staticPreview
                        />
                    ) : template.has_thumbnail ? (
                        <img
                            src={
                                template.thumbnail_url ??
                                thumbnail.url({
                                    current_team: slug,
                                    template: template.id,
                                })
                            }
                            alt=""
                            className="h-full w-full object-contain transition duration-300 group-hover:scale-[1.02]"
                        />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center">
                            <span className="text-muted-foreground text-xs">
                                {width} × {height}
                            </span>
                        </div>
                    )}
                </div>
            </Link>
            <div className="space-y-2 p-3">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 space-y-1">
                        <h3 className="truncate font-medium">
                            {template.name}
                        </h3>
                        <p className="text-muted-foreground text-xs">
                            {template.category_label} ·{' '}
                            {orientationLabel(width, height)}
                        </p>
                    </div>
                    <div className="flex shrink-0 flex-wrap justify-end gap-1">
                        {template.platform && (
                            <Badge variant="secondary">Catalog</Badge>
                        )}
                        <Badge variant="outline">{template.status_label}</Badge>
                    </div>
                </div>
                {template.description ? (
                    <p className="text-muted-foreground line-clamp-2 text-xs">
                        {template.description}
                    </p>
                ) : null}
                <div className="flex flex-wrap gap-1 pt-1">
                    <Button size="sm" variant="outline" asChild>
                        <Link
                            href={show.url({
                                current_team: slug,
                                template: template.id,
                            })}
                        >
                            Preview
                        </Link>
                    </Button>
                    <Button size="sm" variant="outline" asChild>
                        <Link
                            href={edit.url({
                                current_team: slug,
                                template: template.id,
                            })}
                        >
                            Open
                        </Link>
                    </Button>
                    {designPermissions?.canCreateDesign && (
                        <Button
                            size="sm"
                            variant={emphasizeUse ? 'default' : 'outline'}
                            onClick={() =>
                                router.post(
                                    instantiate.url({
                                        current_team: slug,
                                        template: template.id,
                                    }),
                                )
                            }
                            data-test="use-template"
                        >
                            Use
                        </Button>
                    )}
                    {permissions.canCreateTemplate && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    duplicate.url({
                                        current_team: slug,
                                        template: template.id,
                                    }),
                                )
                            }
                        >
                            Duplicate
                        </Button>
                    )}
                    {permissions.canDeleteTemplate &&
                        (!template.platform ||
                            permissions.canManagePlatformTemplates) && (
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => onDelete(template)}
                            >
                                Delete
                            </Button>
                        )}
                </div>
            </div>
        </article>
    );
}

function TemplateMasonry({
    templates,
    slug,
    designPermissions,
    permissions,
    onDelete,
    emphasizeUse = false,
}: {
    templates: Card[];
    slug: string;
    designPermissions?: { canCreateDesign?: boolean } | null;
    permissions: TemplatePermissions;
    onDelete: (template: TemplateRecord) => void;
    emphasizeUse?: boolean;
}) {
    return (
        <div className="grid grid-cols-1 items-start gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {templates.map((template) => (
                <TemplateCard
                    key={template.id}
                    template={template}
                    slug={slug}
                    designPermissions={designPermissions}
                    permissions={permissions}
                    onDelete={onDelete}
                    emphasizeUse={emphasizeUse}
                />
            ))}
        </div>
    );
}

export default function TemplatesIndex({
    templates,
    featured = [],
    portraitFeatured = [],
    filters,
    categories,
    statuses,
    permissions,
}: Props) {
    const { currentTeam, designPermissions } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [search, setSearch] = useState(filters.search);
    const [createOpen, setCreateOpen] = useState(false);
    const [deleting, setDeleting] = useState<TemplateRecord | null>(null);

    const galleryCategories = useMemo(() => {
        const preferred = [
            'corporate',
            'healthcare',
            'restaurant',
            'retail',
            'events',
            'education',
            'hospitality',
            'menu_boards',
            'portrait',
        ];
        const ordered = preferred
            .map((value) =>
                categories.find((category) => category.value === value),
            )
            .filter((category): category is Option => category !== undefined);
        const remainder = categories.filter(
            (category) => !preferred.includes(category.value),
        );

        return [...ordered, ...remainder];
    }, [categories]);

    const showFeatured =
        filters.search === '' &&
        filters.category === '' &&
        filters.orientation === '' &&
        filters.scope !== 'team';

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            index(slug),
            {
                search: next.search ?? search,
                category: next.category ?? filters.category,
                status: next.status ?? filters.status,
                scope: next.scope ?? filters.scope,
                orientation: next.orientation ?? filters.orientation,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Templates" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Templates"
                        description="Browse ready-made layouts with live widgets, or save your own team templates."
                    />
                    {permissions.canCreateTemplate && (
                        <Button
                            onClick={() => setCreateOpen(true)}
                            data-test="create-template"
                        >
                            New template
                        </Button>
                    )}
                </div>

                <div className="space-y-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <Input
                            value={search}
                            placeholder="Search templates"
                            className="max-w-xs"
                            onChange={(event) => setSearch(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    applyFilters({ search });
                                }
                            }}
                        />
                        <select
                            className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                            value={filters.scope}
                            onChange={(event) =>
                                applyFilters({ scope: event.target.value })
                            }
                        >
                            <option value="">All sources</option>
                            <option value="team">Team</option>
                            <option value="platform">Catalog</option>
                        </select>
                        {permissions.canUpdateTemplate && (
                            <select
                                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                value={filters.status}
                                onChange={(event) =>
                                    applyFilters({ status: event.target.value })
                                }
                            >
                                <option value="">All statuses</option>
                                {statuses.map((status) => (
                                    <option
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </option>
                                ))}
                            </select>
                        )}
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant={
                                filters.category === '' ? 'default' : 'outline'
                            }
                            onClick={() => applyFilters({ category: '' })}
                        >
                            All templates
                        </Button>
                        {galleryCategories.map((category) => (
                            <Button
                                key={category.value}
                                type="button"
                                size="sm"
                                variant={
                                    filters.category === category.value
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() =>
                                    applyFilters({ category: category.value })
                                }
                            >
                                {category.label}
                            </Button>
                        ))}
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {ORIENTATION_FILTERS.map((option) => (
                            <Button
                                key={option.value || 'all-orientations'}
                                type="button"
                                size="sm"
                                variant={
                                    filters.orientation === option.value
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                onClick={() =>
                                    applyFilters({ orientation: option.value })
                                }
                            >
                                {option.label}
                            </Button>
                        ))}
                    </div>
                </div>

                {showFeatured && featured.length > 0 && (
                    <section className="space-y-3">
                        <div>
                            <h2 className="text-lg font-semibold">
                                Featured templates
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                Popular starting points with working widgets.
                                Use copies the canvas into a team design you can
                                edit.
                            </p>
                        </div>
                        <TemplateMasonry
                            templates={featured}
                            slug={slug}
                            designPermissions={designPermissions}
                            permissions={permissions}
                            onDelete={setDeleting}
                            emphasizeUse
                        />
                    </section>
                )}

                {showFeatured && portraitFeatured.length > 0 && (
                    <section className="space-y-3">
                        <div>
                            <h2 className="text-lg font-semibold">
                                Portrait highlights
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                Vertical layouts for 9:16 and HD portrait
                                displays.
                            </p>
                        </div>
                        <TemplateMasonry
                            templates={portraitFeatured}
                            slug={slug}
                            designPermissions={designPermissions}
                            permissions={permissions}
                            onDelete={setDeleting}
                            emphasizeUse
                        />
                    </section>
                )}

                <section className="space-y-3">
                    <div className="flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <h2 className="text-lg font-semibold">
                                Template gallery
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                {templates.total} layout
                                {templates.total === 1 ? '' : 's'} · mixed
                                landscape, portrait, and square sizes
                            </p>
                        </div>
                    </div>

                    {templates.data.length === 0 ? (
                        <EmptyState
                            title="No templates match these filters"
                            description="Browse the catalog, clear filters, or create a blank team template."
                        />
                    ) : (
                        <TemplateMasonry
                            templates={templates.data}
                            slug={slug}
                            designPermissions={designPermissions}
                            permissions={permissions}
                            onDelete={setDeleting}
                            emphasizeUse
                        />
                    )}

                    {templates.last_page > 1 && (
                        <div className="flex flex-wrap items-center justify-center gap-2 pt-2">
                            {templates.links.map((link, index) => (
                                <Button
                                    key={`${link.url ?? 'null'}-${index}`}
                                    size="sm"
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    disabled={!link.url}
                                    onClick={() => {
                                        if (link.url) {
                                            router.get(
                                                link.url,
                                                {},
                                                {
                                                    preserveState: true,
                                                    replace: true,
                                                },
                                            );
                                        }
                                    }}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    )}
                </section>
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
                                    <DialogTitle>New template</DialogTitle>
                                </DialogHeader>
                                <div className="space-y-2">
                                    <Label htmlFor="template-name">Name</Label>
                                    <Input id="template-name" name="name" />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="template-category">
                                        Category
                                    </Label>
                                    <select
                                        id="template-category"
                                        name="category"
                                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                        defaultValue="corporate"
                                    >
                                        {categories.map((category) => (
                                            <option
                                                key={category.value}
                                                value={category.value}
                                            >
                                                {category.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="template-width">
                                            Width
                                        </Label>
                                        <Input
                                            id="template-width"
                                            name="width"
                                            type="number"
                                            defaultValue={1920}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="template-height">
                                            Height
                                        </Label>
                                        <Input
                                            id="template-height"
                                            name="height"
                                            type="number"
                                            defaultValue={1080}
                                        />
                                    </div>
                                </div>
                                {permissions.canManagePlatformTemplates && (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="platform"
                                            value="1"
                                        />
                                        Publish into the platform catalog
                                    </label>
                                )}
                                <select
                                    name="status"
                                    defaultValue="draft"
                                    className="hidden"
                                >
                                    {statuses.map((status) => (
                                        <option
                                            key={status.value}
                                            value={status.value}
                                        >
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
                title="Delete template?"
                description="This removes the reusable layout from the library."
                confirmLabel="Delete"
                onConfirm={() => {
                    if (!deleting) {
                        return;
                    }

                    router.delete(
                        destroy.url({
                            current_team: slug,
                            template: deleting.id,
                        }),
                    );
                    setDeleting(null);
                }}
                onOpenChange={(open) => !open && setDeleting(null)}
            />
        </>
    );
}

TemplatesIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Templates',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
