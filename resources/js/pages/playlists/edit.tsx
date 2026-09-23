import { Head, Link, router, usePage } from '@inertiajs/react';
import { GripVertical } from 'lucide-react';
import { useRef, useState } from 'react';
import ContentWorkflowPanel from '@/components/content-workflow-panel';
import { PlaylistThumb } from '@/components/playlist-thumb';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WidgetSchemaForm from '@/components/widget-schema-form';
import { dashboard } from '@/routes';
import {
    edit as editRoute,
    index,
    show,
    update,
} from '@/routes/playlists';
import { restore } from '@/routes/playlists/revisions';
import type {
    ContentApprovalPayload,
    PlaylistItemRecord,
    PlaylistPermissions,
    PlaylistRecord,
    WidgetDefinition,
} from '@/types';

type Option = { value: string; label: string };
type CatalogItem = {
    id: number;
    name: string;
    type?: string;
    duration_seconds?: number;
    platform?: boolean;
    preview_url?: string | null;
};
type Revision = { id: number; version: number; created_at: string | null };

type Props = {
    playlist: PlaylistRecord & {
        loop: boolean;
        description: string | null;
        items: PlaylistItemRecord[];
    };
    revisions: Revision[];
    itemTypes: Option[];
    transitions: Option[];
    catalog: {
        media: CatalogItem[];
        designs: CatalogItem[];
    };
    permissions: PlaylistPermissions;
    approval: ContentApprovalPayload;
};

function blankItem(
    type: string,
    extras: Partial<PlaylistItemRecord> = {},
): PlaylistItemRecord {
    return {
        type,
        title: extras.title ?? type,
        duration_seconds: extras.duration_seconds ?? 15,
        transition: extras.transition ?? 'fade',
        transition_ms: extras.transition_ms ?? 400,
        enabled: extras.enabled ?? true,
        available_from: extras.available_from ?? null,
        available_until: extras.available_until ?? null,
        media_id: extras.media_id ?? null,
        design_id: extras.design_id ?? null,
        template_id: extras.template_id ?? null,
        url: extras.url ?? null,
        widget_key: extras.widget_key ?? null,
        widget_settings: extras.widget_settings ?? {},
        preview_url: extras.preview_url ?? null,
        media_type: extras.media_type ?? null,
    };
}

function totalDuration(items: PlaylistItemRecord[]): number {
    return items
        .filter((item) => item.enabled)
        .reduce((sum, item) => sum + item.duration_seconds, 0);
}

function toLocalInput(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const pad = (part: number) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export default function PlaylistEditor({
    playlist,
    revisions,
    itemTypes,
    transitions,
    catalog,
    permissions,
    approval,
}: Props) {
    const { currentTeam, widgets = [] } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const canEdit = permissions.canUpdatePlaylist && !approval.locked;
    const [name, setName] = useState(playlist.name);
    const [loop, setLoop] = useState(playlist.loop);
    const [items, setItems] = useState<PlaylistItemRecord[]>(playlist.items);
    const [selected, setSelected] = useState(0);
    const [library, setLibrary] = useState<'media' | 'designs' | 'widgets'>(
        'media',
    );
    const widgetCatalog = widgets as WidgetDefinition[];
    const dragIndex = useRef<number | null>(null);
    const current = items[selected] ?? null;

    const save = (nextItems = items, after?: () => void) => {
        if (!canEdit) {
            after?.();

            return;
        }

        router.patch(
            update.url({ current_team: slug, playlist: playlist.id }),
            {
                name,
                status: playlist.status,
                loop,
                items: nextItems,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: after,
            },
        );
    };

    const publish = () => {
        const release = () => {
            router.post(
                `/${slug}/approvals/playlist/${playlist.id}/publish`,
                {},
                { preserveScroll: true, preserveState: true },
            );
        };

        if (canEdit) {
            save(items, release);

            return;
        }

        release();
    };

    const addItem = (item: PlaylistItemRecord) => {
        setItems((currentItems) => [...currentItems, item]);
        setSelected(items.length);
    };

    const updateItem = (index: number, patch: Partial<PlaylistItemRecord>) => {
        setItems((currentItems) =>
            currentItems.map((item, itemIndex) =>
                itemIndex === index ? { ...item, ...patch } : item,
            ),
        );
    };

    const moveItem = (from: number, to: number) => {
        if (to < 0 || to >= items.length || from === to) {
            return;
        }

        setItems((currentItems) => {
            const next = [...currentItems];
            const [moved] = next.splice(from, 1);
            next.splice(to, 0, moved);

            return next;
        });
        setSelected(to);
    };

    return (
        <>
            <Head title={`${playlist.name} playlist`} />
            <div className="flex h-[calc(100vh-4rem)] min-h-[640px] flex-col">
                <div className="flex flex-wrap items-center gap-2 border-b p-3">
                    <Input
                        value={name}
                        className="max-w-xs"
                        disabled={!canEdit}
                        onChange={(event) => setName(event.target.value)}
                    />
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={loop}
                            disabled={!canEdit}
                            onChange={(event) => setLoop(event.target.checked)}
                        />
                        Loop
                    </label>
                    <span className="text-muted-foreground text-sm">
                        {items.length} items · {totalDuration(items)}s · v
                        {playlist.version}
                    </span>
                    {canEdit && !approval.can_publish && (
                        <Button
                            size="sm"
                            onClick={() => save()}
                            data-test="save-playlist"
                        >
                            Save
                        </Button>
                    )}
                    {approval.can_publish && (
                        <Button
                            size="sm"
                            onClick={publish}
                            data-test="publish-playlist"
                        >
                            Publish
                        </Button>
                    )}
                    <Button size="sm" variant="outline" asChild>
                        <Link
                            href={show.url({
                                current_team: slug,
                                playlist: playlist.id,
                            })}
                        >
                            Preview
                        </Link>
                    </Button>
                </div>
                <ContentWorkflowPanel approval={approval} hidePublish />
                <div className="grid min-h-0 flex-1 grid-cols-[280px_minmax(0,1fr)_280px]">
                    <aside className="space-y-3 overflow-auto border-r p-3">
                        <div className="flex gap-1">
                            {(['media', 'designs', 'widgets'] as const).map(
                                (tab) => (
                                    <Button
                                        key={tab}
                                        size="sm"
                                        variant={
                                            library === tab
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() => setLibrary(tab)}
                                    >
                                        {tab}
                                    </Button>
                                ),
                            )}
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            {library === 'widgets'
                                ? widgetCatalog.map((widget) => (
                                      <button
                                          key={widget.key}
                                          type="button"
                                          disabled={!canEdit}
                                          title={widget.label}
                                          className="group bg-card hover:border-foreground/20 disabled:opacity-50 overflow-hidden rounded-lg border text-left transition-colors"
                                          onClick={() =>
                                              addItem(
                                                  blankItem('widget', {
                                                      title: widget.label,
                                                      widget_key: widget.key,
                                                      widget_settings: {
                                                          ...widget.defaults,
                                                      },
                                                  }),
                                              )
                                          }
                                      >
                                          <PlaylistThumb
                                              alt={widget.label}
                                              type="widget"
                                              widgetKey={widget.key}
                                              label={widget.label}
                                              className="aspect-video"
                                          />
                                          <p className="text-muted-foreground group-hover:text-foreground truncate px-2 py-1.5 text-xs">
                                              {widget.label}
                                          </p>
                                      </button>
                                  ))
                                : catalog[library].map((entry) => (
                                      <button
                                          key={`${library}-${entry.id}`}
                                          type="button"
                                          disabled={!canEdit}
                                          title={entry.name}
                                          className="group bg-card hover:border-foreground/20 disabled:opacity-50 overflow-hidden rounded-lg border text-left transition-colors"
                                          onClick={() => {
                                              if (library === 'media') {
                                                  addItem(
                                                      blankItem('media', {
                                                          title: entry.name,
                                                          media_id: entry.id,
                                                          duration_seconds:
                                                              entry.duration_seconds ??
                                                              15,
                                                          preview_url:
                                                              entry.preview_url ??
                                                              null,
                                                          media_type:
                                                              entry.type ??
                                                              null,
                                                      }),
                                                  );
                                              } else {
                                                  addItem(
                                                      blankItem('design', {
                                                          title: entry.name,
                                                          design_id: entry.id,
                                                          duration_seconds: 20,
                                                          preview_url:
                                                              entry.preview_url ??
                                                              null,
                                                      }),
                                                  );
                                              }
                                          }}
                                      >
                                          <PlaylistThumb
                                              src={entry.preview_url}
                                              alt={entry.name}
                                              type={
                                                  library === 'media'
                                                      ? 'media'
                                                      : 'design'
                                              }
                                              mediaType={entry.type}
                                              label={
                                                  library === 'media'
                                                      ? (entry.type ?? 'media')
                                                      : 'design'
                                              }
                                              className="aspect-video"
                                          />
                                          <p className="text-muted-foreground group-hover:text-foreground truncate px-2 py-1.5 text-xs">
                                              {entry.name}
                                          </p>
                                      </button>
                                  ))}
                        </div>
                        <div className="space-y-1 border-t pt-3">
                            {itemTypes
                                .filter((type) =>
                                    ['web_page', 'live_stream'].includes(
                                        type.value,
                                    ),
                                )
                                .map((type) => (
                                    <Button
                                        key={type.value}
                                        variant="outline"
                                        className="w-full justify-start"
                                        disabled={!canEdit}
                                        onClick={() =>
                                            addItem(
                                                blankItem(type.value, {
                                                    title: type.label,
                                                    url: 'https://example.com',
                                                }),
                                            )
                                        }
                                    >
                                        Add {type.label}
                                    </Button>
                                ))}
                        </div>
                    </aside>
                    <ol className="grid content-start gap-3 overflow-auto p-4 sm:grid-cols-2 xl:grid-cols-3">
                        {items.length === 0 ? (
                            <p className="text-muted-foreground col-span-full text-sm">
                                Add media, designs, or widgets to start the
                                sequence.
                            </p>
                        ) : (
                            items.map((item, index) => (
                                <li
                                    key={`${item.type}-${index}`}
                                    draggable={canEdit}
                                    onDragStart={() => {
                                        dragIndex.current = index;
                                    }}
                                    onDragOver={(event) =>
                                        event.preventDefault()
                                    }
                                    onDrop={() => {
                                        if (dragIndex.current === null) {
                                            return;
                                        }

                                        moveItem(dragIndex.current, index);
                                        dragIndex.current = null;
                                    }}
                                    className={`bg-card cursor-grab overflow-hidden rounded-xl border transition-shadow ${selected === index ? 'ring-primary ring-2 ring-offset-2' : ''} ${item.enabled ? '' : 'opacity-60'}`}
                                    onClick={() => setSelected(index)}
                                >
                                    <div className="relative">
                                        <PlaylistThumb
                                            src={item.preview_url}
                                            alt={item.title}
                                            type={item.type}
                                            mediaType={item.media_type}
                                            widgetKey={item.widget_key}
                                            label={item.type_label ?? item.type}
                                            className="aspect-video"
                                        />
                                        <div className="absolute inset-x-0 top-0 flex items-start justify-between p-2">
                                            <span className="bg-background/90 rounded-md px-1.5 py-0.5 text-xs font-semibold">
                                                {index + 1}
                                            </span>
                                            <span className="bg-background/90 rounded-md px-1.5 py-0.5 text-xs">
                                                {item.duration_seconds}s
                                            </span>
                                        </div>
                                        {canEdit && (
                                            <GripVertical className="text-background absolute right-2 bottom-2 size-4 drop-shadow" />
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2 px-2.5 py-2">
                                        <Badge
                                            variant="secondary"
                                            className="capitalize"
                                        >
                                            {item.type_label ?? item.type}
                                        </Badge>
                                        <p
                                            className="min-w-0 flex-1 truncate text-xs"
                                            title={item.title}
                                        >
                                            {item.title}
                                        </p>
                                    </div>
                                </li>
                            ))
                        )}
                    </ol>
                    <aside className="space-y-3 overflow-auto border-l p-3 text-sm">
                        {current ? (
                            <>
                                <PlaylistThumb
                                    src={current.preview_url}
                                    alt={current.title}
                                    type={current.type}
                                    mediaType={current.media_type}
                                    widgetKey={current.widget_key}
                                    label={current.type_label ?? current.type}
                                    className="aspect-video rounded-lg border"
                                />
                                <Label>Title</Label>
                                <Input
                                    value={current.title}
                                    disabled={!canEdit}
                                    onChange={(event) =>
                                        updateItem(selected, {
                                            title: event.target.value,
                                        })
                                    }
                                />
                                <Label>Duration (seconds)</Label>
                                <Input
                                    type="number"
                                    min={1}
                                    value={current.duration_seconds}
                                    disabled={!canEdit}
                                    onChange={(event) =>
                                        updateItem(selected, {
                                            duration_seconds: Number(
                                                event.target.value,
                                            ),
                                        })
                                    }
                                />
                                <Label>Transition</Label>
                                <select
                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    value={current.transition}
                                    disabled={!canEdit}
                                    onChange={(event) =>
                                        updateItem(selected, {
                                            transition: event.target.value,
                                        })
                                    }
                                >
                                    {transitions.map((item) => (
                                        <option
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
                                        </option>
                                    ))}
                                </select>
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={current.enabled}
                                        disabled={!canEdit}
                                        onChange={(event) =>
                                            updateItem(selected, {
                                                enabled: event.target.checked,
                                            })
                                        }
                                    />
                                    Enabled
                                </label>
                                <Label>Available from</Label>
                                <Input
                                    type="datetime-local"
                                    value={toLocalInput(current.available_from)}
                                    disabled={!canEdit}
                                    onChange={(event) =>
                                        updateItem(selected, {
                                            available_from:
                                                event.target.value || null,
                                        })
                                    }
                                />
                                <Label>Available until</Label>
                                <Input
                                    type="datetime-local"
                                    value={toLocalInput(
                                        current.available_until,
                                    )}
                                    disabled={!canEdit}
                                    onChange={(event) =>
                                        updateItem(selected, {
                                            available_until:
                                                event.target.value || null,
                                        })
                                    }
                                />
                                {(current.type === 'web_page' ||
                                    current.type === 'live_stream') && (
                                    <>
                                        <Label>URL</Label>
                                        <Input
                                            value={current.url ?? ''}
                                            disabled={!canEdit}
                                            onChange={(event) =>
                                                updateItem(selected, {
                                                    url: event.target.value,
                                                })
                                            }
                                        />
                                    </>
                                )}
                                {current.type === 'template' && (
                                    <p className="text-muted-foreground text-xs">
                                        Legacy template item. Use the template
                                        library to create an editable design
                                        before adding new content.
                                    </p>
                                )}
                                {current.type === 'widget' && (
                                    <WidgetSchemaForm
                                        widget={
                                            widgetCatalog.find(
                                                (entry) =>
                                                    entry.key ===
                                                    current.widget_key,
                                            ) ?? {
                                                key: current.widget_key ?? '',
                                                label: 'Widget',
                                                description: '',
                                                defaults: {},
                                                schema: [],
                                            }
                                        }
                                        values={current.widget_settings ?? {}}
                                        disabled={!canEdit}
                                        onChange={(widget_settings) =>
                                            updateItem(selected, {
                                                widget_settings,
                                            })
                                        }
                                    />
                                )}
                                {canEdit && (
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        onClick={() => {
                                            setItems((currentItems) =>
                                                currentItems.filter(
                                                    (_, itemIndex) =>
                                                        itemIndex !== selected,
                                                ),
                                            );
                                            setSelected(0);
                                        }}
                                    >
                                        Remove item
                                    </Button>
                                )}
                            </>
                        ) : (
                            <p className="text-muted-foreground">
                                Select an item to edit duration, transition, and
                                availability.
                            </p>
                        )}
                        <div className="space-y-1 border-t pt-3">
                            <p className="font-medium">Versions</p>
                            {revisions.map((revision) => (
                                <Button
                                    key={revision.id}
                                    size="sm"
                                    variant="ghost"
                                    className="w-full justify-start"
                                    disabled={!canEdit}
                                    onClick={() =>
                                        router.post(
                                            restore.url({
                                                current_team: slug,
                                                playlist: playlist.id,
                                                revision: revision.id,
                                            }),
                                        )
                                    }
                                >
                                    v{revision.version}
                                </Button>
                            ))}
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}

PlaylistEditor.layout = (props: {
    currentTeam?: { slug: string } | null;
    playlist?: { id: number; name: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Playlists',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
        {
            title: props.playlist?.name ?? 'Edit',
            href:
                props.currentTeam && props.playlist
                    ? editRoute.url({
                          current_team: props.currentTeam.slug,
                          playlist: props.playlist.id,
                      })
                    : '/',
        },
    ],
});
