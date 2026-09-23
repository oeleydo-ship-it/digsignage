<?php

namespace App\Http\Requests\Playlist;

use App\Enums\PlaylistItemType;
use App\Enums\PlaylistStatus;
use App\Enums\PlaylistTransition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePlaylistRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::enum(PlaylistStatus::class)],
            'loop' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array', 'max:200'],
            'items.*.type' => ['required_with:items', Rule::enum(PlaylistItemType::class)],
            'items.*.title' => ['required_with:items', 'string', 'max:255'],
            'items.*.duration_seconds' => ['required_with:items', 'integer', 'min:1', 'max:86400'],
            'items.*.transition' => ['sometimes', Rule::enum(PlaylistTransition::class)],
            'items.*.transition_ms' => ['sometimes', 'integer', 'min:0', 'max:5000'],
            'items.*.enabled' => ['sometimes', 'boolean'],
            'items.*.available_from' => ['nullable', 'date'],
            'items.*.available_until' => ['nullable', 'date'],
            'items.*.media_id' => ['nullable', 'integer'],
            'items.*.design_id' => ['nullable', 'integer'],
            'items.*.template_id' => ['nullable', 'integer'],
            'items.*.url' => ['nullable', 'string', 'max:2048'],
            'items.*.widget_key' => ['nullable', 'string', 'max:100'],
            'items.*.widget_settings' => ['nullable', 'array'],
        ];
    }
}
