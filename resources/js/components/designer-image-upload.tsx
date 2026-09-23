import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { store } from '@/routes/media';
import type { MediaRecord } from '@/types';

const ACCEPT =
    'image/png,image/jpeg,image/webp,image/gif,image/svg+xml,video/mp4,video/webm,video/quicktime,video/ogg,.mp4,.webm,.mov,.m4v,.ogv,.mpeg,.mpg,.avi,.mkv,.3gp';

export async function uploadDesignerMedia(
    file: File,
    slug: string,
): Promise<MediaRecord> {
    const isImage = file.type.startsWith('image/');
    const isVideo =
        file.type.startsWith('video/') ||
        /\.(mp4|webm|mov|m4v|ogv|mpeg|mpg|avi|mkv|3gp)$/i.test(file.name);

    if (!isImage && !isVideo) {
        throw new Error(
            'Choose an image (PNG, JPEG, WebP, GIF, SVG) or a video (MP4, WebM, MOV, M4V, and similar).',
        );
    }

    const form = new FormData();
    form.append('files[]', file);
    const token = window.document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    const response = await fetch(store.url(slug), {
        method: 'POST',
        credentials: 'same-origin',
        body: form,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
        },
    });
    if (!response.ok) {
        const payload = (await response.json().catch(() => null)) as {
            message?: string;
            errors?: Record<string, string[]>;
        } | null;
        const detail = payload?.errors
            ? Object.values(payload.errors).flat().join(' ')
            : null;
        throw new Error(
            detail ||
                (response.status === 413
                    ? 'The file exceeds the server upload limit. Choose a smaller file.'
                    : response.status === 419
                      ? 'Your session expired. Refresh the page and try again.'
                      : payload?.message || 'Upload failed. Please try again.'),
        );
    }
    const payload = (await response.json()) as { media: MediaRecord[] };
    if (!payload.media?.[0])
        throw new Error('The server did not return the uploaded file.');
    return payload.media[0];
}

/** @deprecated Use uploadDesignerMedia */
export const uploadDesignerImage = uploadDesignerMedia;

export default function DesignerImageUpload({
    disabled,
    uploading,
    onFile,
}: {
    disabled: boolean;
    uploading: boolean;
    onFile: (file: File) => void;
}) {
    const input = useRef<HTMLInputElement>(null);
    return (
        <>
            <input
                ref={input}
                type="file"
                accept={ACCEPT}
                className="hidden"
                aria-label="Upload designer media"
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    event.target.value = '';
                    if (file) onFile(file);
                }}
            />
            <Button
                type="button"
                className="mb-2 w-full"
                disabled={disabled || uploading}
                onClick={() => input.current?.click()}
            >
                {uploading ? 'Uploading…' : 'Upload image or video'}
            </Button>
        </>
    );
}
