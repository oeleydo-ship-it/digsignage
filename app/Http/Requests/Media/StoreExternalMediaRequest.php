<?php

namespace App\Http\Requests\Media;

use App\Enums\MediaType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalMediaRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([
                MediaType::Url->value,
                MediaType::LiveStream->value,
                MediaType::Video->value,
            ])],
            'external_url' => ['required', 'string', 'url', 'max:2048'],
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('media_folders', 'id')->where('team_id', $teamId),
            ],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('folder_id') === '') {
            $this->merge(['folder_id' => null]);
        }
    }
}
