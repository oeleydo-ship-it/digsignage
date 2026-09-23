<?php

namespace App\Http\Requests\Queue;

use App\Data\QueueVoiceConfig;
use App\Support\StayOnPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQueueVoiceSettingsRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'languages' => ['required', 'array', 'min:1', 'max:4'],
            'languages.*' => ['required', 'string', Rule::in(QueueVoiceConfig::LANGUAGES)],
            'voice' => ['nullable', 'string', 'max:255'],
            'speed' => ['required', 'numeric', 'min:0.5', 'max:2'],
            'volume' => ['required', 'numeric', 'min:0', 'max:1'],
            'repeat_count' => ['required', 'integer', 'min:1', 'max:5'],
            'chime' => ['required', 'boolean'],
            'announcement_delay_seconds' => ['required', 'numeric', 'min:0', 'max:30'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('voice') === '') {
            $this->merge(['voice' => null]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return StayOnPage::url($this);
    }
}
