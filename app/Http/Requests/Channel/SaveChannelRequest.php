<?php

namespace App\Http\Requests\Channel;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\LiveStreamProtocol;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveChannelRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['sometimes', Rule::enum(ChannelType::class)],
            'status' => ['sometimes', Rule::enum(ChannelStatus::class)],
            'playlist_id' => ['nullable', 'integer'],
            'live_protocol' => ['nullable', Rule::enum(LiveStreamProtocol::class)],
            'live_url' => ['nullable', 'string', 'max:2048'],
            'width' => ['sometimes', 'integer', 'min:320', 'max:7680'],
            'height' => ['sometimes', 'integer', 'min:240', 'max:4320'],
            'scheduled_at' => ['nullable', 'date'],
            'screen_ids' => ['sometimes', 'array'],
            'screen_ids.*' => ['integer'],
            'zones' => ['sometimes', 'array', 'max:24'],
            'zones.*.name' => ['required_with:zones', 'string', 'max:255'],
            'zones.*.playlist_id' => ['nullable', 'integer'],
            'zones.*.x' => ['required_with:zones', 'integer', 'min:0', 'max:100'],
            'zones.*.y' => ['required_with:zones', 'integer', 'min:0', 'max:100'],
            'zones.*.width' => ['required_with:zones', 'integer', 'min:1', 'max:100'],
            'zones.*.height' => ['required_with:zones', 'integer', 'min:1', 'max:100'],
            'zones.*.z_index' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ];
    }
}
