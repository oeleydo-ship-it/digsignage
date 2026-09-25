import { useEffect, useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';
import { DESIGN_ICONS, type DesignIconGroup } from '@/lib/design-icons';

export type GraphicInsert = {
    props?: Record<string, string | number | boolean | null>;
    width?: number;
    height?: number;
    name?: string;
    opacity?: number;
    /** Span the whole canvas behind everything else. */
    fullCanvas?: boolean;
};

type LibraryAsset = {
    key: string;
    label: string;
    src: string;
    width: number;
    height: number;
};

type Manifest = {
    backgrounds: LibraryAsset[];
    stickers: LibraryAsset[];
};

type Tab = 'backgrounds' | 'stickers' | 'icons' | 'shapes';

const TABS: { value: Tab; label: string }[] = [
    { value: 'backgrounds', label: 'Backgrounds' },
    { value: 'stickers', label: 'Stickers' },
    { value: 'icons', label: 'Icons' },
    { value: 'shapes', label: 'Shapes' },
];

const SHAPES: {
    label: string;
    width: number;
    height: number;
    props: Record<string, string | number>;
    opacity?: number;
}[] = [
    {
        label: 'Panel',
        width: 640,
        height: 360,
        props: { fill: '#0f172a', radius: 0 },
    },
    {
        label: 'Card',
        width: 560,
        height: 320,
        props: { fill: '#1e293b', radius: 28 },
    },
    {
        label: 'Pill',
        width: 480,
        height: 120,
        props: { fill: '#2563eb', radius: 60 },
    },
    {
        label: 'Circle',
        width: 280,
        height: 280,
        props: { fill: '#f59e0b', radius: 140 },
    },
    {
        label: 'Glass',
        width: 640,
        height: 360,
        props: { fill: '#ffffff', radius: 32 },
        opacity: 0.12,
    },
    {
        label: 'Scrim',
        width: 1920,
        height: 400,
        props: { fill: '#000000', radius: 0 },
        opacity: 0.45,
    },
    {
        label: 'Divider',
        width: 800,
        height: 6,
        props: { fill: '#ffffff', radius: 3 },
    },
    {
        label: 'Accent bar',
        width: 24,
        height: 320,
        props: { fill: '#38bdf8', radius: 12 },
    },
];

let manifestPromise: Promise<Manifest> | null = null;

/**
 * The manifest is written by `php artisan catalog:artwork`, so new artwork
 * and stickers appear here without a frontend release.
 */
function loadManifest(): Promise<Manifest> {
    manifestPromise ??= fetch('/images/library/manifest.json', {
        credentials: 'same-origin',
    })
        .then((response) =>
            response.ok ? (response.json() as Promise<Manifest>) : null,
        )
        .then((manifest) => manifest ?? { backgrounds: [], stickers: [] })
        .catch(() => {
            manifestPromise = null;

            return { backgrounds: [], stickers: [] };
        });

    return manifestPromise;
}

/**
 * Built-in imagery so layouts look designed without uploading anything.
 */
export default function DesignerGraphics({
    disabled = false,
    onInsert,
}: {
    disabled?: boolean;
    onInsert: (type: string, insert: GraphicInsert) => void;
}) {
    const [tab, setTab] = useState<Tab>('backgrounds');
    const [manifest, setManifest] = useState<Manifest | null>(null);
    const [query, setQuery] = useState('');

    useEffect(() => {
        let active = true;

        void loadManifest().then((loaded) => {
            if (active) {
                setManifest(loaded);
            }
        });

        return () => {
            active = false;
        };
    }, []);

    const needle = query.trim().toLowerCase();
    const matches = (label: string) =>
        needle === '' || label.toLowerCase().includes(needle);

    const iconGroups = useMemo(() => {
        const groups = new Map<DesignIconGroup, [string, string][]>();

        Object.entries(DESIGN_ICONS).forEach(([key, { label, group }]) => {
            if (!matches(label) && !matches(key)) {
                return;
            }

            groups.set(group, [...(groups.get(group) ?? []), [key, label]]);
        });

        return [...groups.entries()];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [needle]);

    const insertImage = (asset: LibraryAsset, fullCanvas: boolean) =>
        onInsert('image', {
            name: asset.label,
            fullCanvas,
            width: fullCanvas ? undefined : Math.min(asset.width, 640),
            height: fullCanvas
                ? undefined
                : Math.round(
                      Math.min(asset.width, 640) *
                          (asset.height / Math.max(1, asset.width)),
                  ),
            props: {
                src: asset.src,
                media_id: null,
                objectFit: fullCanvas ? 'cover' : 'contain',
            },
        });

    return (
        <div className="space-y-2">
            <div
                className="bg-muted grid grid-cols-2 gap-0.5 rounded-md p-0.5"
                role="tablist"
                aria-label="Graphics library"
            >
                {TABS.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        role="tab"
                        aria-selected={tab === option.value}
                        className={`min-w-0 truncate rounded px-2 py-1 text-xs font-medium ${
                            tab === option.value
                                ? 'bg-background shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                        onClick={() => setTab(option.value)}
                    >
                        {option.label}
                    </button>
                ))}
            </div>

            {tab !== 'shapes' && (
                <Input
                    value={query}
                    placeholder={`Search ${tab}`}
                    className="h-8 text-xs"
                    onChange={(event) => setQuery(event.target.value)}
                />
            )}

            {tab === 'backgrounds' && (
                <>
                    <p className="text-muted-foreground text-[11px]">
                        Click to fill the canvas behind everything.
                    </p>
                    <div className="grid grid-cols-2 gap-1.5">
                        {(manifest?.backgrounds ?? [])
                            .filter((asset) => matches(asset.label))
                            .map((asset) => (
                                <button
                                    key={asset.key}
                                    type="button"
                                    disabled={disabled}
                                    title={asset.label}
                                    aria-label={`Use ${asset.label} background`}
                                    className="group relative aspect-video overflow-hidden rounded border hover:ring-2 hover:ring-sky-500 disabled:opacity-50"
                                    onClick={() => insertImage(asset, true)}
                                >
                                    <img
                                        src={asset.src}
                                        alt=""
                                        loading="lazy"
                                        className="h-full w-full object-cover"
                                    />
                                </button>
                            ))}
                    </div>
                    {manifest && manifest.backgrounds.length === 0 && (
                        <EmptyLibrary />
                    )}
                </>
            )}

            {tab === 'stickers' && (
                <div className="grid grid-cols-2 gap-1.5">
                    {(manifest?.stickers ?? [])
                        .filter((asset) => matches(asset.label))
                        .map((asset) => (
                            <button
                                key={asset.key}
                                type="button"
                                disabled={disabled}
                                title={asset.label}
                                aria-label={`Add ${asset.label}`}
                                className="flex aspect-square items-center justify-center overflow-hidden rounded border bg-slate-800 p-2 hover:ring-2 hover:ring-sky-500 disabled:opacity-50"
                                onClick={() => insertImage(asset, false)}
                            >
                                <img
                                    src={asset.src}
                                    alt=""
                                    loading="lazy"
                                    className="max-h-full max-w-full object-contain"
                                />
                            </button>
                        ))}
                    {manifest && manifest.stickers.length === 0 && (
                        <EmptyLibrary />
                    )}
                </div>
            )}

            {tab === 'icons' && (
                <div className="space-y-2">
                    {iconGroups.map(([group, icons]) => (
                        <div key={group}>
                            <p className="text-muted-foreground pb-1 text-[11px] font-medium uppercase">
                                {group}
                            </p>
                            <div className="grid grid-cols-5 gap-1">
                                {icons.map(([key, label]) => {
                                    const { Icon } = DESIGN_ICONS[key];

                                    return (
                                        <button
                                            key={key}
                                            type="button"
                                            disabled={disabled}
                                            title={label}
                                            aria-label={`Add ${label} icon`}
                                            className="hover:bg-muted flex aspect-square items-center justify-center rounded border disabled:opacity-50"
                                            onClick={() =>
                                                onInsert('icon', {
                                                    name: `${label} icon`,
                                                    props: {
                                                        icon: key,
                                                        color: '#ffffff',
                                                        strokeWidth: 2,
                                                        background: '#2563eb',
                                                        badgeShape: 'circle',
                                                    },
                                                })
                                            }
                                        >
                                            <Icon className="size-4" />
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                    {iconGroups.length === 0 && (
                        <p className="text-muted-foreground text-xs">
                            No icons match.
                        </p>
                    )}
                </div>
            )}

            {tab === 'shapes' && (
                <div className="grid grid-cols-2 gap-1.5">
                    {SHAPES.map((shape) => (
                        <button
                            key={shape.label}
                            type="button"
                            disabled={disabled}
                            className="hover:bg-muted flex flex-col items-center gap-1 rounded border p-2 text-[11px] disabled:opacity-50"
                            onClick={() =>
                                onInsert('shape', {
                                    name: shape.label,
                                    width: shape.width,
                                    height: shape.height,
                                    opacity: shape.opacity,
                                    props: shape.props,
                                })
                            }
                        >
                            <span className="flex h-10 w-full items-center justify-center">
                                <span
                                    className="block"
                                    style={{
                                        background: String(shape.props.fill),
                                        opacity: shape.opacity ?? 1,
                                        width: `${Math.min(100, (shape.width / Math.max(shape.width, shape.height)) * 100)}%`,
                                        height: `${Math.min(100, (shape.height / Math.max(shape.width, shape.height)) * 100)}%`,
                                        minHeight: 3,
                                        minWidth: 3,
                                        borderRadius: Math.min(
                                            20,
                                            Number(shape.props.radius) / 6,
                                        ),
                                        outline:
                                            shape.props.fill === '#000000'
                                                ? '1px solid rgb(148 163 184)'
                                                : undefined,
                                    }}
                                />
                            </span>
                            {shape.label}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function EmptyLibrary() {
    return (
        <p className="text-muted-foreground col-span-2 text-xs">
            The graphics library is empty. Run{' '}
            <code className="font-mono">php artisan catalog:artwork</code>.
        </p>
    );
}
