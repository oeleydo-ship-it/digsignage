<?php

namespace App\Jobs;

use App\Actions\Notifications\DispatchSignageAlert;
use App\Enums\SignageAlert;
use App\Models\Team;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSignageAlert implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public int $teamId,
        public string $event,
        public string $title,
        public string $body,
        public array $data = [],
        public ?string $dedupeKey = null,
        public int $dedupeSeconds = 300,
    ) {}

    public function handle(DispatchSignageAlert $dispatch): void
    {
        $team = Team::query()->find($this->teamId);
        $alert = SignageAlert::tryFrom($this->event);

        if ($team === null || $alert === null) {
            return;
        }

        $dispatch->handle(
            $team,
            $alert,
            $this->title,
            $this->body,
            $this->data,
            $this->dedupeKey,
            $this->dedupeSeconds,
        );
    }
}
