<?php

namespace App\Http\Requests\Design;

use App\Enums\DesignStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDesignRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::enum(DesignStatus::class)],
            'width' => ['sometimes', 'integer', 'min:320', 'max:7680'],
            'height' => ['sometimes', 'integer', 'min:240', 'max:4320'],
            'document' => ['sometimes', 'array'],
            'document.width' => ['sometimes', 'integer'],
            'document.height' => ['sometimes', 'integer'],
            'document.background' => ['sometimes', 'string', 'max:32'],
            'document.elements' => ['sometimes', 'array', 'max:200'],
        ];
    }
}
