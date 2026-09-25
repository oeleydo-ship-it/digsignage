<?php

namespace App\Http\Requests\Platform\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveBrandingRequest extends FormRequest
{
    protected $redirectRoute = 'platform.settings.index';

    public function authorize(): bool
    {
        return $this->user()?->is_platform_admin === true;
    }

    /**
     * SVG is not accepted: it can carry scripts and is served from this
     * site's own domain.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:512'],
            'remove_logo' => ['boolean'],
            'remove_favicon' => ['boolean'],
            'logo_tone' => ['sometimes', 'in:original,white,black'],
            'show_name' => ['sometimes', 'boolean'],
        ];
    }
}
