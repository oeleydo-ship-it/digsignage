<?php

namespace App\Actions\Channel;

use App\Enums\ChannelStatus;
use App\Models\Channel;
use App\Models\User;

class DuplicateChannel
{
    /**
     * Duplicate a channel and its zones as a new draft.
     */
    public function handle(User $user, Channel $channel): Channel
    {
        $channel->loadMissing('zones');

        return app(SaveChannel::class)->handle($user, $channel->team, [
            'name' => $channel->name.' copy',
            'description' => $channel->description,
            'type' => $channel->type->value,
            'status' => ChannelStatus::Draft->value,
            'playlist_id' => $channel->playlist_id,
            'live_protocol' => $channel->live_protocol?->value,
            'live_url' => $channel->live_url,
            'width' => $channel->width,
            'height' => $channel->height,
            'zones' => $channel->zonesSnapshot(),
        ]);
    }
}
