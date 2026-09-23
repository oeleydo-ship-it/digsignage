<?php

namespace App\Support;

use App\Models\StorageDisk;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Bridges platform-managed storage backends into Laravel's filesystem config.
 *
 * Disks live in the `storage_disks` table so a platform administrator can add
 * S3, Wasabi, DigitalOcean Spaces, R2, B2, MinIO or a plain server directory
 * without touching `.env`. Each row is registered lazily under
 * `filesystems.disks.storage-{id}` the first time it is resolved, so the rest
 * of the application keeps working with ordinary disk names.
 */
class StorageDisks
{
    /**
     * Disks loaded for the current request, keyed by id.
     *
     * @var array<int, StorageDisk>|null
     */
    protected ?array $disks = null;

    /**
     * Disk names already written into the filesystem config.
     *
     * @var array<string, true>
     */
    protected array $registered = [];

    /**
     * Fallback disk name from config, used when nothing is configured yet.
     */
    public function fallbackDiskName(): string
    {
        return (string) config('media.disk', 'media');
    }

    /**
     * Forget memoised rows so the next resolve re-reads the table.
     */
    public function flush(): void
    {
        $this->disks = null;
        $this->registered = [];
    }

    /**
     * All configured disks, newest last.
     *
     * @return array<int, StorageDisk>
     */
    public function all(): array
    {
        if ($this->disks !== null) {
            return $this->disks;
        }

        try {
            $this->disks = StorageDisk::query()
                ->orderBy('name')
                ->get()
                ->keyBy('id')
                ->all();
        } catch (QueryException) {
            // The table is missing (e.g. during the very first migration run).
            $this->disks = [];
        }

        return $this->disks;
    }

    /**
     * The platform-wide default backend, when one is marked.
     */
    public function platformDefault(): ?StorageDisk
    {
        foreach ($this->all() as $disk) {
            if ($disk->is_default && $disk->is_active && $disk->team_id === null) {
                return $disk;
            }
        }

        return null;
    }

    /**
     * Disks an organization may store new uploads on.
     *
     * @return list<StorageDisk>
     */
    public function availableFor(?Team $team): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (StorageDisk $disk) => $disk->is_active
                && ($disk->team_id === null || ($team !== null && $disk->team_id === $team->id)),
        ));
    }

    /**
     * Backend new uploads for this organization should be written to.
     *
     * Falls back to the platform default, then to the configured `media` disk.
     */
    public function forTeam(?Team $team): string
    {
        if ($team !== null && $team->storage_disk_id !== null) {
            $assigned = $this->all()[$team->storage_disk_id] ?? null;

            if ($assigned !== null && $assigned->is_active
                && ($assigned->team_id === null || $assigned->team_id === $team->id)) {
                return $this->register($assigned);
            }
        }

        $default = $this->platformDefault();

        return $default !== null ? $this->register($default) : $this->fallbackDiskName();
    }

    /**
     * Resolve a disk name recorded on an existing record.
     *
     * Stored files keep using the backend they were written to, even after the
     * organization is moved to a different one. Rows whose backend was deleted
     * fall back so reads degrade to a 404 instead of a driver exception.
     */
    public function resolveStored(?string $name): string
    {
        if (blank($name)) {
            return $this->fallbackDiskName();
        }

        if (! str_starts_with($name, StorageDisk::NAME_PREFIX)) {
            return config()->has('filesystems.disks.'.$name)
                ? $name
                : $this->fallbackDiskName();
        }

        $id = (int) substr($name, strlen(StorageDisk::NAME_PREFIX));
        $disk = $this->all()[$id] ?? null;

        return $disk !== null ? $this->register($disk) : $this->fallbackDiskName();
    }

    /**
     * Write a backend into the filesystem config and return its disk name.
     */
    public function register(StorageDisk $disk): string
    {
        $name = $disk->diskName();

        if (! isset($this->registered[$name])) {
            config(['filesystems.disks.'.$name => $disk->toDiskConfig()]);
            Storage::forgetDisk($name);
            $this->registered[$name] = true;
        }

        return $name;
    }

    /**
     * Register a backend from unsaved attributes so it can be tested before saving.
     */
    public function registerTransient(StorageDisk $disk, string $name): string
    {
        config(['filesystems.disks.'.$name => $disk->toDiskConfig()]);
        Storage::forgetDisk($name);

        return $name;
    }

    /**
     * Round-trip a small object to confirm the credentials work.
     *
     * @return array{ok: bool, error: string|null}
     */
    public function test(StorageDisk $disk): array
    {
        $name = $disk->exists
            ? $this->register($disk)
            : $this->registerTransient($disk, 'storage-test-'.bin2hex(random_bytes(4)));

        $probe = 'digsignage-connection-test/'.bin2hex(random_bytes(8)).'.txt';

        try {
            $filesystem = Storage::disk($name);

            if ($filesystem->put($probe, 'digsignage storage check') === false) {
                return ['ok' => false, 'error' => 'The backend rejected the write.'];
            }

            $readBack = $filesystem->get($probe);
            $filesystem->delete($probe);

            if ($readBack !== 'digsignage storage check') {
                return ['ok' => false, 'error' => 'The file was written but could not be read back.'];
            }

            return ['ok' => true, 'error' => null];
        } catch (Throwable $exception) {
            try {
                Storage::disk($name)->delete($probe);
            } catch (Throwable) {
                // Nothing to clean up.
            }

            return ['ok' => false, 'error' => mb_substr($exception->getMessage(), 0, 250)];
        }
    }
}
