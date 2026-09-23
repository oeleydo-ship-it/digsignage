<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\ScreenGroupFactory;
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
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Screen> $screens
 */
#[Fillable(['team_id', 'name', 'description'])]
class ScreenGroup extends Model
{
    /** @use HasFactory<ScreenGroupFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Get the screens in this group.
     *
     * @return BelongsToMany<Screen, $this>
     */
    public function screens(): BelongsToMany
    {
        return $this->belongsToMany(Screen::class, 'screen_group_screen')
            ->withTimestamps();
    }
}
