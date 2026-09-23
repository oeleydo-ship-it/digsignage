import { describe, expect, it } from 'vitest';

import {
    iframeAllowsEmbedding,
    isSameOriginUrl,
    webpageSandbox,
} from './embed';

describe('isSameOriginUrl', () => {
    it('compares origins', () => {
        expect(
            isSameOriginUrl('https://app.test/designs/1', 'https://app.test'),
        ).toBe(true);
        expect(isSameOriginUrl('https://chatgpt.com/', 'https://app.test')).toBe(
            false,
        );
    });
});

describe('webpageSandbox', () => {
    it('omits allow-same-origin for the host app', () => {
        expect(
            webpageSandbox('https://app.test/page', 'https://app.test'),
        ).not.toContain('allow-same-origin');
        expect(webpageSandbox('https://example.com', 'https://app.test')).toContain(
            'allow-same-origin',
        );
        expect(webpageSandbox('https://example.com', 'https://app.test')).toContain(
            'allow-scripts',
        );
    });
});

describe('iframeAllowsEmbedding', () => {
    it('treats about:blank as blocked', () => {
        const frame = {
            contentWindow: { location: { href: 'about:blank' } },
            contentDocument: { body: { childElementCount: 0, innerText: '' } },
        } as unknown as HTMLIFrameElement;

        expect(iframeAllowsEmbedding(frame)).toBe(false);
    });

    it('treats cross-origin access errors as a successful embed', () => {
        const frame = {
            get contentWindow() {
                throw new DOMException('Blocked a frame', 'SecurityError');
            },
        } as unknown as HTMLIFrameElement;

        expect(iframeAllowsEmbedding(frame)).toBe(true);
    });
});
