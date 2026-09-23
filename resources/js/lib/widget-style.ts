export const MIN_WIDGET_FONT_SIZE = 8;
export const MAX_WIDGET_FONT_SIZE = 200;

export function clampWidgetFontSize(
    value: unknown,
    fallback = 48,
): number {
    const parsed = Number(value ?? fallback);

    if (!Number.isFinite(parsed)) {
        return fallback;
    }

    return Math.min(
        MAX_WIDGET_FONT_SIZE,
        Math.max(MIN_WIDGET_FONT_SIZE, parsed),
    );
}

export function widgetFontSize(
    settings: Record<string, unknown>,
    fallback = 48,
): number {
    return clampWidgetFontSize(settings.fontSize, fallback);
}

/** Inspector pixels are authored for an ~640px widget min-edge. */
export const WIDGET_FONT_REFERENCE_CQMIN = 640;

export function widgetContainerFontSize(px: number): string {
    const n = Number.isFinite(px) ? px : 32;

    return `max(${MIN_WIDGET_FONT_SIZE}px, calc(${n}px * 100cqmin / ${WIDGET_FONT_REFERENCE_CQMIN}))`;
}

export function scaleWidgetFontToBox(
    px: number,
    minEdge: number,
    reference = WIDGET_FONT_REFERENCE_CQMIN,
): number {
    const base = Number.isFinite(px) ? px : 32;

    if (!Number.isFinite(minEdge) || minEdge <= 0) {
        return clampWidgetFontSize(base);
    }

    return clampWidgetFontSize((base * minEdge) / reference);
}

export function isTransparentColor(value: unknown): boolean {
    if (value === undefined || value === null || value === '') {
        return true;
    }

    const color = String(value).trim().toLowerCase();

    return color === 'transparent' || color === 'none';
}

function hexToRgb(value: string): { r: number; g: number; b: number } | null {
    const hex = value.trim();
    const short = /^#([0-9a-fA-F]{3})$/.exec(hex);
    const long = /^#([0-9a-fA-F]{6})$/.exec(hex);

    if (short) {
        const [r, g, b] = short[1].split('');

        return {
            r: Number.parseInt(`${r}${r}`, 16),
            g: Number.parseInt(`${g}${g}`, 16),
            b: Number.parseInt(`${b}${b}`, 16),
        };
    }

    if (long) {
        return {
            r: Number.parseInt(long[1].slice(0, 2), 16),
            g: Number.parseInt(long[1].slice(2, 4), 16),
            b: Number.parseInt(long[1].slice(4, 6), 16),
        };
    }

    return null;
}

export function colorWithAlpha(color: string, alpha: number): string {
    const clamped = Math.min(1, Math.max(0, alpha));
    const rgb = hexToRgb(color);

    if (!rgb) {
        return clamped >= 1 ? color : 'transparent';
    }

    if (clamped <= 0) {
        return 'transparent';
    }

    if (clamped >= 1) {
        return color.startsWith('#') && color.length === 7 ? color : `#${color.replace('#', '')}`;
    }

    return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${clamped})`;
}

export function widgetFill(
    settings: Record<string, unknown>,
    colorKey = 'background',
    opacityKey = 'background_opacity',
): string {
    const raw = settings[colorKey];

    if (isTransparentColor(raw)) {
        return 'transparent';
    }

    const opacityRaw = settings[opacityKey];
    const alpha =
        opacityRaw === undefined || opacityRaw === null || opacityRaw === ''
            ? 1
            : Number(opacityRaw) / 100;

    if (!Number.isFinite(alpha)) {
        return String(raw);
    }

    return colorWithAlpha(String(raw), alpha);
}
