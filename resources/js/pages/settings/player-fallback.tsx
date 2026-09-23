import { Head, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import ConfirmDialog from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    fallbackImage: string | null;
    canManage: boolean;
};

export default function PlayerFallback({ fallbackImage, canManage }: Props) {
    const slug = usePage().props.currentTeam?.slug ?? '';
    const fileInput = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);
    const [confirmRemove, setConfirmRemove] = useState(false);

    const upload = () => {
        if (!file) {
            return;
        }

        const data = new FormData();
        data.append('image', file);
        setError(null);

        router.post(`/${slug}/player-fallback`, data, {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => setUploading(true),
            onFinish: () => setUploading(false),
            onError: (errors) =>
                setError(Object.values(errors).join(' ') || null),
            onSuccess: () => {
                setFile(null);

                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    const remove = () => {
        router.delete(`/${slug}/player-fallback`, {
            preserveScroll: true,
            onFinish: () => setConfirmRemove(false),
        });
    };

    return (
        <>
            <Head title="Player fallback screen" />

            <h1 className="sr-only">Player fallback screen</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Player fallback screen"
                    description="Shown on players when no playable content is scheduled. Upload a branded image, or keep the default dark frame. Individual screens can override this from the Screens page."
                />

                <div className="relative aspect-video w-full max-w-2xl overflow-hidden rounded-xl border bg-[#0b0b0f]">
                    {fallbackImage && (
                        <>
                            <img
                                src={fallbackImage}
                                alt="Fallback screen preview"
                                className="absolute inset-0 h-full w-full object-cover"
                            />
                            <div className="absolute inset-0 bg-black/45" />
                        </>
                    )}
                    <div className="relative z-10 flex h-full flex-col items-center justify-center gap-2 px-8 text-center text-white">
                        <AppLogoIcon className="size-10 fill-white opacity-90" />
                        <p className="text-[10px] tracking-[0.3em] text-white/50 uppercase">
                            DigSignage
                        </p>
                        <p className="text-lg text-white/70">
                            Waiting for content
                        </p>
                    </div>
                </div>

                <p className="text-muted-foreground text-sm">
                    {fallbackImage
                        ? 'A custom fallback image is active.'
                        : 'No custom image set — players show the default branded frame.'}
                </p>

                {canManage && (
                    <div className="grid max-w-xl gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="fallback-image">
                                Fallback image
                            </Label>
                            <Input
                                ref={fileInput}
                                id="fallback-image"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                onChange={(event) =>
                                    setFile(
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                                data-test="fallback-image-input"
                            />
                            <p className="text-muted-foreground text-xs">
                                JPG, PNG, or WebP, up to 5 MB. Landscape
                                1920×1080 or larger works best.
                            </p>
                            <InputError message={error ?? undefined} />
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                onClick={upload}
                                disabled={!file || uploading}
                                data-test="upload-fallback-image"
                            >
                                {uploading
                                    ? 'Uploading…'
                                    : fallbackImage
                                      ? 'Replace image'
                                      : 'Upload image'}
                            </Button>
                            {fallbackImage && (
                                <Button
                                    variant="outline"
                                    onClick={() => setConfirmRemove(true)}
                                    data-test="remove-fallback-image"
                                >
                                    Remove image
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={confirmRemove}
                title="Remove fallback image"
                description="Players will fall back to the default branded frame when idle."
                confirmLabel="Remove"
                onOpenChange={setConfirmRemove}
                onConfirm={remove}
            />
        </>
    );
}

PlayerFallback.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Settings',
            href: '/settings/profile',
        },
        {
            title: 'Player fallback',
            href: props.currentTeam
                ? `/${props.currentTeam.slug}/player-fallback`
                : '/',
        },
    ],
});
