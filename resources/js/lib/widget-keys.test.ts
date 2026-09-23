import { describe, expect, it } from 'vitest';

import { canonicalWidgetKey, isWidgetType, WIDGET_KEYS } from './widget-keys';

describe('canonicalWidgetKey', () => {
    it('maps legacy aliases to canonical keys', () => {
        expect(canonicalWidgetKey('chart')).toBe('charts');
        expect(canonicalWidgetKey('iframe')).toBe('web_page');
    });

    it('passes through known keys unchanged', () => {
        expect(canonicalWidgetKey('weather')).toBe('weather');
        expect(canonicalWidgetKey('youtube')).toBe('youtube');
    });
});

describe('isWidgetType', () => {
    it('accepts every registered widget key', () => {
        for (const key of WIDGET_KEYS) {
            expect(isWidgetType(key)).toBe(true);
        }
    });

    it('accepts legacy aliases', () => {
        expect(isWidgetType('chart')).toBe(true);
        expect(isWidgetType('iframe')).toBe(true);
    });

    it('rejects unknown types', () => {
        expect(isWidgetType('hologram')).toBe(false);
        expect(isWidgetType('')).toBe(false);
    });
});
