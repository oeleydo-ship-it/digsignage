<?php

namespace App\Http\Requests\Signage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveScreenGroupRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;
        $group = $this->route('screenGroup');

        $unique = Rule::unique('screen_groups', 'name')->where('team_id', $teamId);

        if ($group) {
            $unique->ignore($group);
        }

        return [
            'name' => ['required', 'string', 'max:255', $unique],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
