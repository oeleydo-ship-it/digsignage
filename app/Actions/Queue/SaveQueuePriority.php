<?php

namespace App\Actions\Queue;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Models\QueuePriority;
use App\Models\Team;
use App\Models\User;
use App\Support\QueueAuditSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaveQueuePriority
{
    public function __construct(
        protected RecordOrganizationAudit $recordOrganizationAudit,
    ) {
        //
    }

    /**
     * Create or update a team-defined queue priority.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Team $team,
        array $attributes,
        ?QueuePriority $priority = null,
        ?User $actor = null,
    ): QueuePriority {
        return DB::transaction(function () use ($team, $attributes, $priority, $actor) {
            $creating = $priority === null || ! $priority->exists;
            $before = $creating ? null : QueueAuditSnapshot::priority($priority);
            $priority ??= new QueuePriority(['team_id' => $team->id]);

            $code = $this->code($team, $attributes, $priority, $creating);

            $priority->fill([
                'name' => $attributes['name'],
                'code' => $code,
                'weight' => (int) $attributes['weight'],
                'color' => $attributes['color'] ?? null,
                'sort_order' => array_key_exists('sort_order', $attributes)
                    ? (int) $attributes['sort_order']
                    : ($creating ? $this->nextSort($team) : (int) $priority->sort_order),
                'is_active' => array_key_exists('is_active', $attributes)
                    ? (bool) $attributes['is_active']
                    : ($creating ? true : $priority->is_active),
            ]);

            $priority->save();

            $priority = $priority->refresh();

            $this->recordOrganizationAudit->handle(
                $team,
                AuditAction::QueuePriorityChanged,
                $actor,
                'queue_priority',
                $priority->id,
                $before,
                QueueAuditSnapshot::priority($priority),
            );

            return $priority;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function code(Team $team, array $attributes, QueuePriority $priority, bool $creating): string
    {
        $code = $attributes['code'] ?? null;

        if (is_string($code) && $code !== '') {
            return Str::slug($code);
        }

        if (! $creating && $priority->code !== '') {
            return $priority->code;
        }

        $base = Str::slug((string) $attributes['name']);

        if ($base === '') {
            $base = 'priority';
        }

        $candidate = $base;
        $suffix = 2;

        while (
            QueuePriority::query()
                ->where('team_id', $team->id)
                ->where('code', $candidate)
                ->when($priority->exists, fn ($query) => $query->whereKeyNot($priority->id))
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function nextSort(Team $team): int
    {
        return (int) QueuePriority::query()->where('team_id', $team->id)->max('sort_order') + 1;
    }
}
