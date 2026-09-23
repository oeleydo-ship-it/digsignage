<?php

namespace App\Http\Controllers\Media;

use App\Actions\Media\DeleteMedia;
use App\Actions\Media\DuplicateMedia;
use App\Actions\Media\StoreExternalMedia;
use App\Actions\Media\UpdateMedia;
use App\Actions\Media\UploadMediaFiles;
use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\StoreExternalMediaRequest;
use App\Http\Requests\Media\UpdateMediaRequest;
use App\Http\Requests\Media\UploadMediaRequest;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\MediaTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    /**
     * Display the media library.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Media::class);

        $team = $request->user()->currentTeam;
        $search = $request->string('search')->toString();
        $type = $request->string('type')->toString();
        $tag = $request->string('tag')->toString();
        $folderId = $request->has('folder_id')
            ? ($request->input('folder_id') === null || $request->input('folder_id') === ''
                ? null
                : $request->integer('folder_id'))
            : null;
        $archived = $request->boolean('archived');
        $sort = $request->string('sort')->toString() ?: 'name';

        $allowedSorts = ['name', 'created_at', 'file_size', 'type'];
        $sortColumn = in_array($sort, $allowedSorts, true) ? $sort : 'name';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $media = Media::query()
            ->forTeam($team)
            ->with(['tags:id,name', 'folder:id,name'])
            ->when(! $archived, fn ($query) => $query->notArchived())
            ->when($archived, fn ($query) => $query->whereNotNull('archived_at'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('original_filename', 'like', '%'.$search.'%');
                });
            })
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($tag !== '', function ($query) use ($tag) {
                $query->whereHas('tags', fn ($tags) => $tags->where('name', $tag));
            })
            ->when($folderId !== null, fn ($query) => $query->where('folder_id', $folderId))
            ->orderBy($sortColumn, $direction)
            ->paginate(36)
            ->withQueryString()
            ->through(fn (Media $item) => $this->mediaPayload($item));

        $folders = MediaFolder::query()
            ->forTeam($team)
            ->withCount(['children', 'media'])
            ->orderBy('path')
            ->orderBy('name')
            ->get()
            ->map(fn (MediaFolder $folder) => [
                'id' => $folder->id,
                'parent_id' => $folder->parent_id,
                'name' => $folder->name,
                'path' => $folder->path,
                'depth' => $folder->depth,
                'children_count' => $folder->children_count,
                'media_count' => $folder->media_count,
            ]);

        $uploadMax = ini_get('upload_max_filesize');
        $postMax = ini_get('post_max_size');

        return Inertia::render('media/index', [
            'uploadLimits' => [
                'fileBytes' => min(
                    (int) config('media.max_file_kilobytes') * 1024,
                    is_string($uploadMax) ? (ini_parse_quantity($uploadMax) ?: PHP_INT_MAX) : PHP_INT_MAX,
                ),
                'requestBytes' => is_string($postMax) ? (ini_parse_quantity($postMax) ?: PHP_INT_MAX) : PHP_INT_MAX,
                'maxFiles' => min(20, (int) ini_get('max_file_uploads')),
            ],
            'media' => $media,
            'folders' => $folders,
            'tags' => MediaTag::query()->forTeam($team)->orderBy('name')->pluck('name'),
            'types' => collect(MediaType::cases())->map(fn (MediaType $mediaType) => [
                'value' => $mediaType->value,
                'label' => $mediaType->label(),
            ]),
            'filters' => [
                'search' => $search,
                'type' => $type,
                'tag' => $tag,
                'folder_id' => $request->has('folder_id') ? $folderId : null,
                'archived' => $archived,
                'sort' => $sortColumn,
                'direction' => $direction,
            ],
            'permissions' => $request->user()->toMediaPermissions($team),
        ]);
    }

    /**
     * Upload one or more files.
     */
    public function store(UploadMediaRequest $request, UploadMediaFiles $uploadMediaFiles): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Media::class);

        $files = $request->file('files');
        $uploads = [];

        foreach (is_array($files) ? $files : [$files] as $file) {
            if ($file instanceof UploadedFile) {
                $uploads[] = $file;
            }
        }

        $created = $uploadMediaFiles->handle(
            $request->user(),
            $request->user()->currentTeam,
            $uploads,
            $request->integer('folder_id') ?: null,
            $request->validated('tags') ?? [],
        );

        if ($request->wantsJson()) {
            return response()->json(['media' => collect($created)->map(fn (Media $media) => $this->mediaPayload($media->fresh()))], 201);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Media uploaded.')]);

        return back();
    }

    /**
     * Store an external URL or live stream.
     */
    public function storeExternal(StoreExternalMediaRequest $request, StoreExternalMedia $storeExternal): RedirectResponse
    {
        Gate::authorize('create', Media::class);

        $storeExternal->handle(
            $request->user(),
            $request->user()->currentTeam,
            $request->validated(),
            $request->validated('tags') ?? [],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('External media added.')]);

        return back();
    }

    /**
     * Update media metadata.
     */
    public function update(UpdateMediaRequest $request, string $current_team, Media $media, UpdateMedia $updateMedia): RedirectResponse
    {
        Gate::authorize('update', $media);
        abort_unless($media->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $updateMedia->handle($request->user()->currentTeam, $media, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Media updated.')]);

        return back();
    }

    /**
     * Duplicate a media item.
     */
    public function duplicate(Request $request, string $current_team, Media $media, DuplicateMedia $duplicateMedia): RedirectResponse
    {
        Gate::authorize('create', Media::class);
        abort_unless($media->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $duplicateMedia->handle($media);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Media duplicated.')]);

        return back();
    }

    /**
     * Permanently delete a media item.
     */
    public function destroy(Request $request, string $current_team, Media $media, DeleteMedia $deleteMedia): RedirectResponse
    {
        Gate::authorize('delete', $media);
        abort_unless($media->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $deleteMedia->handle($media);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Media deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function mediaPayload(Media $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'type' => $media->type->value,
            'type_label' => $media->type->label(),
            'source' => $media->source->value,
            'original_filename' => $media->original_filename,
            'mime_type' => $media->mime_type,
            'file_size' => $media->file_size,
            'duration' => $media->duration,
            'width' => $media->width,
            'height' => $media->height,
            'processing_status' => $media->processing_status->value,
            'processing_status_label' => $media->processing_status->label(),
            'processing_error' => $media->processing_error,
            'external_url' => $media->external_url,
            'usage_count' => $media->usage_count,
            'archived_at' => $media->archived_at?->toIso8601String(),
            'folder_id' => $media->folder_id,
            'folder_name' => $media->folder?->name,
            'has_file' => filled($media->storage_path),
            'has_thumbnail' => filled($media->thumbnail_path),
            'created_at' => $media->created_at?->toIso8601String(),
            'tags' => $media->tags->pluck('name')->values()->all(),
        ];
    }
}
