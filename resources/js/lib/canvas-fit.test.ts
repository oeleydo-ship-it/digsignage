import { describe, expect, it } from 'vitest';
import { PLAYER_DESIGN_FIT, fitCanvasScale } from './canvas-fit';

describe('fitCanvasScale', () => {
    it('contains a landscape artboard on a matching 16:9 display', () => {
        expect(fitCanvasScale(1920, 1080, 1920, 1080, 'contain')).toBe(1);
        expect(fitCanvasScale(1280, 720, 1920, 1080, 'contain')).toBeCloseTo(
            1280 / 1920,
        );
    });

    it('letterboxes a portrait artboard on a landscape display', () => {
        expect(fitCanvasScale(1920, 1080, 1080, 1920, 'contain')).toBeCloseTo(
            1080 / 1920,
        );
        expect(fitCanvasScale(1920, 1080, 1080, 1920, 'cover')).toBeCloseTo(
            1920 / 1080,
        );
    });

    it('fits square and phone-sized canvases without using a 1280 fallback', () => {
        expect(fitCanvasScale(1920, 1080, 1080, 1080, 'contain')).toBe(1);
        expect(fitCanvasScale(720, 1280, 720, 1280, 'contain')).toBe(1);
        expect(fitCanvasScale(1080, 1920, 720, 1280, 'contain')).toBe(1.5);
    });

    it('does not crop when contain is used on a mismatched aspect ratio', () => {
        const scale = fitCanvasScale(1920, 1080, 1080, 1080, 'contain');
        expect(1080 * scale).toBeLessThanOrEqual(1920);
        expect(1080 * scale).toBeLessThanOrEqual(1080);
    });

    it('player designs contain the full artboard so ticker and clock are not cropped', () => {
        expect(PLAYER_DESIGN_FIT).toBe('contain');

        const landscape = fitCanvasScale(
            1920,
            1080,
            1920,
            1080,
            PLAYER_DESIGN_FIT,
        );
        expect(1920 * landscape).toBeLessThanOrEqual(1920);
        expect(1080 * landscape).toBeLessThanOrEqual(1080);

        const portrait = fitCanvasScale(
            1920,
            1080,
            1080,
            1920,
            PLAYER_DESIGN_FIT,
        );
        expect(1080 * portrait).toBeLessThanOrEqual(1920);
        expect(1920 * portrait).toBeLessThanOrEqual(1080);

        const squareOnWide = fitCanvasScale(
            1920,
            1080,
            1080,
            1080,
            PLAYER_DESIGN_FIT,
        );
        expect(1080 * squareOnWide).toBeLessThanOrEqual(1920);
        expect(1080 * squareOnWide).toBeLessThanOrEqual(1080);
        expect(squareOnWide).toBe(1);
    });

    it('player design contain maximizes scale to the live viewport', () => {
        expect(
            fitCanvasScale(1280, 720, 1920, 1080, PLAYER_DESIGN_FIT),
        ).toBeCloseTo(1280 / 1920);
        expect(fitCanvasScale(3840, 2160, 1920, 1080, PLAYER_DESIGN_FIT)).toBe(
            2,
        );
        expect(fitCanvasScale(720, 1280, 1080, 1920, PLAYER_DESIGN_FIT)).toBe(
            720 / 1080,
        );
    });

    it('covers a mismatched artboard using max scale without letterboxing', () => {
        const landscape = fitCanvasScale(1920, 1080, 1080, 1080, 'cover');
        expect(landscape).toBeCloseTo(1920 / 1080);
        expect(1080 * landscape).toBeGreaterThanOrEqual(1920);
        expect(1080 * landscape).toBeGreaterThanOrEqual(1080);

        const portraitOnLandscape = fitCanvasScale(
            1920,
            1080,
            1080,
            1920,
            'cover',
        );
        expect(portraitOnLandscape).toBeCloseTo(1920 / 1080);
        expect(1080 * portraitOnLandscape).toBeGreaterThanOrEqual(1920);
        expect(1920 * portraitOnLandscape).toBeGreaterThanOrEqual(1080);
    });
});
