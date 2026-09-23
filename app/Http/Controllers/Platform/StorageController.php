<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\RecordPlatformAudit;
use App\Enums\PlatformAuditAction;
use App\Enums\StorageProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SaveStorageDiskRequest;
use App\Models\Media;
use App\Models\StorageDisk;
use App\Models\Team;
use App\Support\StorageDisks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StorageController extends Controller
{
    public function __construct(protected StorageDisks $disks) {}

    public function index(): Response
    {
        $usage = $this->usageByDisk();

        return Inertia::render('platform/storage/index', [
            'disks' => StorageDisk::query()
                ->with('team:id,name,slug')
                ->withCount('assignedTeams')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(fn (StorageDisk $disk) => $this->summary($disk, $usage)),
            'providers' => StorageProvider::options(),
            'organizations' => Team::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'storage_disk_id'])
                ->map(fn (Team $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'storage_disk_id' => $team->storage_disk_id,
                ]),
            'fallbackDisk' => $this->disks->fallbackDiskName(),
            'unassignedBytes' => $usage[''] ?? 0,
        ]);
    }

    public function store(SaveStorageDiskRequest $request, RecordPlatformAudit $audit): RedirectResponse
    {
        $disk = DB::transaction(function () use ($request) {
            $disk = StorageDisk::query()->create([
                ...$this->attributes($request),
                'created_by' => $request->user()?->id,
            ]);

            $this->syncDefault($disk);

            return $disk;
        });

        $this->disks->flush();

        $audit->handle(
            PlatformAuditAction::StorageDiskCreated,
            $request->user(),
            'storage_disk',
            $disk->id,
            null,
            $this->auditPayload($disk),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Storage backend added.')]);

        return back();
    }

    public function update(SaveStorageDiskRequest $request, StorageDisk $storageDisk, RecordPlatformAudit $audit): RedirectResponse
    {
        $before = $this->auditPayload($storageDisk);

        DB::transaction(function () use ($request, $storageDisk) {
            $attributes = $this->attributes($request);

            // Blank credential fields keep the stored secret rather than clearing it.
            foreach (['access_key', 'secret_key'] as $secret) {
                if (blank($attributes[$secret])) {
                    unset($attributes[$secret]);
                }
            }

            $storageDisk->update($attributes);

            $this->syncDefault($storageDisk);
        });

        $this->disks->flush();

        $audit->handle(
            PlatformAuditAction::StorageDiskUpdated,
            $request->user(),
            'storage_disk',
            $storageDisk->id,
            $before,
            $this->auditPayload($storageDisk->refresh()),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Storage backend updated.')]);

        return back();
    }

    public function test(Request $request, StorageDisk $storageDisk, RecordPlatformAudit $audit): RedirectResponse
    {
        $result = $this->disks->test($storageDisk);

        $storageDisk->forceFill([
            'last_tested_at' => now(),
            'last_test_error' => $result['error'],
        ])->save();

        $audit->handle(
            PlatformAuditAction::StorageDiskTested,
            $request->user(),
            'storage_disk',
            $storageDisk->id,
            null,
            ['ok' => $result['ok'], 'error' => $result['error']],
        );

        Inertia::flash('toast', $result['ok']
            ? ['type' => 'success', 'message' => __('Connection succeeded.')]
            : ['type' => 'error', 'message' => __('Connection failed: :error', ['error' => $result['error']])]);

        return back();
    }

    public function destroy(Request $request, StorageDisk $storageDisk, RecordPlatformAudit $audit): RedirectResponse
    {
        $stored = Media::query()->where('storage_disk', $storageDisk->diskName())->count();

        if ($stored > 0) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This backend still holds :count media items. Move or delete them first.', ['count' => $stored]),
            ]);

            return back();
        }

        $before = $this->auditPayload($storageDisk);
        $id = $storageDisk->id;
        $storageDisk->delete();
        $this->disks->flush();

        $audit->handle(
            PlatformAuditAction::StorageDiskDeleted,
            $request->user(),
            'storage_disk',
            $id,
            $before,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Storage backend deleted.')]);

        return back();
    }

    /**
     * Point an organization at one of the available backends.
     */
    public function assign(Request $request, Team $team, RecordPlatformAudit $audit): RedirectResponse
    {
        $validated = $request->validate([
            'storage_disk_id' => ['nullable', 'integer'],
        ]);

        $diskId = $validated['storage_disk_id'] ?? null;
        $disk = null;

        if ($diskId !== null) {
            $disk = StorageDisk::query()
                ->active()
                ->where(fn ($query) => $query->whereNull('team_id')->orWhere('team_id', $team->id))
                ->whereKey($diskId)
                ->firstOrFail();
        }

        $before = ['storage_disk_id' => $team->storage_disk_id];
        $team->forceFill(['storage_disk_id' => $disk?->id])->save();
        $this->disks->flush();

        $audit->handle(
            PlatformAuditAction::OrganizationStorageAssigned,
            $request->user(),
            'team',
            $team->id,
            $before,
            ['storage_disk_id' => $disk?->id, 'storage_disk_name' => $disk?->name],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization storage updated.')]);

        return back();
    }

    /**
     * Exactly one platform-wide backend may be the default.
     */
    protected function syncDefault(StorageDisk $disk): void
    {
        if (! $disk->is_default) {
            return;
        }

        if ($disk->team_id !== null) {
            // A team-scoped backend cannot be the platform default.
            $disk->forceFill(['is_default' => false])->save();

            return;
        }

        StorageDisk::query()
            ->whereKeyNot($disk->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(SaveStorageDiskRequest $request): array
    {
        $provider = StorageProvider::from($request->validated('provider'));

        return [
            'name' => $request->validated('name'),
            'provider' => $provider,
            'team_id' => $request->validated('team_id'),
            'bucket' => $provider->isS3() ? $request->validated('bucket') : null,
            'region' => $provider->isS3() ? $request->validated('region') : null,
            'root' => $request->validated('root'),
            'endpoint' => $provider->isS3() ? $request->validated('endpoint') : null,
            'url' => $request->validated('url'),
            'access_key' => $provider->isS3() ? $request->validated('access_key') : null,
            'secret_key' => $provider->isS3() ? $request->validated('secret_key') : null,
            'path_style_endpoint' => $provider->isS3() && $request->boolean('path_style_endpoint'),
            'visibility' => $request->validated('visibility'),
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * Bytes stored per disk name, with an empty key for pre-existing uploads.
     *
     * @return array<string, int>
     */
    protected function usageByDisk(): array
    {
        return Media::query()
            ->selectRaw('COALESCE(storage_disk, \'\') as disk_name, COALESCE(SUM(file_size), 0) as bytes')
            ->groupBy('disk_name')
            ->pluck('bytes', 'disk_name')
            ->map(fn ($bytes) => (int) $bytes)
            ->all();
    }

    /**
     * @param  array<string, int>  $usage
     * @return array<string, mixed>
     */
    protected function summary(StorageDisk $disk, array $usage): array
    {
        return [
            'id' => $disk->id,
            'name' => $disk->name,
            'disk_name' => $disk->diskName(),
            'provider' => $disk->provider->value,
            'provider_label' => $disk->provider->label(),
            'driver' => $disk->provider->driver(),
            'team_id' => $disk->team_id,
            'team_name' => $disk->team?->name,
            'bucket' => $disk->bucket,
            'region' => $disk->region,
            'root' => $disk->root,
            'endpoint' => $disk->provider->endpointFor($disk->endpoint, $disk->region),
            'url' => $disk->url,
            'access_key' => $disk->access_key ? $this->maskKey($disk->access_key) : null,
            'has_credentials' => filled($disk->secret_key),
            'path_style_endpoint' => $disk->path_style_endpoint,
            'visibility' => $disk->visibility,
            'is_default' => $disk->is_default,
            'is_active' => $disk->is_active,
            'assigned_teams_count' => $disk->assigned_teams_count ?? 0,
            'stored_bytes' => $usage[$disk->diskName()] ?? 0,
            'last_tested_at' => $disk->last_tested_at?->toIso8601String(),
            'last_test_error' => $disk->last_test_error,
        ];
    }

    protected function maskKey(string $key): string
    {
        return mb_strlen($key) <= 4
            ? str_repeat('•', mb_strlen($key))
            : str_repeat('•', max(mb_strlen($key) - 4, 4)).mb_substr($key, -4);
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditPayload(StorageDisk $disk): array
    {
        return [
            'name' => $disk->name,
            'provider' => $disk->provider->value,
            'bucket' => $disk->bucket,
            'region' => $disk->region,
            'endpoint' => $disk->endpoint,
            'team_id' => $disk->team_id,
            'is_default' => $disk->is_default,
            'is_active' => $disk->is_active,
        ];
    }
}
