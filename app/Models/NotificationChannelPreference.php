<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\SignageAlert;
use Database\Factories\NotificationChannelPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property SignageAlert $event
 * @property bool $email
 * @property bool $in_app
 * @property bool $webhook
 * @property bool $slack
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['team_id', 'event', 'email', 'in_app', 'webhook', 'slack'])]
class NotificationChannelPreference extends Model
{
    /** @use HasFactory<NotificationChannelPreferenceFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => SignageAlert::class,
            'email' => 'boolean',
            'in_app' => 'boolean',
            'webhook' => 'boolean',
            'slack' => 'boolean',
        ];
    }
}
