<?php

namespace App\Http\Requests\Signage;

use App\Enums\ScreenOrientation;
use App\Support\StayOnPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PairScreenRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:16'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('team_id', $teamId),
            ],
            'orientation' => ['required', Rule::enum(ScreenOrientation::class)],
            'resolution_width' => ['nullable', 'integer', 'min:1', 'max:7680'],
            'resolution_height' => ['nullable', 'integer', 'min:1', 'max:7680'],
            'timezone' => ['nullable', 'timezone:all'],
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
