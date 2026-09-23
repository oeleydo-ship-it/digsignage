import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import WidgetSchemaForm from '@/components/widget-schema-form';
import ContentWorkflowPanel from '@/components/content-workflow-panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { edit as editRoute, index, show, update } from '@/routes/templates';
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

function cloneDocument(document: DesignDocument): DesignDocument {
    return JSON.parse(JSON.stringify(document)) as DesignDocument;
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

    if (type === 'text' || type === 'ticker') {
        return {
            text: type === 'ticker' ? 'Welcome visitors' : 'Headline',
            fontSize: 48,
        };
    }

    if (type === 'shape' || type === 'button') {
        return { fill: '#2563eb', label: type };
    }

    return { label: type };
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
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [zoom, setZoom] = useState(0.35);
    const drag = useRef<{
        id: string;
        offsetX: number;
        offsetY: number;
    } | null>(null);
    const skipAutosave = useRef(true);
    const autosave = useRef<number | null>(null);

    const selected =
        document.elements.find((element) => element.id === selectedId) ?? null;
    const ordered = useMemo(
        () => [...document.elements].sort((a, b) => a.zIndex - b.zIndex),
        [document.elements],
    );

    const commit = (next: DesignDocument) => {
        setDocument(cloneDocument(next));
    };

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
    }, [document, name, category, status, canEdit]);

    const addElement = (type: string) => {
        const element: DesignDocumentElement = {
            id: crypto.randomUUID(),
            type,
            name: type,
            x: 80,
            y: 80,
            width: 320,
            height: 140,
            rotation: 0,
            opacity: 1,
            zIndex: document.elements.length + 1,
            locked: false,
            hidden: false,
            props: defaultProps(type, widgets),
        };

        commit({ ...document, elements: [...document.elements, element] });
        setSelectedId(element.id);
    };

    const standardElementTypes = elementTypes.filter(
        (type) => !type.value.startsWith('queue_'),
    );
    const queueElementTypes = elementTypes.filter((type) =>
        type.value.startsWith('queue_'),
    );

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
                    <Input
                        type="range"
                        min={0.1}
                        max={1}
                        step={0.05}
                        value={zoom}
                        className="h-8 w-24"
                        onChange={(event) =>
                            setZoom(Number(event.target.value))
                        }
                    />
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
                    <aside className="w-[240px] shrink-0 space-y-1 overflow-y-auto border-r p-2">
                        {standardElementTypes.map((type) => (
                            <Button
                                key={type.value}
                                variant="ghost"
                                className="w-full justify-start"
                                disabled={!canEdit}
                                onClick={() => addElement(type.value)}
                            >
                                {type.label}
                            </Button>
                        ))}
                        {queueElementTypes.length > 0 && (
                            <>
                                <p className="text-muted-foreground px-3 pt-4 pb-1 text-xs font-medium uppercase">
                                    Queue
                                </p>
                                {queueElementTypes.map((type) => (
                                    <Button
                                        key={type.value}
                                        variant="ghost"
                                        className="h-auto min-h-10 w-full justify-start py-2 text-left whitespace-normal"
                                        disabled={!canEdit}
                                        onClick={() => addElement(type.value)}
                                    >
                                        {type.label.replace('Queue · ', '')}
                                    </Button>
                                ))}
                            </>
                        )}
                    </aside>
                    <div
                        className="bg-muted/40 min-h-0 min-w-0 flex-1 overflow-auto p-3"
                        onMouseUp={() => {
                            drag.current = null;
                        }}
                        onMouseMove={(event) => {
                            if (!drag.current || !canEdit) {
                                return;
                            }

                            const canvas =
                                event.currentTarget.querySelector(
                                    '[data-canvas]',
                                );

                            if (!(canvas instanceof HTMLElement)) {
                                return;
                            }

                            const bounds = canvas.getBoundingClientRect();
                            const x = Math.round(
                                (event.clientX - bounds.left) / zoom -
                                    drag.current.offsetX,
                            );
                            const y = Math.round(
                                (event.clientY - bounds.top) / zoom -
                                    drag.current.offsetY,
                            );
                            const id = drag.current.id;

                            setDocument((current) => ({
                                ...current,
                                elements: current.elements.map((element) =>
                                    element.id === id && !element.locked
                                        ? { ...element, x, y }
                                        : element,
                                ),
                            }));
                        }}
                    >
                        <div
                            data-canvas
                            className="relative shadow-xl"
                            style={{
                                width: document.width * zoom,
                                height: document.height * zoom,
                                background: document.background,
                            }}
                        >
                            {ordered
                                .filter((element) => !element.hidden)
                                .map((element) => (
                                    <button
                                        key={element.id}
                                        type="button"
                                        className={`absolute overflow-hidden text-left text-white ${selectedId === element.id ? 'ring-2 ring-sky-400' : ''}`}
                                        style={{
                                            left: element.x * zoom,
                                            top: element.y * zoom,
                                            width: element.width * zoom,
                                            height: element.height * zoom,
                                            opacity: element.opacity,
                                            zIndex: element.zIndex,
                                            background:
                                                element.type === 'shape' ||
                                                element.type === 'button'
                                                    ? String(
                                                          element.props.fill ??
                                                              '#2563eb',
                                                      )
                                                    : 'transparent',
                                        }}
                                        onMouseDown={(event) => {
                                            if (!canEdit || element.locked) {
                                                return;
                                            }

                                            setSelectedId(element.id);
                                            drag.current = {
                                                id: element.id,
                                                offsetX:
                                                    event.nativeEvent.offsetX /
                                                    zoom,
                                                offsetY:
                                                    event.nativeEvent.offsetY /
                                                    zoom,
                                            };
                                        }}
                                    >
                                        {String(
                                            element.props.text ??
                                                element.props.label ??
                                                element.name,
                                        )}
                                    </button>
                                ))}
                        </div>
                    </div>
                    <aside className="w-[240px] shrink-0 space-y-3 overflow-auto border-l p-3 text-sm">
                        {selected ? (
                            <>
                                <Label>Name</Label>
                                <Input
                                    value={selected.name}
                                    disabled={!canEdit}
                                    onChange={(event) =>
                                        commit({
                                            ...document,
                                            elements: document.elements.map(
                                                (element) =>
                                                    element.id === selected.id
                                                        ? {
                                                              ...element,
                                                              name: event.target
                                                                  .value,
                                                          }
                                                        : element,
                                            ),
                                        })
                                    }
                                />
                                {(() => {
                                    const widget =
                                        widgets.find(
                                            (entry) =>
                                                entry.key === selected.type,
                                        ) ??
                                        (selected.type === 'chart'
                                            ? widgets.find(
                                                  (entry) =>
                                                      entry.key === 'charts',
                                              )
                                            : undefined);

                                    if (!widget) {
                                        return null;
                                    }

                                    return (
                                        <WidgetSchemaForm
                                            widget={widget}
                                            values={selected.props}
                                            disabled={!canEdit}
                                            onChange={(props) =>
                                                commit({
                                                    ...document,
                                                    elements:
                                                        document.elements.map(
                                                            (element) =>
                                                                element.id ===
                                                                selected.id
                                                                    ? {
                                                                          ...element,
                                                                          props,
                                                                      }
                                                                    : element,
                                                        ),
                                                })
                                            }
                                        />
                                    );
                                })()}
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    disabled={!canEdit}
                                    onClick={() => {
                                        commit({
                                            ...document,
                                            elements: document.elements.filter(
                                                (element) =>
                                                    element.id !== selected.id,
                                            ),
                                        });
                                        setSelectedId(null);
                                    }}
                                >
                                    Delete
                                </Button>
                            </>
                        ) : (
                            <p className="text-muted-foreground">
                                Select an element or add one from the toolbox.
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
