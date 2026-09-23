<?php

namespace App\Http\Requests\Queue;

use App\Models\QueueKiosk;
use App\Support\StayOnPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQueueKioskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $kiosk = $this->route('queueKiosk');

        if ($kiosk instanceof QueueKiosk) {
            return $user->can('update', $kiosk);
        }

        return $user->can('create', QueueKiosk::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;
        $color = ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('team_id', $teamId),
            ],
            'printer_enabled' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'pin' => ['nullable', 'string', 'max:12'],
            'clear_pin' => ['sometimes', 'boolean'],
            'branding' => ['nullable', 'array'],
            'branding.logo_url' => ['nullable', 'string', 'max:2048'],
            'branding.title' => ['nullable', 'string', 'max:255'],
            'branding.footer' => ['nullable', 'string', 'max:500'],
            'branding.print_qr_code' => ['sometimes', 'boolean'],
            'branding.colors' => ['nullable', 'array'],
            'branding.colors.background' => $color,
            'branding.colors.primary' => $color,
            'branding.colors.text' => $color,
            'branding.colors.button_text' => $color,
        ];
    }

    /**
     * Validation failures use UrlGenerator::previous(), not redirect()->back().
     */
    protected function getRedirectUrl(): string
    {
        return StayOnPage::url($this);
    }
}
