<?php

namespace App\Actions\Queue;

use App\Actions\Design\SaveDesign;
use App\Models\Design;
use App\Models\Team;
use App\Models\User;
use App\Support\QueueBoardPresets;

class CreateQueueBoard
{
    public function __construct(protected SaveDesign $saveDesign) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Team $team, array $attributes): Design
    {
        $preset = (string) ($attributes['preset'] ?? 'classic');
        $catalog = collect(QueueBoardPresets::catalog())->firstWhere('key', $preset);
        $name = trim((string) ($attributes['name'] ?? ''));

        return $this->saveDesign->handle($user, $team, [
            'name' => $name !== '' ? $name : (string) ($catalog['name'] ?? 'Queue board'),
            'description' => 'Queue display board created from the '.($catalog['name'] ?? $preset).' preset.',
            'width' => 1920,
            'height' => 1080,
            'document' => QueueBoardPresets::document($preset, $attributes),
        ]);
    }
}
