import { Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';

type ElementOption = { value: string; label: string };

type Group = { title: string; keys: string[] };

/**
 * Palette groups, in display order. Keys missing here fall into "More", so a
 * newly registered widget still shows up without touching this list.
 */
const GROUPS: Group[] = [
    {
        title: 'Basics',
        keys: [
            'text',
            'image',
            'video',
            'shape',
            'button',
            'logo',
            'icon',
            'ticker',
            'qr_code',
        ],
    },
    {
        title: 'Time & weather',
        keys: [
            'clock',
            'analog_clock',
            'date',
            'world_clock',
            'countdown',
            'weather',
            'weather_forecast',
        ],
    },
    {
        title: 'Room booking',
        keys: ['room_status', 'room_board', 'booking', 'room_info'],
    },
    {
        title: 'Feeds & web',
        keys: [
            'rss',
            'news',
            'social_wall',
            'web_page',
            'iframe',
            'youtube',
            'live_stream',
            'json_api',
        ],
    },
    {
        title: 'Data & KPIs',
        keys: [
            'metric_tiles',
            'progress_goal',
            'gauge',
            'charts',
            'chart',
            'table',
            'safety_counter',
        ],
    },
    {
        title: 'Workplace & events',
        keys: [
            'calendar',
            'event_schedule',
            'celebrations',
            'directory',
            'menu_board',
            'alert_banner',
            'quote',
            'image_gallery',
        ],
    },
];

const QUEUE_PREFIX = 'queue_';

function shortLabel(option: ElementOption): string {
    return option.label.replace(/^Queue\s*[·.:-]\s*/u, '');
}

/**
 * Searchable, grouped element list for the design and template editors.
 * Labels wrap onto a second line instead of running into the next column.
 */
export default function DesignerPalette({
    elementTypes,
    disabled = false,
    draggable = false,
    onAdd,
}: {
    elementTypes: ElementOption[];
    disabled?: boolean;
    draggable?: boolean;
    onAdd: (type: string) => void;
}) {
    const [query, setQuery] = useState('');
    const needle = query.trim().toLowerCase();

    const sections = useMemo(() => {
        const byKey = new Map(
            elementTypes.map((option) => [option.value, option]),
        );
        const placed = new Set<string>();
        const result: { title: string; options: ElementOption[] }[] = [];

        for (const group of GROUPS) {
            const options = group.keys
                .map((key) => byKey.get(key))
                .filter(
                    (option): option is ElementOption => option !== undefined,
                );

            options.forEach((option) => placed.add(option.value));
            result.push({ title: group.title, options });
        }

        const queue = elementTypes.filter((option) =>
            option.value.startsWith(QUEUE_PREFIX),
        );
        queue.forEach((option) => placed.add(option.value));

        result.push({
            title: 'More',
            options: elementTypes.filter((option) => !placed.has(option.value)),
        });
        result.push({ title: 'Queue', options: queue });

        return result
            .map((section) => ({
                ...section,
                options:
                    needle === ''
                        ? section.options
                        : section.options.filter((option) =>
                              `${option.label} ${option.value.replaceAll('_', ' ')} ${section.title}`
                                  .toLowerCase()
                                  .includes(needle),
                          ),
            }))
            .filter((section) => section.options.length > 0);
    }, [elementTypes, needle]);

    return (
        <div className="space-y-3">
            <div className="relative px-1">
                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3.5 size-3.5 -translate-y-1/2" />
                <Input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Escape') {
                            setQuery('');
                        }

                        // Enter adds the first match, so keyboard users can search and insert.
                        if (event.key === 'Enter' && !disabled) {
                            const first = sections[0]?.options[0];

                            if (first) {
                                event.preventDefault();
                                onAdd(first.value);
                            }
                        }
                    }}
                    placeholder="Search widgets"
                    aria-label="Search widgets and elements"
                    data-test="palette-search"
                    className="h-8 pr-7 pl-8 text-xs"
                />
                {query !== '' && (
                    <button
                        type="button"
                        aria-label="Clear search"
                        className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2"
                        onClick={() => setQuery('')}
                    >
                        <X className="size-3.5" />
                    </button>
                )}
            </div>

            {sections.length === 0 ? (
                <p className="text-muted-foreground px-2 text-xs">
                    No widgets match “{query}”.
                </p>
            ) : (
                sections.map((section) => (
                    <section key={section.title} aria-label={section.title}>
                        <p className="text-muted-foreground px-2 pb-1.5 text-[11px] font-semibold tracking-wide uppercase">
                            {section.title}
                        </p>
                        <div className="grid grid-cols-2 gap-1">
                            {section.options.map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    title={option.label}
                                    data-test={`palette-${option.value}`}
                                    disabled={disabled}
                                    draggable={draggable && !disabled}
                                    onDragStart={(event) =>
                                        event.dataTransfer.setData(
                                            'application/x-design-element',
                                            option.value,
                                        )
                                    }
                                    onClick={() => onAdd(option.value)}
                                    className="hover:bg-accent hover:text-accent-foreground flex min-h-9 min-w-0 items-center rounded-md px-2 py-1.5 text-left text-xs leading-tight break-words hyphens-auto disabled:pointer-events-none disabled:opacity-50"
                                >
                                    {shortLabel(option)}
                                </button>
                            ))}
                        </div>
                    </section>
                ))
            )}
        </div>
    );
}
