<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Data\QueueKioskBranding;
use Database\Factories\QueueKioskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $location_id
 * @property string $name
 * @property array<string, mixed>|null $branding
 * @property bool $printer_enabled
 * @property bool $is_active
 * @property string $token
 * @property string|null $pin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Location|null $location
 */
#[Fillable([
    'team_id',
    'location_id',
    'name',
    'branding',
    'printer_enabled',
    'is_active',
    'token',
    'pin',
])]
#[Hidden(['pin'])]
class QueueKiosk extends Model
{
    /** @use HasFactory<QueueKioskFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Optional location this kiosk belongs to.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Public tablet URL for this kiosk.
     */
    public function serveUrl(): string
    {
        return route('queue.kiosk.serve', $this);
    }

    public function resolvedBranding(): QueueKioskBranding
    {
        return QueueKioskBranding::fromArray($this->branding);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'printer_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (QueueKiosk $kiosk): void {
            if (blank($kiosk->token)) {
                $kiosk->token = static::generateToken();
            }

            if ($kiosk->branding === null) {
                $kiosk->branding = QueueKioskBranding::defaults();
            }
        });
    }

    public static function generateToken(): string
    {
        return Str::lower(Str::random(48));
    }
}
