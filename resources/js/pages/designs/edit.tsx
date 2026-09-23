import { Head, Link, router, usePage } from '@inertiajs/react';
import { uploadDesignerMedia } from '@/components/designer-image-upload';
import ContentWorkflowPanel from '@/components/content-workflow-panel';
import { useHydratedDocument } from '@/hooks/use-hydrated-document';
import { isWidgetType } from '@/lib/widget-keys';
import { useEffect, useMemo, useRef, useState } from 'react';
import ColorInput from '@/components/color-input';
import DesignerImageUpload from '@/components/designer-image-upload';
import { FullscreenCanvasPreview } from '@/components/canvas-preview';
import DesignerProperties from '@/components/designer-properties';
import KonvaDesigner from '@/components/konva-designer';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { edit as editRoute, index, show, update } from '@/routes/designs';
import { restore } from '@/routes/designs/revisions';
import { file as mediaFile } from '@/routes/media';
import { fromDesign } from '@/routes/templates';
import { resolve as resolveWidgets } from '@/routes/widgets';
import type {
    ContentApprovalPayload,
    DesignDocument,
    DesignDocumentElement,
    DesignPermissions,
    DesignRecord,
    WidgetDefinition,
} from '@/types';

type Revision = { id: number; version: number; created_at: string | null };
type Option = { value: string; label: string };
type DesignMedia = {
    id: number;
    name: string;
    type: 'image' | 'video';
    width: number | null;
    height: number | null;
    has_file: boolean;
    external_url: string | null;
};
type Props = {
    design: DesignRecord & { document: DesignDocument };
    revisions: Revision[];
    elementTypes: Option[];
    media?: DesignMedia[];
    permissions: DesignPermissions;
    approval: ContentApprovalPayload;
};

const MIN_SIZE = 20;
const cloneDocument = (document: DesignDocument): DesignDocument =>
    JSON.parse(JSON.stringify(document)) as DesignDocument;
const snap = (value: number, grid: number) =>
    Math.round(value / Math.max(1, grid)) * Math.max(1, grid);
const clamp = (value: number, minimum: number, maximum: number) =>
    Math.min(Math.max(value, minimum), Math.max(minimum, maximum));

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
    switch (type) {
        case 'text':
            return {
                text: 'Headline',
                fontSize: 48,
                color: '#ffffff',
                align: 'left',
            };
        case 'shape':
            return { fill: '#2563eb', radius: 8 };
        case 'button':
            return {
                text: 'Button',
                fill: '#2563eb',
                radius: 12,
                align: 'center',
                fontSize: 24,
            };
        case 'image':
        case 'logo':
            return { src: null, media_id: null, objectFit: 'cover' };
        case 'video':
            return {
                src: null,
                media_id: null,
                objectFit: 'cover',
                url: '',
            };
        case 'ticker':
            return { text: 'Breaking news — welcome visitors', speed: 40 };
        case 'clock':
        case 'date':
            return { format: type === 'clock' ? 'HH:mm' : 'PPP', fontSize: 48 };
        case 'weather':
            return {
                location: 'Dubai',
                units: 'celsius',
                label: 'Dubai · 28°C',
            };
        case 'qr_code':
            return { value: 'https://example.com' };
        case 'web_page':
        case 'iframe':
        case 'live_stream':
            return { url: 'https://example.com' };
        default:
            return { label: type.replaceAll('_', ' ') };
    }
}

export default function DesignEditor({
    design,
    revisions,
    elementTypes,
    media: serverMedia = [],
    permissions,
    approval,
}: Props) {
    const { currentTeam, widgets = [], mediaPermissions } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const canEdit = permissions.canUpdateDesign && !approval.locked;
    const canUpload = canEdit && Boolean(mediaPermissions?.canCreateMedia);
    const [uploadedMedia, setUploadedMedia] = useState<DesignMedia[]>([]);
    const media = [
        ...uploadedMedia,
        ...serverMedia.filter(
            (item) => !uploadedMedia.some((upload) => upload.id === item.id),
        ),
    ];
    const [uploading, setUploading] = useState(false);
    const uploadBusy = useRef(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [name, setName] = useState(design.name);
    const [document, setDocument] = useState<DesignDocument>(design.document);
    const documentRef = useRef(document);
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [zoom, setZoom] = useState(0.35);
    const userZoomed = useRef(false);
    const [grid, setGrid] = useState(20);
    const [preview, setPreview] = useState(false);
    const [saveState, setSaveState] = useState('Saved');
    const workspace = useRef<HTMLDivElement>(null);
    const [history, setHistory] = useState<DesignDocument[]>([
        cloneDocument(design.document),
    ]);
    const [historyIndex, setHistoryIndex] = useState(0);
    const historyIndexRef = useRef(historyIndex);
    historyIndexRef.current = historyIndex;
    const autosave = useRef<number | null>(null);
    const skipAutosave = useRef(true);

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
        if (!canEdit) return;
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

    const save = (next = documentRef.current, after?: () => void) => {
        if (!canEdit) {
            after?.();
            return;
        }
        if (autosave.current) window.clearTimeout(autosave.current);
        setSaveState('Saving…');
        router.patch(
            update.url({ current_team: slug, design: design.id }),
            { name, document: next, width: next.width, height: next.height },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setSaveState('Saved');
                    after?.();
                },
                onError: () => setSaveState('Save failed — retry'),
            },
        );
    };

    const publish = () => {
        const release = () => {
            router.post(
                `/${slug}/approvals/design/${design.id}/publish`,
                {},
                { preserveScroll: true, preserveState: true },
            );
        };

        save(documentRef.current, release);
    };

    const fitCanvas = (fromUser = false) => {
        if (fromUser) userZoomed.current = false;
        const bounds = workspace.current?.getBoundingClientRect();
        if (!bounds) return;
        const padding = 24;
        const next = Math.min(
            (bounds.width - padding) / documentRef.current.width,
            (bounds.height - padding) / documentRef.current.height,
            1,
        );
        setZoom(Math.max(0.1, next));
    };

    useEffect(() => {
        const node = workspace.current;
        if (!node) return;
        const observer = new ResizeObserver(() => {
            if (!userZoomed.current) fitCanvas();
        });
        observer.observe(node);
        fitCanvas();
        return () => observer.disconnect();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!canEdit) return;
        if (skipAutosave.current) {
            skipAutosave.current = false;
            return;
        }
        if (autosave.current) window.clearTimeout(autosave.current);
        autosave.current = window.setTimeout(() => {
            save();
        }, 1500);
        return () => {
            if (autosave.current) window.clearTimeout(autosave.current);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [document, name, canEdit]);

    const mediaSource = (item: DesignMedia): string | null =>
        item.has_file
            ? mediaFile.url({ current_team: slug, media: item.id })
            : item.external_url;

    const addElement = (
        type: string,
        position?: { x: number; y: number },
        mediaItem?: DesignMedia,
    ) => {
        const document = documentRef.current;
        if (!canEdit || document.elements.length >= 200) return;
        const naturalWidth =
            mediaItem?.width ?? (type === 'ticker' ? 800 : 320);
        const naturalHeight =
            mediaItem?.height ?? (type === 'ticker' ? 80 : 180);
        const width = Math.max(
            MIN_SIZE,
            Math.min(document.width * 0.5, naturalWidth),
        );
        const height = Math.max(
            MIN_SIZE,
            mediaItem
                ? width * (naturalHeight / Math.max(1, naturalWidth))
                : naturalHeight,
        );
        const element: DesignDocumentElement = {
            id: crypto.randomUUID(),
            type,
            name: mediaItem?.name ?? type.replaceAll('_', ' '),
            x: clamp(snap(position?.x ?? 80, grid), 0, document.width - width),
            y: clamp(
                snap(position?.y ?? 80, grid),
                0,
                document.height - height,
            ),
            width: Math.min(width, document.width),
            height: Math.min(height, document.height),
            rotation: 0,
            opacity: 1,
            zIndex:
                Math.max(0, ...document.elements.map((item) => item.zIndex)) +
                1,
            locked: false,
            hidden: false,
            props: mediaItem
                ? {
                      ...defaultProps(type, widgets),
                      media_id: mediaItem.id,
                      src: mediaSource(mediaItem),
                  }
                : defaultProps(type, widgets),
        };
        commit({ ...document, elements: [...document.elements, element] });
        setSelectedId(element.id);
    };

    const insertMedia = (
        item: DesignMedia,
        position?: { x: number; y: number },
        targetId = selectedId,
    ) => {
        if (!canEdit) return;
        const current = documentRef.current;
        const target = current.elements.find(
            (element) => element.id === targetId,
        );
        if (
            !position &&
            target &&
            !target.locked &&
            !target.props.src &&
            !target.props.media_id &&
            (target.type === item.type ||
                (target.type === 'logo' && item.type === 'image'))
        ) {
            commit({
                ...current,
                elements: current.elements.map((element) =>
                    element.id === target.id
                        ? {
                              ...element,
                              name: item.name,
                              props: {
                                  ...element.props,
                                  media_id: item.id,
                                  src: mediaSource(item),
                              },
                          }
                        : element,
                ),
            });
            setSelectedId(target.id);
        } else {
            addElement(item.type, position, item);
        }
    };

    const uploadImage = async (
        file: File,
        position?: { x: number; y: number },
    ) => {
        if (uploadBusy.current) return;
        if (!canUpload) {
            setUploadError('You do not have permission to upload media.');
            return;
        }
        if (documentRef.current.elements.length >= 200) {
            setUploadError(
                'The canvas is full. Remove an element before uploading.',
            );
            return;
        }
        const targetId = selectedId;
        uploadBusy.current = true;
        setUploading(true);
        setUploadError(null);
        try {
            const uploaded = await uploadDesignerMedia(file, slug);
            if (uploaded.type !== 'image' && uploaded.type !== 'video') {
                throw new Error('Upload an image or a video file.');
            }
            const item: DesignMedia = {
                ...uploaded,
                type: uploaded.type,
            };
            setUploadedMedia((current) => [item, ...current]);
            insertMedia(item, position, targetId);
        } catch (error) {
            setUploadError(
                error instanceof Error ? error.message : 'Media upload failed.',
            );
        } finally {
            uploadBusy.current = false;
            setUploading(false);
        }
    };

    const updateSelected = (patch: Partial<DesignDocumentElement>) => {
        if (
            !canEdit ||
            !selected ||
            (selected.locked &&
                patch.locked === undefined &&
                patch.hidden === undefined)
        )
            return;
        const nextElement = { ...selected, ...patch };
        nextElement.width = clamp(nextElement.width, MIN_SIZE, document.width);
        nextElement.height = clamp(
            nextElement.height,
            MIN_SIZE,
            document.height,
        );
        nextElement.x = clamp(
            nextElement.x,
            0,
            document.width - nextElement.width,
        );
        nextElement.y = clamp(
            nextElement.y,
            0,
            document.height - nextElement.height,
        );
        nextElement.opacity = clamp(nextElement.opacity, 0, 1);
        commit({
            ...document,
            elements: document.elements.map((element) =>
                element.id === selected.id ? nextElement : element,
            ),
        });
    };

    const useAsCanvasBackground = () => {
        if (!selected) {
            return;
        }

        const lowest = Math.min(
            ...document.elements.map((item) => item.zIndex),
        );

        updateSelected({
            x: 0,
            y: 0,
            width: document.width,
            height: document.height,
            rotation: 0,
            zIndex: lowest - 1,
            props: {
                ...selected.props,
                objectFit: 'cover',
            },
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

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                event.target instanceof HTMLElement &&
                event.target.closest(
                    'input, textarea, select, [contenteditable]',
                )
            )
                return;
            if (canEdit && (event.ctrlKey || event.metaKey)) {
                if (event.key.toLowerCase() === 'z') {
                    event.preventDefault();
                    if (event.shiftKey) redo();
                    else undo();
                    return;
                }
                if (event.key.toLowerCase() === 's') {
                    event.preventDefault();
                    save();
                    return;
                }
            }
            if (
                !selected ||
                !canEdit ||
                selected.locked ||
                event.target instanceof HTMLInputElement ||
                event.target instanceof HTMLTextAreaElement
            )
                return;
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

    const standardElementTypes = elementTypes.filter(
        (type) => !type.value.startsWith('queue_'),
    );
    const queueElementTypes = elementTypes.filter((type) =>
        type.value.startsWith('queue_'),
    );

    return (
        <>
            <Head title={`${design.name} designer`}>
                <meta head-key="referrer" name="referrer" content="origin" />
            </Head>
            <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
                <div className="flex shrink-0 flex-wrap items-center gap-1.5 border-b px-2 py-1.5">
                    <Input
                        value={name}
                        className="h-8 max-w-[10rem]"
                        disabled={!canEdit}
                        onChange={(event) => setName(event.target.value)}
                    />
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                size="sm"
                                variant="outline"
                                className="h-8 max-w-[14rem] shrink-0 text-xs"
                                aria-label="Version history"
                            >
                                v{design.version}{' '}
                                {design.status_label ?? design.status} ·{' '}
                                {saveState}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="start"
                            className="max-h-80 overflow-auto"
                        >
                            <DropdownMenuLabel>
                                Restore revision
                            </DropdownMenuLabel>
                            {revisions.length === 0 && (
                                <DropdownMenuItem disabled>
                                    No revisions yet
                                </DropdownMenuItem>
                            )}
                            {revisions.map((revision) => (
                                <DropdownMenuItem
                                    key={revision.id}
                                    disabled={!canEdit}
                                    onSelect={() =>
                                        router.post(
                                            restore.url({
                                                current_team: slug,
                                                design: design.id,
                                                revision: revision.id,
                                            }),
                                        )
                                    }
                                >
                                    v{revision.version}
                                    {revision.created_at
                                        ? ` · ${revision.created_at}`
                                        : ''}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <span role="status" className="sr-only">
                        {saveState}
                    </span>
                    {canEdit && (
                        <Button
                            size="sm"
                            variant="outline"
                            className="h-8"
                            onClick={() => save()}
                            data-test="save-design"
                        >
                            Save draft
                        </Button>
                    )}
                    {approval.can_publish && (
                        <Button
                            size="sm"
                            className="h-8"
                            onClick={publish}
                            data-test="publish-design"
                        >
                            Publish
                        </Button>
                    )}
                    {usePage().props.templatePermissions?.canCreateTemplate && (
                        <Button
                            size="sm"
                            variant="outline"
                            className="h-8"
                            data-test="save-as-template"
                            onClick={() => {
                                const templateName = window.prompt(
                                    'Template name',
                                    name,
                                );
                                if (templateName)
                                    router.post(
                                        fromDesign.url({
                                            current_team: slug,
                                            design: design.id,
                                        }),
                                        {
                                            name: templateName,
                                            category: 'corporate',
                                            document,
                                        },
                                    );
                            }}
                        >
                            Save as template
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
                    <Button
                        size="sm"
                        variant="outline"
                        className="h-8"
                        onClick={() => setPreview(true)}
                    >
                        Preview
                    </Button>
                    <Button size="sm" variant="outline" className="h-8" asChild>
                        <Link
                            href={show.url({
                                current_team: slug,
                                design: design.id,
                            })}
                        >
                            Full preview
                        </Link>
                    </Button>
                    <ContentWorkflowPanel
                        approval={approval}
                        hidePublish
                        compact
                    />
                </div>

                <div className="flex min-h-0 min-w-0 flex-1">
                    <aside className="flex w-[240px] shrink-0 flex-col overflow-hidden border-r">
                        <div className="min-h-0 flex-1 overflow-y-auto p-2">
                            <p className="text-muted-foreground px-2 pb-2 text-xs font-medium uppercase">
                                Elements · drag to canvas
                            </p>
                            <div className="grid grid-cols-2 gap-1">
                                {standardElementTypes.map((type) => (
                                    <Button
                                        key={type.value}
                                        variant="ghost"
                                        className="h-9 justify-start px-2 text-xs"
                                        disabled={!canEdit}
                                        draggable={canEdit}
                                        onDragStart={(event) =>
                                            event.dataTransfer.setData(
                                                'application/x-design-element',
                                                type.value,
                                            )
                                        }
                                        onClick={() => addElement(type.value)}
                                    >
                                        {type.label}
                                    </Button>
                                ))}
                            </div>
                            {queueElementTypes.length > 0 && (
                                <>
                                    <p className="text-muted-foreground mt-5 px-2 pb-2 text-xs font-medium uppercase">
                                        Queue
                                    </p>
                                    <div className="grid grid-cols-2 gap-1">
                                        {queueElementTypes.map((type) => (
                                            <Button
                                                key={type.value}
                                                variant="ghost"
                                                className="h-auto min-h-9 justify-start px-2 py-2 text-left text-xs whitespace-normal"
                                                disabled={!canEdit}
                                                draggable={canEdit}
                                                onDragStart={(event) =>
                                                    event.dataTransfer.setData(
                                                        'application/x-design-element',
                                                        type.value,
                                                    )
                                                }
                                                onClick={() =>
                                                    addElement(type.value)
                                                }
                                            >
                                                {type.label.replace(
                                                    'Queue · ',
                                                    '',
                                                )}
                                            </Button>
                                        ))}
                                    </div>
                                </>
                            )}
                            <p className="text-muted-foreground mt-5 px-2 pb-2 text-xs font-medium uppercase">
                                Media library
                            </p>
                            <DesignerImageUpload
                                disabled={!canUpload}
                                uploading={uploading}
                                onFile={(file) => void uploadImage(file)}
                            />
                            {uploadError && (
                                <p
                                    role="alert"
                                    className="text-destructive mb-2 px-2 text-xs"
                                >
                                    {uploadError}
                                </p>
                            )}
                            <div className="space-y-1">
                                {media.map((item) => (
                                    <button
                                        key={item.id}
                                        type="button"
                                        draggable={canEdit}
                                        className="hover:bg-muted flex w-full items-center gap-2 rounded-md p-2 text-left text-xs"
                                        onDragStart={(event) =>
                                            event.dataTransfer.setData(
                                                'application/x-design-media',
                                                JSON.stringify(item),
                                            )
                                        }
                                        onClick={() => insertMedia(item)}
                                    >
                                        <span className="bg-muted flex h-9 w-12 shrink-0 items-center justify-center overflow-hidden rounded">
                                            {mediaSource(item) ? (
                                                item.type === 'video' ? (
                                                    <video
                                                        src={
                                                            mediaSource(item) ??
                                                            undefined
                                                        }
                                                        muted
                                                        playsInline
                                                        className="h-full w-full object-cover"
                                                    />
                                                ) : (
                                                    <img
                                                        src={
                                                            mediaSource(item) ??
                                                            undefined
                                                        }
                                                        alt=""
                                                        className="h-full w-full object-cover"
                                                        draggable={false}
                                                    />
                                                )
                                            ) : (
                                                item.type
                                                    .slice(0, 1)
                                                    .toUpperCase()
                                            )}
                                        </span>
                                        <span className="truncate">
                                            {item.name}
                                        </span>
                                    </button>
                                ))}
                                {media.length === 0 && (
                                    <p className="text-muted-foreground px-2 text-xs">
                                        Upload an image or video, or drop a file
                                        onto the canvas.
                                    </p>
                                )}
                            </div>
                        </div>
                    </aside>

                    <div
                        className="min-h-0 min-w-0 flex-1 touch-none overflow-auto bg-slate-200/70 p-3"
                        ref={workspace}
                        onDragOver={(event) => event.preventDefault()}
                        onDrop={(event) => {
                            event.preventDefault();
                            const canvas =
                                event.currentTarget.querySelector(
                                    '[data-canvas]',
                                );
                            if (!(canvas instanceof HTMLElement)) return;
                            const bounds = canvas.getBoundingClientRect();
                            const position = {
                                x: (event.clientX - bounds.left) / zoom,
                                y: (event.clientY - bounds.top) / zoom,
                            };
                            if (event.dataTransfer.files.length) {
                                if (event.dataTransfer.files.length > 1) {
                                    setUploadError('Drop one file at a time.');
                                    return;
                                }
                                void uploadImage(
                                    event.dataTransfer.files[0],
                                    position,
                                );
                                return;
                            }
                            const mediaData = event.dataTransfer.getData(
                                'application/x-design-media',
                            );
                            if (mediaData) {
                                try {
                                    const payload = JSON.parse(mediaData) as {
                                        id?: number;
                                    };
                                    const item = media.find(
                                        (entry) => entry.id === payload.id,
                                    );
                                    if (item)
                                        addElement(item.type, position, item);
                                } catch {
                                    /* Ignore drops from other applications. */
                                }
                                return;
                            }
                            const type = event.dataTransfer.getData(
                                'application/x-design-element',
                            );
                            if (
                                elementTypes.some(
                                    (entry) => entry.value === type,
                                )
                            )
                                addElement(type, position);
                        }}
                        onPointerDown={(event) => {
                            if (
                                event.target === event.currentTarget ||
                                (event.target as HTMLElement).hasAttribute(
                                    'data-canvas',
                                )
                            )
                                setSelectedId(null);
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
                                    commit({
                                        ...document,
                                        background,
                                    })
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
                        {selected && (
                            <>
                                <DesignerProperties
                                    element={selected}
                                    disabled={!canEdit || selected.locked}
                                    widgets={widgets}
                                    onChange={(props) =>
                                        updateSelected({ props })
                                    }
                                />
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
                                                document.width - selected.width,
                                            ),
                                            y: clamp(
                                                selected.y + 20,
                                                0,
                                                document.height -
                                                    selected.height,
                                            ),
                                            zIndex:
                                                Math.max(
                                                    0,
                                                    ...ordered.map(
                                                        (item) => item.zIndex,
                                                    ),
                                                ) + 1,
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
                                    Duplicate element
                                </Button>
                            </>
                        )}
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
                                {typeof selected.props.text === 'string' &&
                                    !isWidgetType(selected.type) && (
                                        <>
                                            <Label>Text</Label>
                                            <Input
                                                value={String(
                                                    selected.props.text,
                                                )}
                                                disabled={
                                                    !canEdit || selected.locked
                                                }
                                                onChange={(event) =>
                                                    updateSelected({
                                                        props: {
                                                            ...selected.props,
                                                            text: event.target
                                                                .value,
                                                        },
                                                    })
                                                }
                                            />
                                        </>
                                    )}
                                {(selected.type === 'image' ||
                                    selected.type === 'logo' ||
                                    selected.type === 'video') && (
                                    <>
                                        <Label>Media source</Label>
                                        <select
                                            className="border-input bg-background h-9 w-full rounded-md border px-2"
                                            value={String(
                                                selected.props.media_id ?? '',
                                            )}
                                            disabled={
                                                !canEdit || selected.locked
                                            }
                                            onChange={(event) => {
                                                const item = media.find(
                                                    (entry) =>
                                                        entry.id ===
                                                        Number(
                                                            event.target.value,
                                                        ),
                                                );
                                                updateSelected({
                                                    props: {
                                                        ...selected.props,
                                                        media_id:
                                                            item?.id ?? null,
                                                        src: item
                                                            ? mediaSource(item)
                                                            : null,
                                                    },
                                                });
                                            }}
                                        >
                                            <option value="">
                                                Choose media
                                            </option>
                                            {media
                                                .filter((item) =>
                                                    selected.type === 'video'
                                                        ? item.type === 'video'
                                                        : item.type === 'image',
                                                )
                                                .map((item) => (
                                                    <option
                                                        key={item.id}
                                                        value={item.id}
                                                    >
                                                        {item.name}
                                                    </option>
                                                ))}
                                        </select>
                                        {selected.type === 'video' && (
                                            <>
                                                <Label>Video fit</Label>
                                                <select
                                                    className="border-input bg-background h-9 w-full rounded-md border px-2"
                                                    value={String(selected.props.objectFit ?? 'cover')}
                                                    disabled={!canEdit || selected.locked}
                                                    onChange={(event) =>
                                                        updateSelected({
                                                            props: {
                                                                ...selected.props,
                                                                objectFit: event.target.value,
                                                            },
                                                        })
                                                    }
                                                >
                                                    <option value="cover">Fill block (crop)</option>
                                                    <option value="contain">Fit inside block</option>
                                                </select>
                                            </>
                                        )}
                                        {(selected.type === 'image' ||
                                            selected.type === 'logo') && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                disabled={
                                                    !canEdit || selected.locked
                                                }
                                                onClick={useAsCanvasBackground}
                                            >
                                                Use as canvas background
                                            </Button>
                                        )}
                                    </>
                                )}
                                {(typeof selected.props.url === 'string' ||
                                    [
                                        'iframe',
                                        'web_page',
                                        'live_stream',
                                        'video',
                                    ].includes(selected.type)) &&
                                    !isWidgetType(selected.type) && (
                                        <>
                                            <Label>
                                                {selected.type === 'video'
                                                    ? 'Video URL'
                                                    : 'URL'}
                                            </Label>
                                            <Input
                                                value={String(
                                                    selected.props.url ?? '',
                                                )}
                                                disabled={
                                                    !canEdit || selected.locked
                                                }
                                                onChange={(event) =>
                                                    updateSelected({
                                                        props: {
                                                            ...selected.props,
                                                            url: event.target
                                                                .value,
                                                        },
                                                    })
                                                }
                                            />
                                        </>
                                    )}
                                <div className="grid grid-cols-2 gap-2">
                                    {(
                                        [
                                            'x',
                                            'y',
                                            'width',
                                            'height',
                                            'rotation',
                                            'opacity',
                                        ] as const
                                    ).map((key) => (
                                        <div key={key}>
                                            <Label className="capitalize">
                                                {key}
                                            </Label>
                                            <Input
                                                type="number"
                                                step={
                                                    key === 'opacity' ? 0.1 : 1
                                                }
                                                value={selected[key]}
                                                disabled={
                                                    !canEdit || selected.locked
                                                }
                                                onChange={(event) =>
                                                    updateSelected({
                                                        [key]: Number(
                                                            event.target.value,
                                                        ),
                                                    })
                                                }
                                            />
                                        </div>
                                    ))}
                                </div>
                                <Label>Layer</Label>
                                <div className="grid grid-cols-2 gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        disabled={!canEdit || selected.locked}
                                        onClick={() =>
                                            updateSelected({
                                                zIndex:
                                                    Math.min(
                                                        ...ordered.map(
                                                            (item) =>
                                                                item.zIndex,
                                                        ),
                                                    ) - 1,
                                            })
                                        }
                                    >
                                        Send back
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        disabled={!canEdit || selected.locked}
                                        onClick={() =>
                                            updateSelected({
                                                zIndex:
                                                    Math.max(
                                                        ...ordered.map(
                                                            (item) =>
                                                                item.zIndex,
                                                        ),
                                                    ) + 1,
                                            })
                                        }
                                    >
                                        Bring front
                                    </Button>
                                </div>
                                <div className="flex flex-wrap gap-2">
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
                                <p className="text-muted-foreground text-xs">
                                    Drag the block to move it. Drag a corner
                                    handle to resize it or the top handle to
                                    rotate. Use image crop controls to adjust
                                    the picture. Use arrow keys to nudge, Shift
                                    for larger steps, and Ctrl+Z to undo.
                                </p>
                            </>
                        ) : (
                            <p className="text-muted-foreground">
                                Select an element to edit properties.
                            </p>
                        )}
                    </aside>
                </div>
            </div>
            {preview && (
                <div role="dialog" aria-label="Design preview">
                    <FullscreenCanvasPreview
                        document={hydrated}
                        timezone={timezone}
                        onClose={() => setPreview(false)}
                    />
                </div>
            )}
        </>
    );
}

DesignEditor.layout = (props: {
    currentTeam?: { slug: string } | null;
    design?: { id: number; name: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Designer',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
        {
            title: props.design?.name ?? 'Edit',
            href:
                props.currentTeam && props.design
                    ? editRoute.url({
                          current_team: props.currentTeam.slug,
                          design: props.design.id,
                      })
                    : '/',
        },
    ],
});
