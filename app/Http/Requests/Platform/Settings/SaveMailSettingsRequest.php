<?php

namespace App\Http\Requests\Platform\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMailSettingsRequest extends FormRequest
{
    protected $redirectRoute = 'platform.settings.index';

    public function authorize(): bool
    {
        return $this->user()?->is_platform_admin === true;
    }

    /**
     * Blank password keeps the stored password.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $smtp = $this->input('mailer') === 'smtp';

        return [
            'mailer' => ['required', 'in:smtp,log'],
            'host' => [Rule::requiredIf($smtp), 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/'],
            'port' => [Rule::requiredIf($smtp), 'nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:500'],
            'encryption' => ['required', 'in:tls,ssl,none'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'host.regex' => __('Enter a host name such as smtp.example.com.'),
        ];
    }
}
