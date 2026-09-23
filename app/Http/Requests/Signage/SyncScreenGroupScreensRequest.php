<?php

namespace App\Http\Requests\Signage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncScreenGroupScreensRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'screen_ids' => ['present', 'array'],
            'screen_ids.*' => ['integer', Rule::exists('screens', 'id')->where('team_id', $teamId)],
        ];
    }
}
