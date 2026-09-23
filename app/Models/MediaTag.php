<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\MediaTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Media> $media
 */
#[Fillable(['team_id', 'name'])]
class MediaTag extends Model
{
    /** @use HasFactory<MediaTagFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsToMany<Media, $this>
     */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'media_media_tag')->withTimestamps();
    }
}
