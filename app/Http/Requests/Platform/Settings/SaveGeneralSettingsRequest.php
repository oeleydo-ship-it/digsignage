<?php

namespace App\Http\Requests\Platform\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveGeneralSettingsRequest extends FormRequest
{
    protected $redirectRoute = 'platform.settings.index';

    public function authorize(): bool
    {
        return $this->user()?->is_platform_admin === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'allow_registration' => ['required', 'boolean'],
            'terms_url' => ['nullable', 'url:https,http', 'max:500'],
            'privacy_url' => ['nullable', 'url:https,http', 'max:500'],
        ];
    }
}
