<?php

namespace App\Http\Requests\Queue;

use App\Enums\TeamPermission;
use App\Models\Design;
use App\Support\QueueBoardPresets;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateQueueBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $team = $user?->currentTeam;

        return $user !== null
            && $team !== null
            && $user->hasTeamPermission($team, TeamPermission::ManageQueue)
            && $user->can('create', Design::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'preset' => ['required', 'string', Rule::in(QueueBoardPresets::keys())],
            'name' => ['nullable', 'string', 'max:255'],
            'service_id' => ['nullable', 'integer', Rule::exists('queue_services', 'id')->where('team_id', $teamId)],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('team_id', $teamId)],
            'counter_id' => ['nullable', 'integer', Rule::exists('queue_counters', 'id')->where('team_id', $teamId)],
        ];
    }
}
