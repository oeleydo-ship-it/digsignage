<?php

namespace App\Http\Requests\Partner;

use App\Enums\WebhookEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWebhookEndpointRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', Rule::enum(WebhookEvent::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
