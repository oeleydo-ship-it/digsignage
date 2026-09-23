<?php

namespace App\Http\Requests\Queue;

use App\Support\StayOnPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveQueueStarvationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'max_priority_wait_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'promote_after_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['max_priority_wait_seconds', 'promote_after_seconds'] as $key) {
            $value = $this->input($key);

            if ($value === '' || $value === '0') {
                $merge[$key] = null;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    protected function getRedirectUrl(): string
    {
        return StayOnPage::url($this);
    }
}
