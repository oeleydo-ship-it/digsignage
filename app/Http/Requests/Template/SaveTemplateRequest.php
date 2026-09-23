<?php

namespace App\Http\Requests\Template;

use App\Enums\TemplateCategory;
use App\Enums\TemplateStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTemplateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::enum(TemplateCategory::class)],
            'status' => ['sometimes', Rule::enum(TemplateStatus::class)],
            'platform' => ['sometimes', 'boolean'],
            'width' => ['sometimes', 'integer', 'min:320', 'max:7680'],
            'height' => ['sometimes', 'integer', 'min:240', 'max:4320'],
            'document' => ['sometimes', 'array'],
            'source_design_id' => [
                'nullable',
                'integer',
                Rule::exists('designs', 'id')->where('team_id', $this->user()?->currentTeam?->id),
            ],
        ];
    }
}
