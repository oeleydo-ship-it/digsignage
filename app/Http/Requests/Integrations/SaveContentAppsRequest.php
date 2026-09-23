<?php

namespace App\Http\Requests\Integrations;

use App\Support\ContentApps;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveContentAppsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ContentApps::validationRules();
    }
}
