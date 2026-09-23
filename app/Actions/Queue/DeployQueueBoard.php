<?php

namespace App\Actions\Queue;

use App\Actions\Approval\TransitionContentApproval;
use App\Actions\Channel\SaveChannel;
use App\Actions\Playlist\SavePlaylist;
use App\Enums\ChannelType;
use App\Enums\PlaylistItemType;
use App\Enums\PlaylistTransition;
use App\Models\Channel;
use App\Models\Design;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeployQueueBoard
{
    public function __construct(
        protected SavePlaylist $savePlaylist,
        protected SaveChannel $saveChannel,
        protected TransitionContentApproval $transition,
    ) {}

    /**
     * @param  list<int>  $screenIds
     */
    public function handle(User $user, Team $team, Design $design, array $screenIds): Channel
    {
        return DB::transaction(function () use ($user, $team, $design, $screenIds) {
            $playlist = $this->savePlaylist->handle($user, $team, [
                'name' => $design->name.' · Queue playback',
                'description' => 'Queue display playlist generated from '.$design->name.'.',
                'loop' => true,
                'items' => [[
                    'type' => PlaylistItemType::Design->value,
                    'title' => $design->name,
                    'duration_seconds' => 86400,
                    'transition' => PlaylistTransition::None->value,
                    'transition_ms' => 0,
                    'enabled' => true,
                    'design_id' => $design->id,
                ]],
            ]);
            $playlist = $this->transition->publish($user, $playlist, 'Published for queue display deployment.');

            $channel = $this->saveChannel->handle($user, $team, [
                'name' => $design->name.' · Queue channel',
                'description' => 'Queue display channel generated from '.$design->name.'.',
                'type' => ChannelType::Playlist->value,
                'playlist_id' => $playlist->id,
                'width' => $design->width,
                'height' => $design->height,
                'screen_ids' => $screenIds,
            ]);

            return $this->transition->publish($user, $channel, 'Published for queue display deployment.');
        });
    }
}
