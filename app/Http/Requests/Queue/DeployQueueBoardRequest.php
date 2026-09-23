<?php

namespace App\Http\Requests\Queue;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeployQueueBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'design_id' => [
                'required',
                'integer',
                Rule::exists('designs', 'id')
                    ->where('team_id', $teamId)
                    ->where('status', 'published')
                    ->whereNull('deleted_at'),
            ],
            'screen_ids' => ['required', 'array', 'min:1'],
            'screen_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('screens', 'id')->where('team_id', $teamId)->whereNull('deleted_at'),
            ],
        ];
    }
}
