import { describe, expect, it } from 'vitest';
import {
    clampWidgetFontSize,
    colorWithAlpha,
    isTransparentColor,
    widgetContainerFontSize,
    widgetFill,
    widgetFontSize,
    scaleWidgetFontToBox,
} from './widget-style';

describe('widget-style', () => {
    it('clamps font sizes to the readable range', () => {
        expect(clampWidgetFontSize(4)).toBe(8);
        expect(clampWidgetFontSize(240)).toBe(200);
        expect(clampWidgetFontSize('64')).toBe(64);
        expect(widgetFontSize({}, 48)).toBe(48);
        expect(widgetFontSize({ fontSize: 96 })).toBe(96);
    });

    it('scales inspector font size with the widget container', () => {
        expect(widgetContainerFontSize(32)).toBe(
            'max(8px, calc(32px * 100cqmin / 640))',
        );
        expect(widgetContainerFontSize(64)).toContain('64px');
        expect(scaleWidgetFontToBox(42, 640)).toBe(42);
        expect(scaleWidgetFontToBox(42, 960)).toBe(63);
    });

    it('treats missing ticker backgrounds as transparent', () => {
        expect(isTransparentColor(undefined)).toBe(true);
        expect(widgetFill({})).toBe('transparent');
        expect(widgetFill({ background: 'transparent' })).toBe('transparent');
        expect(widgetFill({ background: '#000000' })).toBe('#000000');
        expect(widgetFill({ background: '#000000', background_opacity: 40 })).toBe(
            'rgba(0, 0, 0, 0.4)',
        );
        expect(widgetFill({ background: '#000', background_opacity: 0 })).toBe(
            'transparent',
        );
    });

    it('converts hex colors to rgba', () => {
        expect(colorWithAlpha('#fff', 0.5)).toBe('rgba(255, 255, 255, 0.5)');
        expect(colorWithAlpha('#112233', 1)).toBe('#112233');
    });
});
