<?php

namespace App\Http\Requests\Signage;

use App\Support\StayOnPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateScreensRequest extends FormRequest
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
            'action' => ['required', 'in:disable,assign_location,assign_group'],
            'screen_ids' => ['required', 'array', 'min:1'],
            'screen_ids.*' => ['integer', Rule::exists('screens', 'id')->where('team_id', $teamId)],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('team_id', $teamId),
            ],
            'screen_group_id' => [
                Rule::requiredIf($this->input('action') === 'assign_group'),
                'nullable',
                'integer',
                Rule::exists('screen_groups', 'id')->where('team_id', $teamId),
            ],
        ];
    }

    /**
     * Validation failures use UrlGenerator::previous(), not redirect()->back().
     */
    protected function getRedirectUrl(): string
    {
        return StayOnPage::url($this);
    }
}
