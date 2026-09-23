import { Form, Head, router, usePage } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import {
    destroy,
    duplicate,
    external as storeExternal,
    file as mediaFile,
    index,
    store,
    thumbnail,
    update,
} from '@/routes/media';
import {
    destroy as destroyFolder,
    store as storeFolder,
    update as updateFolder,
} from '@/routes/media-folders';
import type {
    MediaFolderRecord,
    MediaPermissions,
    MediaRecord,
    Paginated,
} from '@/types';

type Option = { value: string; label: string };

type Props = {
    uploadLimits: { fileBytes: number; requestBytes: number; maxFiles: number };
    media: Paginated<MediaRecord>;
    folders: MediaFolderRecord[];
    tags: string[];
    types: Option[];
    filters: {
        search: string;
        type: string;
        tag: string;
        folder_id: number | null;
        archived: boolean;
        sort: string;
        direction: string;
    };
    permissions: MediaPermissions;
};

function formatBytes(bytes: number | null): string {
    if (!bytes) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function MediaIndex({
    uploadLimits,
    media,
    folders,
    tags,
    types,
    filters,
    permissions,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const fileInput = useRef<HTMLInputElement>(null);
    const [search, setSearch] = useState(filters.search);
    const [dragging, setDragging] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [preview, setPreview] = useState<MediaRecord | null>(null);
    const [editing, setEditing] = useState<MediaRecord | null>(null);
    const [deleting, setDeleting] = useState<MediaRecord | null>(null);
    const [folderOpen, setFolderOpen] = useState(false);
    const [externalOpen, setExternalOpen] = useState(false);
    const [editingFolder, setEditingFolder] =
        useState<MediaFolderRecord | null>(null);
    const [deletingFolder, setDeletingFolder] =
        useState<MediaFolderRecord | null>(null);
    const [editForm, setEditForm] = useState({
        name: '',
        folder_id: '',
        tags: '',
        archived: false,
    });

    const currentFolder = useMemo(
        () => folders.find((folder) => folder.id === filters.folder_id) ?? null,
        [folders, filters.folder_id],
    );

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            index(slug),
            {
                search: next.search ?? search,
                type: next.type ?? filters.type,
                tag: next.tag ?? filters.tag,
                folder_id:
                    next.folder_id === undefined
                        ? (filters.folder_id ?? '')
                        : (next.folder_id ?? ''),
                archived: (next.archived ?? filters.archived) ? 1 : 0,
                sort: next.sort ?? filters.sort,
                direction: next.direction ?? filters.direction,
            },
            { preserveState: true, replace: true },
        );
    };

    const uploadFiles = (fileList: FileList | File[]) => {
        if (!permissions.canCreateMedia || uploading) return;
        const files = Array.from(fileList);
        if (!files.length) return;
        if (files.length > uploadLimits.maxFiles) {
            setUploadError(
                `Upload up to ${uploadLimits.maxFiles} files at a time.`,
            );
            return;
        }
        if (files.some((file) => file.size > uploadLimits.fileBytes)) {
            setUploadError(
                `The server accepts files up to ${formatBytes(uploadLimits.fileBytes)} each.`,
            );
            return;
        }
        if (
            files.reduce((total, file) => total + file.size, 0) +
                files.length * 1024 >
            uploadLimits.requestBytes
        ) {
            setUploadError(
                `This batch exceeds the server upload limit (${formatBytes(uploadLimits.requestBytes)}). Upload fewer files at a time.`,
            );
            return;
        }
        setUploadError(null);
        setUploadProgress(0);
        setUploading(true);
        const data = new FormData();
        files.forEach((item) => data.append('files[]', item));

        if (filters.folder_id) {
            data.append('folder_id', String(filters.folder_id));
        }

        router.post(store(slug), data, {
            forceFormData: true,
            preserveScroll: true,
            onProgress: (progress) =>
                setUploadProgress(progress?.percentage ?? 0),
            onError: (errors) =>
                setUploadError(
                    Object.values(errors).join(' ') ||
                        'Upload failed. Please retry.',
                ),
            onFinish: () => setUploading(false),
        });
    };

    const openEdit = (item: MediaRecord) => {
        setEditing(item);
        setEditForm({
            name: item.name,
            folder_id: item.folder_id ? String(item.folder_id) : '',
            tags: item.tags.join(', '),
            archived: Boolean(item.archived_at),
        });
    };

    return (
        <>
            <Head title="Media" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Media library"
                        description="Upload, tag, and organize assets for your screens."
                    />
                    <div className="flex flex-wrap gap-2">
                        {permissions.canCreateMedia && (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={() => setFolderOpen(true)}
                                    data-test="create-folder"
                                >
                                    New folder
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => setExternalOpen(true)}
                                    data-test="add-external-media"
                                >
                                    Add URL
                                </Button>
                                <Button
                                    onClick={() => fileInput.current?.click()}
                                    data-test="upload-media"
                                    disabled={uploading}
                                >
                                    Upload
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                <input
                    ref={fileInput}
                    type="file"
                    className="hidden"
                    multiple
                    accept="image/*,video/mp4,video/webm,video/quicktime,video/ogg,.mp4,.webm,.mov,.m4v,.ogv,.mpeg,.mpg,.avi,.mkv,.3gp,audio/*,.pdf,.zip"
                    onChange={(event) => {
                        if (event.target.files?.length) {
                            uploadFiles(event.target.files);
                            event.target.value = '';
                        }
                    }}
                />
                {uploading && (
                    <div
                        role="status"
                        className="rounded-lg border p-3 text-sm"
                    >
                        Uploading {uploadProgress}%
                        <progress
                            className="mt-2 w-full"
                            max={100}
                            value={uploadProgress}
                        />
                    </div>
                )}
                {uploadError && (
                    <p
                        role="alert"
                        className="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700"
                    >
                        {uploadError}
                    </p>
                )}

                <div className="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
                    <aside className="space-y-2">
                        <button
                            type="button"
                            className={`w-full rounded-md px-3 py-2 text-left text-sm ${filters.folder_id === null && !filters.archived ? 'bg-muted font-medium' : 'hover:bg-muted/60'}`}
                            onClick={() =>
                                applyFilters({
                                    folder_id: null,
                                    archived: false,
                                })
                            }
                        >
                            All files
                        </button>
                        <button
                            type="button"
                            className={`w-full rounded-md px-3 py-2 text-left text-sm ${filters.archived ? 'bg-muted font-medium' : 'hover:bg-muted/60'}`}
                            onClick={() => applyFilters({ archived: true })}
                        >
                            Archived
                        </button>
                        <div className="text-muted-foreground px-3 pt-2 text-xs font-medium tracking-wide uppercase">
                            Folders
                        </div>
                        {folders.length === 0 && (
                            <p className="text-muted-foreground px-3 text-sm">
                                No folders yet.
                            </p>
                        )}
                        {folders.map((folder) => (
                            <div
                                key={folder.id}
                                className="flex items-center gap-1"
                            >
                                <button
                                    type="button"
                                    className={`min-w-0 flex-1 rounded-md px-3 py-2 text-left text-sm ${filters.folder_id === folder.id ? 'bg-muted font-medium' : 'hover:bg-muted/60'}`}
                                    style={{
                                        paddingLeft: `${12 + folder.depth * 12}px`,
                                    }}
                                    onClick={() =>
                                        applyFilters({
                                            folder_id: folder.id,
                                            archived: false,
                                        })
                                    }
                                >
                                    {folder.name}
                                </button>
                                {permissions.canUpdateMedia && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => setEditingFolder(folder)}
                                    >
                                        Edit
                                    </Button>
                                )}
                            </div>
                        ))}
                    </aside>

                    <div className="space-y-4">
                        <div className="flex flex-wrap gap-2">
                            <Input
                                placeholder="Search media"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        applyFilters({ search });
                                    }
                                }}
                                className="max-w-xs"
                            />
                            <Select
                                value={filters.type || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        type: value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger className="w-40">
                                    <SelectValue placeholder="Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All types
                                    </SelectItem>
                                    {types.map((type) => (
                                        <SelectItem
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.tag || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        tag: value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger className="w-40">
                                    <SelectValue placeholder="Tag" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All tags
                                    </SelectItem>
                                    {tags.map((tag) => (
                                        <SelectItem key={tag} value={tag}>
                                            {tag}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.sort}
                                onValueChange={(value) =>
                                    applyFilters({ sort: value })
                                }
                            >
                                <SelectTrigger className="w-40">
                                    <SelectValue placeholder="Sort" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="name">Name</SelectItem>
                                    <SelectItem value="created_at">
                                        Newest
                                    </SelectItem>
                                    <SelectItem value="file_size">
                                        Size
                                    </SelectItem>
                                    <SelectItem value="type">Type</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {permissions.canCreateMedia && (
                            <button
                                type="button"
                                data-test="media-dropzone"
                                disabled={uploading}
                                className={`w-full rounded-xl border border-dashed p-8 text-sm ${dragging ? 'border-primary bg-muted/50' : 'text-muted-foreground'}`}
                                onClick={() => fileInput.current?.click()}
                                onDragOver={(event) => {
                                    event.preventDefault();
                                    setDragging(true);
                                }}
                                onDragLeave={() => setDragging(false)}
                                onDrop={(event) => {
                                    event.preventDefault();
                                    setDragging(false);

                                    if (event.dataTransfer.files.length) {
                                        uploadFiles(event.dataTransfer.files);
                                    }
                                }}
                            >
                                {currentFolder
                                    ? `Drop files into ${currentFolder.name}`
                                    : 'Drag and drop images, MP4 and other videos, or click to upload'}
                            </button>
                        )}

                        {media.data.length === 0 ? (
                            <EmptyState
                                title="No media yet"
                                description="Upload images, video, audio, PDFs, or HTML packages, or add an external URL."
                            />
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {media.data.map((item) => (
                                    <article
                                        key={item.id}
                                        className="bg-card overflow-hidden rounded-xl border"
                                    >
                                        <button
                                            type="button"
                                            className="bg-muted aspect-video w-full overflow-hidden"
                                            onClick={() => setPreview(item)}
                                        >
                                            {item.has_thumbnail ? (
                                                <img
                                                    src={thumbnail.url({
                                                        current_team: slug,
                                                        media: item.id,
                                                    })}
                                                    alt=""
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : item.type === 'image' &&
                                              item.has_file ? (
                                                <img
                                                    src={mediaFile.url({
                                                        current_team: slug,
                                                        media: item.id,
                                                    })}
                                                    alt={item.name}
                                                    className="h-full w-full object-contain"
                                                    loading="lazy"
                                                />
                                            ) : (
                                                <div className="text-muted-foreground flex h-full items-center justify-center text-sm">
                                                    {item.type_label}
                                                </div>
                                            )}
                                        </button>
                                        <div className="space-y-2 p-3">
                                            <div className="flex items-start justify-between gap-2">
                                                <h3 className="truncate font-medium">
                                                    {item.name}
                                                </h3>
                                                <Badge variant="secondary">
                                                    {
                                                        item.processing_status_label
                                                    }
                                                </Badge>
                                            </div>
                                            <p className="text-muted-foreground text-xs">
                                                {item.type_label} ·{' '}
                                                {item.duration
                                                    ? `${item.duration}s · `
                                                    : ''}
                                                {formatBytes(item.file_size)} ·
                                                used {item.usage_count}×
                                            </p>
                                            <div className="flex flex-wrap gap-1">
                                                {item.tags.map((tag) => (
                                                    <Badge
                                                        key={tag}
                                                        variant="outline"
                                                    >
                                                        {tag}
                                                    </Badge>
                                                ))}
                                            </div>
                                            <div className="flex flex-wrap gap-1">
                                                {permissions.canUpdateMedia && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            openEdit(item)
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                )}
                                                {permissions.canCreateMedia && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                duplicate.url({
                                                                    current_team:
                                                                        slug,
                                                                    media: item.id,
                                                                }),
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Duplicate
                                                    </Button>
                                                )}
                                                {permissions.canDeleteMedia && (
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() =>
                                                            setDeleting(item)
                                                        }
                                                    >
                                                        Delete
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <Dialog
                open={preview !== null}
                onOpenChange={(open) => !open && setPreview(null)}
            >
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>{preview?.name}</DialogTitle>
                        <DialogDescription>
                            {preview?.type_label}
                            {preview?.width && preview.height
                                ? ` · ${preview.width}×${preview.height}`
                                : ''}
                        </DialogDescription>
                    </DialogHeader>
                    {preview?.has_file && (
                        <div className="bg-muted overflow-hidden rounded-lg">
                            {preview.type === 'image' ? (
                                <img
                                    src={mediaFile.url({
                                        current_team: slug,
                                        media: preview.id,
                                    })}
                                    alt=""
                                    className="max-h-[60vh] w-full object-contain"
                                />
                            ) : preview.type === 'video' ? (
                                <video
                                    controls
                                    className="max-h-[60vh] w-full"
                                    src={mediaFile.url({
                                        current_team: slug,
                                        media: preview.id,
                                    })}
                                />
                            ) : preview.type === 'audio' ? (
                                <audio
                                    controls
                                    className="w-full"
                                    src={mediaFile.url({
                                        current_team: slug,
                                        media: preview.id,
                                    })}
                                />
                            ) : (
                                <a
                                    className="block p-6 underline"
                                    href={mediaFile.url({
                                        current_team: slug,
                                        media: preview.id,
                                    })}
                                >
                                    Open file
                                </a>
                            )}
                        </div>
                    )}
                    {preview?.external_url && (
                        <a
                            className="text-sm underline"
                            href={preview.external_url}
                            target="_blank"
                            rel="noreferrer"
                        >
                            {preview.external_url}
                        </a>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={folderOpen} onOpenChange={setFolderOpen}>
                <DialogContent>
                    <Form
                        {...storeFolder.form(slug)}
                        className="space-y-4"
                        onSuccess={() => setFolderOpen(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>New folder</DialogTitle>
                                    <DialogDescription>
                                        Organize media into folders.
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="space-y-2">
                                    <Label htmlFor="folder-name">Name</Label>
                                    <Input id="folder-name" name="name" />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="folder-parent">
                                        Parent
                                    </Label>
                                    <select
                                        id="folder-parent"
                                        name="parent_id"
                                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                        defaultValue=""
                                    >
                                        <option value="">Root</option>
                                        {folders.map((folder) => (
                                            <option
                                                key={folder.id}
                                                value={folder.id}
                                            >
                                                {'— '.repeat(folder.depth)}
                                                {folder.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Create
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingFolder !== null}
                onOpenChange={(open) => !open && setEditingFolder(null)}
            >
                <DialogContent>
                    {editingFolder && (
                        <Form
                            {...updateFolder.form({
                                current_team: slug,
                                mediaFolder: editingFolder.id,
                            })}
                            className="space-y-4"
                            onSuccess={() => setEditingFolder(null)}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <DialogHeader>
                                        <DialogTitle>Rename folder</DialogTitle>
                                    </DialogHeader>
                                    <div className="space-y-2">
                                        <Label htmlFor="edit-folder-name">
                                            Name
                                        </Label>
                                        <Input
                                            id="edit-folder-name"
                                            name="name"
                                            defaultValue={editingFolder.name}
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <input
                                        type="hidden"
                                        name="parent_id"
                                        value={editingFolder.parent_id ?? ''}
                                    />
                                    <DialogFooter>
                                        {permissions.canDeleteMedia && (
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                onClick={() => {
                                                    setDeletingFolder(
                                                        editingFolder,
                                                    );
                                                    setEditingFolder(null);
                                                }}
                                            >
                                                Delete
                                            </Button>
                                        )}
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={externalOpen} onOpenChange={setExternalOpen}>
                <DialogContent>
                    <Form
                        {...storeExternal.form(slug)}
                        className="space-y-4"
                        onSuccess={() => setExternalOpen(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        Add external media
                                    </DialogTitle>
                                    <DialogDescription>
                                        Add a direct video file URL (MP4, WebM,
                                        MOV), a webpage, or a live stream.
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="space-y-2">
                                    <Label htmlFor="external-name">Name</Label>
                                    <Input id="external-name" name="name" />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="external-type">Type</Label>
                                    <select
                                        id="external-type"
                                        name="type"
                                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                        defaultValue="video"
                                    >
                                        <option value="video">
                                            Video URL (MP4, WebM, MOV, …)
                                        </option>
                                        <option value="url">
                                            Webpage URL
                                        </option>
                                        <option value="live_stream">
                                            Live stream
                                        </option>
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="external-url">URL</Label>
                                    <Input
                                        id="external-url"
                                        name="external_url"
                                        type="url"
                                        placeholder="https://cdn.example.com/promo.mp4"
                                    />
                                    <InputError message={errors.external_url} />
                                </div>
                                {filters.folder_id && (
                                    <input
                                        type="hidden"
                                        name="folder_id"
                                        value={filters.folder_id}
                                    />
                                )}
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Add
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    {editing && (
                        <Form
                            {...update.form({
                                current_team: slug,
                                media: editing.id,
                            })}
                            className="space-y-4"
                            onSuccess={() => setEditing(null)}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <DialogHeader>
                                        <DialogTitle>Edit media</DialogTitle>
                                    </DialogHeader>
                                    <div className="space-y-2">
                                        <Label htmlFor="media-name">Name</Label>
                                        <Input
                                            id="media-name"
                                            name="name"
                                            value={editForm.name}
                                            onChange={(event) =>
                                                setEditForm({
                                                    ...editForm,
                                                    name: event.target.value,
                                                })
                                            }
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="media-folder">
                                            Folder
                                        </Label>
                                        <select
                                            id="media-folder"
                                            name="folder_id"
                                            className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                            value={editForm.folder_id}
                                            onChange={(event) =>
                                                setEditForm({
                                                    ...editForm,
                                                    folder_id:
                                                        event.target.value,
                                                })
                                            }
                                        >
                                            <option value="">Unfiled</option>
                                            {folders.map((folder) => (
                                                <option
                                                    key={folder.id}
                                                    value={folder.id}
                                                >
                                                    {folder.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="media-tags">Tags</Label>
                                        <Input
                                            id="media-tags"
                                            name="tags"
                                            value={editForm.tags}
                                            onChange={(event) =>
                                                setEditForm({
                                                    ...editForm,
                                                    tags: event.target.value,
                                                })
                                            }
                                            placeholder="lobby, promo"
                                        />
                                        <p className="text-muted-foreground text-xs">
                                            Comma-separated tags.
                                        </p>
                                    </div>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="hidden"
                                            name="archived"
                                            value={
                                                editForm.archived ? '1' : '0'
                                            }
                                        />
                                        <input
                                            type="checkbox"
                                            checked={editForm.archived}
                                            onChange={(event) =>
                                                setEditForm({
                                                    ...editForm,
                                                    archived:
                                                        event.target.checked,
                                                })
                                            }
                                        />
                                        Archive
                                    </label>
                                    <DialogFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                title="Delete media?"
                description="This permanently removes the file from storage."
                confirmLabel="Delete"
                onConfirm={() => {
                    if (!deleting) {
                        return;
                    }

                    router.delete(
                        destroy.url({
                            current_team: slug,
                            media: deleting.id,
                        }),
                        { preserveScroll: true },
                    );
                    setDeleting(null);
                }}
                onOpenChange={(open) => !open && setDeleting(null)}
            />

            <ConfirmDialog
                open={deletingFolder !== null}
                title="Delete folder?"
                description="Folders must be empty before they can be deleted."
                confirmLabel="Delete"
                onConfirm={() => {
                    if (!deletingFolder) {
                        return;
                    }

                    router.delete(
                        destroyFolder.url({
                            current_team: slug,
                            mediaFolder: deletingFolder.id,
                        }),
                        { preserveScroll: true },
                    );
                    setDeletingFolder(null);
                }}
                onOpenChange={(open) => !open && setDeletingFolder(null)}
            />
        </>
    );
}

MediaIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Media',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
