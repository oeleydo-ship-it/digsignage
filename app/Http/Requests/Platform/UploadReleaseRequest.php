<?php

namespace App\Http\Requests\Platform;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadReleaseRequest extends FormRequest
{
    protected $redirectRoute = 'platform.updates.index';

    use PasswordValidationRules;

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
            'package' => ['required', 'file', 'extensions:zip', 'max:'.max(1, (int) config('deploy.max_package_mb')) * 1024],
            'checksum' => ['nullable', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'install_now' => ['boolean'],
            'current_password' => $this->currentPasswordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'checksum.regex' => __('The SHA-256 checksum must be 64 hexadecimal characters.'),
        ];
    }
}
