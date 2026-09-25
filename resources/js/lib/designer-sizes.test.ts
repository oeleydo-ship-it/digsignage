import { describe, expect, it } from 'vitest';
import { defaultElementSize } from '@/lib/designer-sizes';

describe('defaultElementSize', () => {
    it('uses the natural size when it fits', () => {
        expect(defaultElementSize('room_status', 1920, 1080)).toEqual({
            width: 1280,
            height: 720,
        });
    });

    it('scales proportionally to fit a portrait canvas', () => {
        const size = defaultElementSize('room_status', 1080, 1920);

        expect(size?.width).toBe(972);
        expect(size?.height).toBe(Math.round(720 * (972 / 1280)));
    });

    it('returns null for types without a natural size', () => {
        expect(defaultElementSize('text', 1920, 1080)).toBeNull();
    });
});
