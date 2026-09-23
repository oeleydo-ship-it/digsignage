import { describe, expect, it } from 'vitest';

import { youtubeEmbedBlocked, youtubeEmbedSrc, youtubeId } from './youtube';

describe('youtubeId', () => {
    it('accepts a bare 11-character id', () => {
        expect(youtubeId('dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
    });

    it('parses watch URLs with and without protocol', () => {
        expect(youtubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ')).toBe(
            'dQw4w9WgXcQ',
        );
        expect(youtubeId('youtube.com/watch?v=dQw4w9WgXcQ&t=42')).toBe(
            'dQw4w9WgXcQ',
        );
    });

    it('parses share, embed, shorts, and live URLs', () => {
        expect(youtubeId('https://youtu.be/dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
        expect(youtubeId('https://youtu.be/dQw4w9WgXcQ?si=abc')).toBe(
            'dQw4w9WgXcQ',
        );
        expect(youtubeId('https://www.youtube.com/embed/dQw4w9WgXcQ')).toBe(
            'dQw4w9WgXcQ',
        );
        expect(youtubeId('https://www.youtube.com/shorts/dQw4w9WgXcQ')).toBe(
            'dQw4w9WgXcQ',
        );
        expect(youtubeId('https://www.youtube.com/live/dQw4w9WgXcQ')).toBe(
            'dQw4w9WgXcQ',
        );
    });

    it('supports mobile, music, and nocookie hosts', () => {
        expect(youtubeId('https://m.youtube.com/watch?v=dQw4w9WgXcQ')).toBe(
            'dQw4w9WgXcQ',
        );
        expect(youtubeId('https://music.youtube.com/watch?v=dQw4w9WgXcQ')).toBe(
            'dQw4w9WgXcQ',
        );
        expect(
            youtubeId('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'),
        ).toBe('dQw4w9WgXcQ');
    });

    it('rejects non-YouTube hosts and invalid input', () => {
        expect(youtubeId('')).toBeNull();
        expect(youtubeId('   ')).toBeNull();
        expect(youtubeId('https://vimeo.com/123456789')).toBeNull();
        expect(youtubeId('https://example.com/watch?v=dQw4w9WgXcQ')).toBeNull();
        expect(youtubeId('too-short')).toBeNull();
        expect(
            youtubeId('https://www.youtube.com/watch?v=too-short'),
        ).toBeNull();
        expect(youtubeId('not a url at all :://')).toBeNull();
    });
});

describe('youtubeEmbedSrc', () => {
    it('builds an autoplaying muted loop embed URL', () => {
        const src = youtubeEmbedSrc('dQw4w9WgXcQ');

        expect(src).toContain('https://www.youtube.com/embed/dQw4w9WgXcQ?');
        expect(src).toContain('autoplay=1');
        expect(src).toContain('mute=1');
        expect(src).toContain('loop=1');
        expect(src).toContain('playlist=dQw4w9WgXcQ');
        expect(src).toContain('rel=0');
    });

    it('adds the origin when provided', () => {
        const src = youtubeEmbedSrc('dQw4w9WgXcQ', 'https://player.example');

        expect(src).toContain('origin=https%3A%2F%2Fplayer.example');
        expect(src).toContain('widget_referrer=https%3A%2F%2Fplayer.example');
    });
});

describe('youtubeEmbedBlocked', () => {
    it('flags embedding-disabled and referrer errors only', () => {
        expect(youtubeEmbedBlocked(101)).toBe(true);
        expect(youtubeEmbedBlocked(150)).toBe(true);
        expect(youtubeEmbedBlocked(153)).toBe(true);
        expect(youtubeEmbedBlocked(100)).toBe(false);
        expect(youtubeEmbedBlocked(2)).toBe(false);
    });
});
