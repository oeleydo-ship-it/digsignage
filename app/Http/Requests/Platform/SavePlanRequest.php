<?php

namespace App\Http\Requests\Platform;

use App\Enums\PlanFeature;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SavePlanRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:80'],
            'screens' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'storage_gb' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'users' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'bandwidth_gb' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'price_usd' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'price_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable'],
            ...collect(PlanFeature::values())->mapWithKeys(fn (string $feature) => [
                'features.'.$feature => ['sometimes', 'boolean'],
            ])->all(),
        ];

        if ($this->isMethod('post')) {
            $rules['key'] = [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('plans', 'key'),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => 'The slug may only contain lowercase letters, numbers, and dashes.',
            'key.unique' => 'That plan slug is already in use.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach (['screens', 'storage_gb', 'users', 'bandwidth_gb'] as $quota) {
            if ($this->exists($quota) && $this->input($quota) === '') {
                $payload[$quota] = null;
            }
        }

        if ($this->isMethod('post')) {
            $slug = $this->input('key') ?? $this->input('slug');

            if (($slug === null || $slug === '') && is_string($this->input('name'))) {
                $slug = $this->input('name');
            }

            $payload['key'] = is_string($slug) ? Str::slug($slug) : $slug;
        }

        if ($this->exists('price_usd')) {
            $usd = $this->input('price_usd');
            $payload['price_cents'] = $usd === null || $usd === ''
                ? null
                : (int) round(((float) $usd) * 100);
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
