<?php

namespace App\Models;

use App\Enums\AppReleaseSource;
use App\Enums\AppReleaseStatus;
use Database\Factories\AppReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One version of the application: installed, installable or historical.
 *
 * @property int $id
 * @property string $version
 * @property string|null $title
 * @property string|null $notes
 * @property list<string>|null $features
 * @property AppReleaseSource $source
 * @property string|null $source_ref
 * @property string|null $package_path
 * @property string|null $checksum
 * @property int|null $package_size
 * @property string|null $release_path
 * @property AppReleaseStatus $status
 * @property string|null $log
 * @property string|null $error
 * @property int|null $created_by
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $activated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 */
#[Fillable([
    'version',
    'title',
    'notes',
    'features',
    'source',
    'source_ref',
    'package_path',
    'checksum',
    'package_size',
    'release_path',
    'status',
    'log',
    'error',
    'created_by',
    'started_at',
    'finished_at',
    'activated_at',
])]
class AppRelease extends Model
{
    /** @use HasFactory<AppReleaseFactory> */
    use HasFactory;

    /** Longest log kept per release; older output is trimmed from the top. */
    public const MAX_LOG_BYTES = 200_000;

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Append installer output, keeping the newest part when it grows large.
     */
    public function appendLog(string $output): void
    {
        $log = ($this->log ?? '').$output;

        if (strlen($log) > self::MAX_LOG_BYTES) {
            $log = "…\n".substr($log, -self::MAX_LOG_BYTES);
        }

        $this->forceFill(['log' => $log])->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'source' => AppReleaseSource::class,
            'status' => AppReleaseStatus::class,
            'package_size' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }
}
