<?php

namespace App\Http\Requests\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMediaRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('media_folders', 'id')->where('team_id', $teamId),
            ],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'archived' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags');

        if (is_string($tags)) {
            $this->merge([
                'tags' => collect(explode(',', $tags))
                    ->map(fn (string $name) => trim($name))
                    ->filter()
                    ->values()
                    ->all(),
            ]);
        }

        if ($this->input('folder_id') === '') {
            $this->merge(['folder_id' => null]);
        }

        if ($this->has('archived')) {
            $this->merge(['archived' => $this->boolean('archived')]);
        }
    }
}
