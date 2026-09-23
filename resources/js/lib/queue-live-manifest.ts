import type { PlayerQueueUpdate } from '@/lib/player-echo';
import type { PlayerItem, PlayerManifest, PlayerPlaylist } from '@/lib/player-runtime';
import type { QueueVoiceRequest } from '@/lib/queue-voice';
import type { WidgetJsonValue, WidgetPayload } from '@/types';

type QueueRow = Record<string, WidgetJsonValue>;

function queueRows(value: WidgetJsonValue | undefined): QueueRow[] {
    return Array.isArray(value)
        ? value.filter((row): row is QueueRow => row !== null && typeof row === 'object' && !Array.isArray(row))
        : [];
}

function sameCounter(left: QueueRow, right: QueueRow): boolean {
    return left.counter_id != null && right.counter_id != null
        ? Number(left.counter_id) === Number(right.counter_id)
        : Boolean(left.counter && right.counter && left.counter === right.counter);
}

function preserveWidgetServing(previous: WidgetPayload | null | undefined, next: WidgetPayload | null | undefined, departed: Set<number>): WidgetPayload | null | undefined {
    if (!previous || !next || previous.key !== next.key
        || !['queue_now_serving', 'queue_board', 'queue_counter_number', 'queue_ticker'].includes(next.key)
        || ['service_id', 'location_id', 'counter_id'].some((key) => String(previous.settings[key] ?? '') !== String(next.settings[key] ?? ''))) {
        return next;
    }

    const incoming = queueRows(next.data.now_serving);
    const missing = queueRows(previous.data.now_serving).filter((row) =>
        !departed.has(Number(row.counter_id ?? 0))
        && !incoming.some((current) => current.id === row.id || sameCounter(current, row)),
    );
    if (missing.length === 0) return next;

    const serving = [...incoming, ...missing];
    const stats = next.data.stats && typeof next.data.stats === 'object' && !Array.isArray(next.data.stats)
        ? { ...next.data.stats, serving: Math.max(Number(next.data.stats.serving ?? 0), serving.length) }
        : next.data.stats;
    return {
        ...next,
        data: {
            ...next.data,
            now_serving: serving,
            ...(stats === undefined ? {} : { stats }),
            ticker: serving.map((row) => `${String(row.number ?? '')} → ${String(row.counter ?? 'Desk')}`).join('   •   '),
        },
    };
}

function preserveItemServing(previous: PlayerItem | undefined, next: PlayerItem, departed: Set<number>): PlayerItem {
    if (!previous || previous.id !== next.id) return next;
    const widget = preserveWidgetServing(previous.widget, next.widget, departed);
    const oldElements = Array.isArray(previous.document?.elements) ? previous.document.elements : [];
    const elements = Array.isArray(next.document?.elements) ? next.document.elements : null;
    const updatedElements = elements?.map((element, index) => {
        const old = oldElements[index];
        if (!old || !element || typeof old !== 'object' || typeof element !== 'object' || Array.isArray(old) || Array.isArray(element)) return element;
        if (old.id != null && element.id != null && old.id !== element.id) return element;
        const merged = preserveWidgetServing((old as { widget?: WidgetPayload }).widget, (element as { widget?: WidgetPayload }).widget, departed);
        return merged === (element as { widget?: WidgetPayload }).widget ? element : { ...element, widget: merged };
    });
    if (widget === next.widget && (!elements || updatedElements?.every((value, index) => value === elements[index]))) return next;
    return { ...next, widget, document: elements ? { ...next.document, elements: updatedElements } : next.document };
}

function preservePlaylistServing(previous: PlayerPlaylist | null, next: PlayerPlaylist | null, departed: Set<number>): PlayerPlaylist | null {
    if (!previous || !next || previous.id !== next.id) return next;
    const items = next.items.map((item) => preserveItemServing(previous.items.find((old) => old.id === item.id), item, departed));
    return items.every((item, index) => item === next.items[index]) ? next : { ...next, items };
}

/** Keep other counters visible while a queue event's fresh snapshot catches up. */
export function preserveQueueServingRows(previous: PlayerManifest, next: PlayerManifest, departed: Set<number> = new Set()): PlayerManifest {
    const playlist = preservePlaylistServing(previous.playback.playlist, next.playback.playlist, departed);
    const zones = next.playback.zones.map((zone) => {
        const old = previous.playback.zones.find((entry) => entry.name === zone.name);
        const merged = preservePlaylistServing(old?.playlist ?? null, zone.playlist, departed);
        return merged === zone.playlist ? zone : { ...zone, playlist: merged };
    });
    return playlist === next.playback.playlist && zones.every((zone, index) => zone === next.playback.zones[index])
        ? next : { ...next, playback: { ...next.playback, playlist, zones } };
}

function matches(settings: WidgetPayload['settings'], update: PlayerQueueUpdate): boolean {
    return (Number(settings.service_id ?? 0) === 0 || Number(settings.service_id) === update.service_id)
        && (Number(settings.location_id ?? 0) === 0 || Number(settings.location_id) === update.location_id)
        && (Number(settings.counter_id ?? 0) === 0 || Number(settings.counter_id) === update.counter_id);
}

function updateWidget(widget: WidgetPayload | null | undefined, update: PlayerQueueUpdate): WidgetPayload | null | undefined {
    if (!widget || !['queue_now_serving', 'queue_board', 'queue_counter_number', 'queue_recently_called', 'queue_ticker'].includes(widget.key)
        || !matches(widget.settings, update)
        || !update.ticket_id || !update.ticket_number || !update.counter_name) {
        return widget;
    }

    const existing = Array.isArray(widget.data.now_serving) ? widget.data.now_serving : [];
    const previousAtCounter = existing.find((value) => value && typeof value === 'object' && !Array.isArray(value)
        && (value.counter_id === update.counter_id || (value.counter_id == null && value.counter === update.counter_name)));
    const rows = existing.filter((value): value is Record<string, import('@/types').WidgetJsonValue> => {
        if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
        return value.id !== update.ticket_id
            && value.counter_id !== update.counter_id
            && !(value.counter_id == null && value.counter === update.counter_name);
    });
    const limit = Math.max(1, Math.min(20, Number(widget.settings.limit ?? 5) || 5));
    const called = {
        id: update.ticket_id,
        number: update.ticket_number,
        service: previousAtCounter && typeof previousAtCounter === 'object' && !Array.isArray(previousAtCounter)
            ? String(previousAtCounter.service ?? '') : '',
        counter: update.counter_name,
        counter_id: update.counter_id,
        status: 'called',
        called_at: update.called_at,
    };

    const recentlyCalled = Array.isArray(widget.data.recently_called) ? widget.data.recently_called : [];
    const recentRows = [called, ...recentlyCalled.filter((value) => value && typeof value === 'object' && !Array.isArray(value)
        && value.id !== update.ticket_id)].slice(0, limit);

    return {
        ...widget,
        data: {
            ...widget.data,
            now_serving: [called, ...rows].slice(0, limit),
            recently_called: recentRows,
            ticker: [called, ...rows].slice(0, limit).map((row) => `${String(row.number ?? '')} → ${String(row.counter ?? 'Desk')}`).join('   •   '),
            highlight_ticket_id: update.ticket_id,
        },
    };
}

function updateItem(item: PlayerItem, update: PlayerQueueUpdate): PlayerItem {
    const widget = updateWidget(item.widget, update);
    const document = item.document;
    const elements = Array.isArray(document?.elements) ? document.elements : null;

    if (!elements) return widget === item.widget ? item : { ...item, widget };

    let changed = widget !== item.widget;
    const nextElements = elements.map((element) => {
        if (!element || typeof element !== 'object' || Array.isArray(element)) return element;
        const entry = element as { widget?: WidgetPayload };
        const next = updateWidget(entry.widget, update);
        if (next === entry.widget) return element;
        changed = true;
        return { ...entry, widget: next };
    });

    return changed ? { ...item, widget, document: { ...document, elements: nextElements } } : item;
}

function updatePlaylist(playlist: PlayerPlaylist | null, update: PlayerQueueUpdate): PlayerPlaylist | null {
    if (!playlist) return null;
    const items = playlist.items.map((item) => updateItem(item, update));
    return items.some((item, index) => item !== playlist.items[index]) ? { ...playlist, items } : playlist;
}

/** Show a committed call immediately without removing tickets at other counters. */
export function applyQueueCallToManifest(manifest: PlayerManifest, update: PlayerQueueUpdate): PlayerManifest {
    if (update.status !== 'called' && update.status !== 'serving') return manifest;

    const playlist = updatePlaylist(manifest.playback.playlist, update);
    const zones = manifest.playback.zones.map((zone) => {
        const next = updatePlaylist(zone.playlist, update);
        return next === zone.playlist ? zone : { ...zone, playlist: next };
    });

    if (playlist === manifest.playback.playlist && zones.every((zone, index) => zone === manifest.playback.zones[index])) {
        return manifest;
    }

    return { ...manifest, playback: { ...manifest.playback, playlist, zones } };
}

/** Queue boards get a short REST fallback interval while Reverb is disconnected. */
export function manifestHasQueueWidgets(manifest: PlayerManifest | null): boolean {
    if (!manifest) return false;

    const playlists = [manifest.playback.playlist, ...manifest.playback.zones.map((zone) => zone.playlist)];

    return playlists.some((playlist) => playlist?.items.some((item) => {
        if (item.widget?.key.startsWith('queue_')) return true;
        const elements = Array.isArray(item.document?.elements) ? item.document.elements : [];
        return elements.some((element) => {
            if (!element || typeof element !== 'object' || Array.isArray(element)) return false;
            return (element as { widget?: WidgetPayload }).widget?.key.startsWith('queue_') === true;
        });
    }) === true);
}

function queueWidgets(manifest: PlayerManifest): WidgetPayload[] {
    const playlists = [manifest.playback.playlist, ...manifest.playback.zones.map((zone) => zone.playlist)];
    const widgets: WidgetPayload[] = [];

    for (const playlist of playlists) {
        for (const item of playlist?.items ?? []) {
            if (item.widget) widgets.push(item.widget);
            const elements = Array.isArray(item.document?.elements) ? item.document.elements : [];
            for (const element of elements) {
                if (!element || typeof element !== 'object' || Array.isArray(element)) continue;
                const widget = (element as { widget?: WidgetPayload }).widget;
                if (widget) widgets.push(widget);
            }
        }
    }

    return widgets;
}

/** Sound-only fallback for a new call discovered through REST manifest polling. */
export function queueSoundsFromManifest(previous: PlayerManifest, next: PlayerManifest, now = Date.now()): Array<{ key: string; request: QueueVoiceRequest }> {
    const seen = new Set<string>();
    for (const widget of queueWidgets(previous)) {
        for (const value of Array.isArray(widget.data.now_serving) ? widget.data.now_serving : []) {
            if (value && typeof value === 'object' && !Array.isArray(value)) seen.add(`${value.id}:${value.called_at}`);
        }
    }

    const calls: Array<{ key: string; request: QueueVoiceRequest }> = [];
    for (const widget of queueWidgets(next)) {
        if (widget.settings.sound !== true) continue;
        for (const value of Array.isArray(widget.data.now_serving) ? widget.data.now_serving : []) {
            if (!value || typeof value !== 'object' || Array.isArray(value)) continue;
            const { id, number, counter, called_at: calledAt } = value;
            if (typeof number !== 'string' || typeof counter !== 'string' || typeof calledAt !== 'string') continue;
            const age = now - Date.parse(calledAt);
            if (!Number.isFinite(age) || age < -5_000 || age > 30_000) continue;
            const key = `${id}:${calledAt}`;
            if (seen.has(key)) continue;
            seen.add(key);
            calls.push({ key, request: {
                ticketNumber: number,
                counterName: counter,
                soundOnly: true,
                settings: {
                    enabled: false, languages: ['en-US'], voice: null, speed: 1,
                    volume: 1, repeat_count: 1, chime: true, announcement_delay_seconds: 0,
                },
            } });
        }
    }

    return calls;
}
