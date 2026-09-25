import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import ColorInput from '@/components/color-input';
import ContentWorkflowPanel from '@/components/content-workflow-panel';
import DesignerArrange from '@/components/designer-arrange';
import DesignerPalette from '@/components/designer-palette';
import DesignerGraphics, {
    type GraphicInsert,
} from '@/components/designer-graphics';
import DesignerProperties from '@/components/designer-properties';
import KonvaDesigner from '@/components/konva-designer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useHydratedDocument } from '@/hooks/use-hydrated-document';
import { fitCanvasScale } from '@/lib/canvas-fit';
import { nextDesignLayer } from '@/lib/design-layers';
import { defaultElementSize } from '@/lib/designer-sizes';
import {
    cloneForCanvas,
    copyElement,
    readClipboard,
} from '@/lib/designer-clipboard';
import { dashboard } from '@/routes';
import { edit as editRoute, index, show, update } from '@/routes/templates';
import { resolve as resolveWidgets } from '@/routes/widgets';
import type {
    ContentApprovalPayload,
    DesignDocument,
    DesignDocumentElement,
    TemplatePermissions,
    TemplateRecord,
    WidgetDefinition,
} from '@/types';

type Option = { value: string; label: string };

type Props = {
    template: TemplateRecord & { document: DesignDocument };
    elementTypes: Option[];
    categories: Option[];
    statuses: Option[];
    permissions: TemplatePermissions;
    approval: ContentApprovalPayload;
};

const MIN_SIZE = 20;

function cloneDocument(document: DesignDocument): DesignDocument {
    return JSON.parse(JSON.stringify(document)) as DesignDocument;
}

function clamp(value: number, minimum: number, maximum: number): number {
    return Math.min(Math.max(value, minimum), Math.max(minimum, maximum));
}

function defaultProps(
    type: string,
    widgets: WidgetDefinition[] = [],
): Record<string, string | number | boolean | null> {
    const widget =
        widgets.find((entry) => entry.key === type) ??
        (type === 'chart'
            ? widgets.find((entry) => entry.key === 'charts')
            : undefined);

    if (widget) {
        return { ...widget.defaults };
    }

    if (type === 'text') {
        return {
            text: 'Headline',
            fontSize: 48,
            color: '#ffffff',
            align: 'left',
            fontWeight: '600',
        };
    }

    if (type === 'ticker') {
        return {
            text: 'Welcome visitors',
            speed: 40,
            color: '#ffffff',
            background: 'transparent',
            fontSize: 48,
        };
    }

    if (type === 'shape' || type === 'button') {
        return { fill: '#2563eb', radius: 8, label: type };
    }

    if (type === 'image' || type === 'logo') {
        return { src: null, media_id: null, objectFit: 'cover' };
    }

    if (type === 'icon') {
        return {
            icon: 'star',
            color: '#ffffff',
            strokeWidth: 2,
            background: 'transparent',
            badgeShape: 'circle',
        };
    }

    return { label: type.replaceAll('_', ' ') };
}

export default function TemplateEditor({
    template,
    elementTypes,
    categories,
    statuses,
    permissions,
    approval,
}: Props) {
    const { currentTeam, widgets = [] } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const canEdit = template.platform
        ? permissions.canManagePlatformTemplates
        : permissions.canUpdateTemplate && !approval.locked;
    const [name, setName] = useState(template.name);
    const [category, setCategory] = useState(template.category);
    const [status, setStatus] = useState(template.status);
    const [document, setDocument] = useState<DesignDocument>(template.document);
    const documentRef = useRef(document);
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [zoom, setZoom] = useState(0.35);
    const userZoomed = useRef(false);
    const [grid, setGrid] = useState(10);
    const workspace = useRef<HTMLDivElement>(null);
    const [history, setHistory] = useState<DesignDocument[]>([
        cloneDocument(template.document),
    ]);
    const [historyIndex, setHistoryIndex] = useState(0);
    const historyIndexRef = useRef(historyIndex);
    historyIndexRef.current = historyIndex;
    const skipAutosave = useRef(true);
    const autosave = useRef<number | null>(null);

    useEffect(() => {
        documentRef.current = document;
    }, [document]);

    const selected =
        document.elements.find((element) => element.id === selectedId) ?? null;
    const ordered = useMemo(
        () => [...document.elements].sort((a, b) => a.zIndex - b.zIndex),
        [document.elements],
    );
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const hydrated = useHydratedDocument(
        document,
        slug ? resolveWidgets.url(slug) : null,
        timezone,
    );

    const commit = (next: DesignDocument) => {
        if (!canEdit) {
            return;
        }

        const snapshot = cloneDocument(next);
        documentRef.current = snapshot;
        setDocument(snapshot);
        setHistory((current) => {
            const updated = [
                ...current.slice(0, historyIndexRef.current + 1),
                snapshot,
            ];
            setHistoryIndex(updated.length - 1);

            return updated;
        });
    };

    const undo = () => {
        if (historyIndex > 0) {
            const next = historyIndex - 1;
            setHistoryIndex(next);
            setDocument(cloneDocument(history[next]));
        }
    };

    const redo = () => {
        if (historyIndex < history.length - 1) {
            const next = historyIndex + 1;
            setHistoryIndex(next);
            setDocument(cloneDocument(history[next]));
        }
    };

    const fitCanvas = (fromUser = false) => {
        const node = workspace.current;

        if (!node) {
            return;
        }

        if (fromUser) {
            userZoomed.current = false;
        }

        const bounds = node.getBoundingClientRect();
        const next = fitCanvasScale(
            bounds.width - 32,
            bounds.height - 32,
            document.width,
            document.height,
            'contain',
        );

        setZoom(Math.max(0.1, Math.min(1, next)));
    };

    useEffect(() => {
        if (!userZoomed.current) {
            fitCanvas();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [document.width, document.height]);

    const save = (next = document) => {
        if (!canEdit) {
            return;
        }

        router.patch(
            update.url({ current_team: slug, template: template.id }),
            {
                name,
                category,
                status,
                document: next,
                width: next.width,
                height: next.height,
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    useEffect(() => {
        if (!canEdit) {
            return;
        }

        if (skipAutosave.current) {
            skipAutosave.current = false;

            return;
        }

        if (autosave.current) {
            window.clearTimeout(autosave.current);
        }

        autosave.current = window.setTimeout(() => save(), 1500);

        return () => {
            if (autosave.current) {
                window.clearTimeout(autosave.current);
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [document, name, category, status, canEdit]);

    const addElement = (type: string, insert?: GraphicInsert) => {
        const preset = insert?.width
            ? null
            : defaultElementSize(type, document.width, document.height);
        const width = insert?.fullCanvas
            ? document.width
            : Math.min(
                  document.width,
                  insert?.width ??
                      preset?.width ??
                      Math.min(document.width * 0.4, 480),
              );
        const height = insert?.fullCanvas
            ? document.height
            : Math.min(
                  document.height,
                  insert?.height ?? preset?.height ?? 180,
              );
        const element: DesignDocumentElement = {
            id: crypto.randomUUID(),
            type,
            name: insert?.name ?? type.replaceAll('_', ' '),
            x: insert?.fullCanvas
                ? 0
                : Math.round((document.width - width) / 2),
            y: insert?.fullCanvas
                ? 0
                : Math.round((document.height - height) / 2),
            width,
            height,
            rotation: 0,
            opacity: insert?.opacity ?? 1,
            zIndex: insert?.fullCanvas
                ? Math.min(0, ...document.elements.map((item) => item.zIndex)) -
                  1
                : nextDesignLayer(document.elements),
            locked: false,
            hidden: false,
            props: { ...defaultProps(type, widgets), ...insert?.props },
        };

        commit({ ...document, elements: [...document.elements, element] });
        setSelectedId(element.id);
    };

    const updateSelected = (patch: Partial<DesignDocumentElement>) => {
        if (!canEdit || !selected || selected.locked) {
            return;
        }

        const next = { ...selected, ...patch };
        next.width = clamp(next.width, MIN_SIZE, document.width);
        next.height = clamp(next.height, MIN_SIZE, document.height);
        next.x = clamp(next.x, 0, document.width - next.width);
        next.y = clamp(next.y, 0, document.height - next.height);
        next.opacity = clamp(next.opacity, 0, 1);

        commit({
            ...document,
            elements: document.elements.map((element) =>
                element.id === selected.id ? next : element,
            ),
        });
    };

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                event.target instanceof HTMLElement &&
                event.target.closest(
                    'input, textarea, select, [contenteditable]',
                )
            ) {
                return;
            }

            if (canEdit && (event.ctrlKey || event.metaKey)) {
                const key = event.key.toLowerCase();

                if (key === 'c' && selected) {
                    event.preventDefault();
                    copyElement(selected);

                    return;
                }

                if (key === 'v') {
                    const copied = readClipboard();

                    if (copied) {
                        event.preventDefault();
                        const pasted = cloneForCanvas(
                            copied,
                            documentRef.current,
                        );
                        commit({
                            ...documentRef.current,
                            elements: [...documentRef.current.elements, pasted],
                        });
                        setSelectedId(pasted.id);
                    }

                    return;
                }

                if (key === 'd' && selected) {
                    event.preventDefault();
                    const copy = cloneForCanvas(selected, documentRef.current);
                    commit({
                        ...documentRef.current,
                        elements: [...documentRef.current.elements, copy],
                    });
                    setSelectedId(copy.id);

                    return;
                }
                if (event.key.toLowerCase() === 'z') {
                    event.preventDefault();

                    if (event.shiftKey) {
                        redo();
                    } else {
                        undo();
                    }

                    return;
                }

                if (event.key.toLowerCase() === 's') {
                    event.preventDefault();
                    save();

                    return;
                }
            }

            if (!selected || !canEdit || selected.locked) {
                return;
            }

            if (event.key.startsWith('Arrow')) {
                event.preventDefault();
                const step = event.shiftKey ? 10 : 1;
                updateSelected({
                    x:
                        selected.x +
                        (event.key === 'ArrowRight'
                            ? step
                            : event.key === 'ArrowLeft'
                              ? -step
                              : 0),
                    y:
                        selected.y +
                        (event.key === 'ArrowDown'
                            ? step
                            : event.key === 'ArrowUp'
                              ? -step
                              : 0),
                });
            }

            if (event.key === 'Delete' || event.key === 'Backspace') {
                event.preventDefault();
                commit({
                    ...documentRef.current,
                    elements: documentRef.current.elements.filter(
                        (item) => item.id !== selected.id,
                    ),
                });
                setSelectedId(null);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    });

    return (
        <>
            <Head title={`${template.name} template`} />
            <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
                <div className="flex shrink-0 flex-wrap items-center gap-1.5 border-b px-2 py-1.5">
                    <Input
                        value={name}
                        className="h-8 max-w-[10rem]"
                        disabled={!canEdit}
                        onChange={(event) => setName(event.target.value)}
                    />
                    <select
                        className="border-input bg-background h-8 rounded-md border px-2 text-sm"
                        value={category}
                        disabled={!canEdit}
                        onChange={(event) => setCategory(event.target.value)}
                    >
                        {categories.map((item) => (
                            <option key={item.value} value={item.value}>
                                {item.label}
                            </option>
                        ))}
                    </select>
                    <select
                        className="border-input bg-background h-8 rounded-md border px-2 text-sm"
                        value={status}
                        disabled={!canEdit}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        {statuses.map((item) => (
                            <option key={item.value} value={item.value}>
                                {item.label}
                            </option>
                        ))}
                    </select>

                    {canEdit && (
                        <Button
                            size="sm"
                            className="h-8"
                            onClick={() => save()}
                            data-test="save-template"
                        >
                            Save
                        </Button>
                    )}
                    <Button
                        size="sm"
                        variant="outline"
                        className="h-8"
                        onClick={undo}
                        disabled={!canEdit || historyIndex === 0}
                    >
                        Undo
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        className="h-8"
                        onClick={redo}
                        disabled={
                            !canEdit || historyIndex >= history.length - 1
                        }
                    >
                        Redo
                    </Button>

                    <span aria-hidden className="bg-border mx-1 h-6 w-px" />
                    <DesignerArrange
                        document={document}
                        selected={selected}
                        disabled={!canEdit}
                        onApply={(patch) => updateSelected(patch)}
                        onReorder={(elements) =>
                            commit({ ...documentRef.current, elements })
                        }
                    />
                    <span aria-hidden className="bg-border mx-1 h-6 w-px" />

                    <Label className="text-xs">Zoom</Label>
                    <Input
                        type="range"
                        min={0.1}
                        max={1}
                        step={0.05}
                        value={zoom}
                        className="h-8 w-24"
                        onChange={(event) => {
                            userZoomed.current = true;
                            setZoom(Number(event.target.value));
                        }}
                    />
                    <span className="w-8 text-xs">
                        {Math.round(zoom * 100)}%
                    </span>
                    <Button
                        size="sm"
                        variant="outline"
                        className="h-8"
                        onClick={() => fitCanvas(true)}
                    >
                        Fit
                    </Button>
                    <Label className="text-xs">Grid</Label>
                    <Input
                        type="number"
                        min={1}
                        className="h-8 w-14"
                        value={grid}
                        onChange={(event) =>
                            setGrid(
                                Math.max(1, Number(event.target.value) || 1),
                            )
                        }
                    />

                    <Button size="sm" variant="outline" className="h-8" asChild>
                        <Link
                            href={show.url({
                                current_team: slug,
                                template: template.id,
                            })}
                        >
                            Preview
                        </Link>
                    </Button>
                    {!template.platform && (
                        <ContentWorkflowPanel approval={approval} compact />
                    )}
                </div>

                <div className="flex min-h-0 min-w-0 flex-1">
                    <aside className="flex w-[240px] shrink-0 flex-col overflow-hidden border-r">
                        <div className="min-h-0 flex-1 overflow-y-auto p-2">
                            <p className="text-muted-foreground px-2 pb-2 text-xs font-medium uppercase">
                                Elements
                            </p>
                            <DesignerPalette
                                elementTypes={elementTypes}
                                disabled={!canEdit}
                                onAdd={(type) => addElement(type)}
                            />
                            <p className="text-muted-foreground mt-5 px-2 pb-2 text-xs font-medium uppercase">
                                Graphics
                            </p>
                            <div className="px-1">
                                <DesignerGraphics
                                    disabled={!canEdit}
                                    onInsert={(type, insert) =>
                                        addElement(type, insert)
                                    }
                                />
                            </div>
                        </div>
                    </aside>

                    <div
                        ref={workspace}
                        className="bg-muted/40 grid min-h-0 min-w-0 flex-1 place-items-center overflow-auto p-4"
                        onPointerDown={(event) => {
                            if (
                                event.target === event.currentTarget ||
                                (event.target as HTMLElement).hasAttribute(
                                    'data-canvas',
                                )
                            ) {
                                setSelectedId(null);
                            }
                        }}
                    >
                        <KonvaDesigner
                            document={hydrated}
                            timezone={timezone}
                            zoom={zoom}
                            grid={grid}
                            selectedId={selectedId}
                            editable={canEdit}
                            onSelect={setSelectedId}
                            onChange={(changed) =>
                                commit({
                                    ...documentRef.current,
                                    elements: documentRef.current.elements.map(
                                        (item) =>
                                            item.id === changed.id
                                                ? changed
                                                : item,
                                    ),
                                })
                            }
                        />
                    </div>

                    <aside className="w-[240px] shrink-0 space-y-3 overflow-auto border-l p-3 text-sm">
                        <label className="block text-xs">
                            Canvas background
                            <ColorInput
                                aria-label="Canvas background"
                                disabled={!canEdit}
                                fallback="#000000"
                                value={document.background}
                                onValue={(background) =>
                                    commit({ ...document, background })
                                }
                            />
                        </label>
                        <div className="rounded-lg border p-2">
                            <p className="mb-2 text-xs font-semibold">Layers</p>
                            {[...ordered].reverse().map((item) => (
                                <button
                                    type="button"
                                    key={item.id}
                                    className={`block w-full truncate rounded px-2 py-1 text-left text-xs ${item.id === selectedId ? 'bg-sky-100 text-sky-900' : 'hover:bg-muted'}`}
                                    onClick={() => setSelectedId(item.id)}
                                >
                                    {item.hidden ? '◌ ' : '▣ '}
                                    {item.name}
                                    {item.locked ? ' · locked' : ''}
                                </button>
                            ))}
                        </div>
                        {selected ? (
                            <>
                                <div className="font-medium capitalize">
                                    {selected.type.replaceAll('_', ' ')}
                                </div>
                                <Label>Name</Label>
                                <Input
                                    value={selected.name}
                                    disabled={!canEdit || selected.locked}
                                    onChange={(event) =>
                                        updateSelected({
                                            name: event.target.value,
                                        })
                                    }
                                />
                                <DesignerProperties
                                    element={selected}
                                    disabled={!canEdit || selected.locked}
                                    widgets={widgets}
                                    onChange={(props) =>
                                        updateSelected({ props })
                                    }
                                />
                                <div className="grid grid-cols-2 gap-2">
                                    <label className="text-xs">
                                        X
                                        <Input
                                            type="number"
                                            value={Math.round(selected.x)}
                                            disabled={
                                                !canEdit || selected.locked
                                            }
                                            onChange={(event) =>
                                                updateSelected({
                                                    x: Number(
                                                        event.target.value,
                                                    ),
                                                })
                                            }
                                        />
                                    </label>
                                    <label className="text-xs">
                                        Y
                                        <Input
                                            type="number"
                                            value={Math.round(selected.y)}
                                            disabled={
                                                !canEdit || selected.locked
                                            }
                                            onChange={(event) =>
                                                updateSelected({
                                                    y: Number(
                                                        event.target.value,
                                                    ),
                                                })
                                            }
                                        />
                                    </label>
                                    <label className="text-xs">
                                        Width
                                        <Input
                                            type="number"
                                            value={Math.round(selected.width)}
                                            disabled={
                                                !canEdit || selected.locked
                                            }
                                            onChange={(event) =>
                                                updateSelected({
                                                    width: Number(
                                                        event.target.value,
                                                    ),
                                                })
                                            }
                                        />
                                    </label>
                                    <label className="text-xs">
                                        Height
                                        <Input
                                            type="number"
                                            value={Math.round(selected.height)}
                                            disabled={
                                                !canEdit || selected.locked
                                            }
                                            onChange={(event) =>
                                                updateSelected({
                                                    height: Number(
                                                        event.target.value,
                                                    ),
                                                })
                                            }
                                        />
                                    </label>
                                </div>
                                <div className="flex flex-wrap gap-1">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        disabled={!canEdit}
                                        onClick={() =>
                                            updateSelected({
                                                locked: !selected.locked,
                                            })
                                        }
                                    >
                                        {selected.locked ? 'Unlock' : 'Lock'}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        disabled={!canEdit}
                                        onClick={() =>
                                            updateSelected({
                                                hidden: !selected.hidden,
                                            })
                                        }
                                    >
                                        {selected.hidden ? 'Show' : 'Hide'}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        disabled={!canEdit}
                                        onClick={() => {
                                            const copy = {
                                                ...selected,
                                                id: crypto.randomUUID(),
                                                name: `${selected.name} copy`,
                                                x: clamp(
                                                    selected.x + 20,
                                                    0,
                                                    document.width -
                                                        selected.width,
                                                ),
                                                y: clamp(
                                                    selected.y + 20,
                                                    0,
                                                    document.height -
                                                        selected.height,
                                                ),
                                                zIndex: nextDesignLayer(
                                                    document.elements,
                                                ),
                                            };
                                            commit({
                                                ...document,
                                                elements: [
                                                    ...document.elements,
                                                    copy,
                                                ],
                                            });
                                            setSelectedId(copy.id);
                                        }}
                                    >
                                        Duplicate
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        disabled={!canEdit}
                                        onClick={() => {
                                            commit({
                                                ...document,
                                                elements:
                                                    document.elements.filter(
                                                        (element) =>
                                                            element.id !==
                                                            selected.id,
                                                    ),
                                            });
                                            setSelectedId(null);
                                        }}
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </>
                        ) : (
                            <p className="text-muted-foreground">
                                Select an element on the canvas, or add one from
                                the toolbox.
                            </p>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

TemplateEditor.layout = (props: {
    currentTeam?: { slug: string } | null;
    template?: { id: number; name: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Templates',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
        {
            title: props.template?.name ?? 'Edit',
            href:
                props.currentTeam && props.template
                    ? editRoute.url({
                          current_team: props.currentTeam.slug,
                          template: props.template.id,
                      })
                    : '/',
        },
    ],
});
