<?php

namespace App\Http\Requests\Platform;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InstallGitHubReleaseRequest extends FormRequest
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
            'url' => ['required', 'string', 'max:500'],
            'current_password' => $this->currentPasswordRules(),
        ];
    }
}
