<?php

namespace App\Actions\Channel;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\LiveStreamProtocol;
use App\Models\Channel;
use App\Models\ChannelZone;
use App\Models\Playlist;
use App\Models\Screen;
use App\Models\Team;
use App\Models\User;
use App\Support\ChannelZones;
use App\Support\ContentWorkflow;
use App\Support\TeamQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveChannel
{
    /**
     * Create or update a channel, its zones, and assigned screens.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Team $team, array $attributes, ?Channel $channel = null): Channel
    {
        return DB::transaction(function () use ($user, $team, $attributes, $channel) {
            $channel ??= new Channel([
                'team_id' => $team->id,
                'created_by' => $user->id,
                'status' => ChannelStatus::Draft,
                'type' => ChannelType::Playlist,
                'version' => 1,
                'width' => 1920,
                'height' => 1080,
            ]);

            $channel->loadMissing('zones');
            $creating = ! $channel->exists;

            $type = isset($attributes['type'])
                ? ChannelType::from($attributes['type'])
                : ($channel->type ?? ChannelType::Playlist);

            if ($type === ChannelType::Advanced) {
                app(TeamQuota::class)->assertCanUseAdvanced($team);
            }

            $playlistId = $this->nullableId($attributes['playlist_id'] ?? $channel->playlist_id);
            $liveProtocol = isset($attributes['live_protocol']) && is_string($attributes['live_protocol']) && $attributes['live_protocol'] !== ''
                ? LiveStreamProtocol::from($attributes['live_protocol'])
                : $channel->live_protocol;
            $liveUrl = array_key_exists('live_url', $attributes)
                ? (is_string($attributes['live_url']) ? trim($attributes['live_url']) : null)
                : $channel->live_url;

            $zones = $channel->zonesSnapshot();

            if ($type === ChannelType::Advanced) {
                if (isset($attributes['zones']) && is_array($attributes['zones'])) {
                    $rawZones = [];

                    foreach ($attributes['zones'] as $zone) {
                        if (is_array($zone)) {
                            $rawZones[] = $zone;
                        }
                    }

                    $zones = ChannelZones::normalize($rawZones, $team);
                } elseif (! $channel->exists) {
                    $zones = ChannelZones::normalize(ChannelZones::defaultLayout(), $team);
                }
            } else {
                $zones = [];
            }

            $previous = json_encode([
                'type' => $channel->type->value,
                'playlist_id' => $channel->playlist_id,
                'live_protocol' => $channel->live_protocol?->value,
                'live_url' => $channel->live_url,
                'zones' => $channel->zonesSnapshot(),
            ]);
            $next = json_encode([
                'type' => $type->value,
                'playlist_id' => $playlistId,
                'live_protocol' => $liveProtocol?->value,
                'live_url' => $liveUrl,
                'zones' => $zones,
            ]);
            $changed = $channel->exists && is_string($previous) && is_string($next) && $previous !== $next;

            $statusValue = ContentWorkflow::editorStatus(
                $channel,
                isset($attributes['status']) ? (string) $attributes['status'] : null,
                $changed,
            );
            $status = ChannelStatus::from($statusValue);

            if ($type === ChannelType::Playlist) {
                $this->assertPlaylist($playlistId, $team, $status);
                $liveProtocol = null;
                $liveUrl = null;
            } elseif ($type === ChannelType::Live) {
                $playlistId = null;
                $this->assertLive($liveProtocol, $liveUrl, $status);
            } else {
                $playlistId = null;
                $liveProtocol = null;
                $liveUrl = null;

                if (in_array($status, [ChannelStatus::Published, ChannelStatus::Scheduled], true) && $zones === []) {
                    throw ValidationException::withMessages([
                        'zones' => __('A multi-zone channel needs at least one zone before it can be published.'),
                    ]);
                }
            }

            if ($status === ChannelStatus::Scheduled && blank($attributes['scheduled_at'] ?? $channel->scheduled_at)) {
                throw ValidationException::withMessages([
                    'scheduled_at' => __('A go-live time is required for scheduled channels.'),
                ]);
            }

            $channel->fill([
                'name' => $attributes['name'] ?? $channel->name,
                'description' => $attributes['description'] ?? $channel->description,
                'type' => $type,
                'status' => $status,
                'playlist_id' => $playlistId,
                'live_protocol' => $liveProtocol,
                'live_url' => $liveUrl !== '' ? $liveUrl : null,
                'width' => (int) ($attributes['width'] ?? $channel->width ?? 1920),
                'height' => (int) ($attributes['height'] ?? $channel->height ?? 1080),
                'scheduled_at' => $status === ChannelStatus::Scheduled
                    ? ($attributes['scheduled_at'] ?? $channel->scheduled_at)
                    : null,
                'published_at' => $status === ChannelStatus::Published
                    ? ($channel->published_at ?? now())
                    : $channel->published_at,
                'updated_by' => $user->id,
            ]);

            if ($changed) {
                $channel->version = $channel->version + 1;
            }

            $channel->save();

            $channel->zones()->delete();

            foreach ($zones as $zone) {
                ChannelZone::query()->create([
                    ...$zone,
                    'team_id' => $team->id,
                    'channel_id' => $channel->id,
                ]);
            }

            if (array_key_exists('screen_ids', $attributes) && is_array($attributes['screen_ids'])) {
                $this->syncScreens($team, $channel, $attributes['screen_ids']);
            }

            $fresh = $channel->refresh()->load(['zones.playlist', 'playlist', 'screens']);

            if ($creating || $changed) {
                app(RecordOrganizationAudit::class)->handle(
                    $team,
                    AuditAction::ContentChanged,
                    $user,
                    'channel',
                    $fresh->id,
                    null,
                    ['name' => $fresh->name, 'type' => $fresh->type->value],
                );
            }

            return $fresh;
        });
    }

    /**
     * @param  array<int|string, mixed>  $screenIds
     */
    protected function syncScreens(Team $team, Channel $channel, array $screenIds): void
    {
        $ids = [];

        foreach ($screenIds as $screenId) {
            $id = (int) $screenId;

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));

        $owned = Screen::query()->forTeam($team)->whereKey($ids)->pluck('id')->all();

        if (count($owned) !== count($ids)) {
            throw ValidationException::withMessages([
                'screen_ids' => __('One or more screens are not available to this team.'),
            ]);
        }

        Screen::query()
            ->forTeam($team)
            ->where('current_channel_id', $channel->id)
            ->whereNotIn('id', $owned)
            ->update(['current_channel_id' => null]);

        if ($owned !== []) {
            Screen::query()->forTeam($team)->whereKey($owned)->update(['current_channel_id' => $channel->id]);
        }
    }

    protected function assertPlaylist(?int $playlistId, Team $team, ChannelStatus $status): void
    {
        if ($playlistId !== null && Playlist::query()->forTeam($team)->whereKey($playlistId)->doesntExist()) {
            throw ValidationException::withMessages([
                'playlist_id' => __('The selected playlist is not available to this team.'),
            ]);
        }

        if (in_array($status, [ChannelStatus::Published, ChannelStatus::Scheduled], true) && $playlistId === null) {
            throw ValidationException::withMessages([
                'playlist_id' => __('Choose a playlist before publishing this channel.'),
            ]);
        }
    }

    protected function assertLive(?LiveStreamProtocol $protocol, ?string $url, ChannelStatus $status): void
    {
        $complete = $protocol !== null && is_string($url) && $url !== '' && preg_match('/^[a-z][a-z0-9+\.\-]*:\/\/.+/i', $url) === 1;

        if (! $complete && in_array($status, [ChannelStatus::Published, ChannelStatus::Scheduled], true)) {
            throw ValidationException::withMessages([
                'live_url' => __('A stream protocol and URL are required before publishing a live channel.'),
            ]);
        }

        if ($url !== null && $url !== '' && preg_match('/^[a-z][a-z0-9+\.\-]*:\/\/.+/i', $url) !== 1) {
            throw ValidationException::withMessages([
                'live_url' => __('Enter a valid stream URL, including the protocol.'),
            ]);
        }
    }

    protected function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
