<?php

namespace App\Actions\Queue;

use App\Models\QueuePriority;
use App\Models\QueueSetting;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class EnsureDefaultQueuePriorities
{
    /**
     * Seed editable default priority levels the first time a team has none.
     */
    public function handle(Team $team): void
    {
        DB::transaction(function () use ($team): void {
            QueueSetting::query()
                ->where('team_id', $team->id)
                ->lockForUpdate()
                ->first();

            if (QueuePriority::query()->where('team_id', $team->id)->exists()) {
                return;
            }

            foreach (QueuePriority::defaultDefinitions() as $definition) {
                QueuePriority::query()->create([
                    'team_id' => $team->id,
                    'name' => $definition['name'],
                    'code' => $definition['code'],
                    'weight' => $definition['weight'],
                    'color' => $definition['color'],
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                ]);
            }
        });
    }
}
