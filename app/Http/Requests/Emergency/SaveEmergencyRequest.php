<?php

namespace App\Http\Requests\Emergency;

use App\Enums\EmergencySeverity;
use App\Models\Emergency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEmergencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $emergency = $this->route('emergency');

        if ($emergency instanceof Emergency) {
            return $this->user()?->can('update', $emergency) ?? false;
        }

        return $this->user()?->can('create', Emergency::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:4000'],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'background' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'severity' => ['required', Rule::enum(EmergencySeverity::class)],
            'image_id' => ['nullable', 'integer'],
            'video_id' => ['nullable', 'integer'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'screen_ids' => ['nullable', 'array'],
            'screen_ids.*' => ['integer'],
            'location_ids' => ['nullable', 'array'],
            'location_ids.*' => ['integer'],
        ];
    }
}
