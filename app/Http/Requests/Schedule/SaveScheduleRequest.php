<?php

namespace App\Http\Requests\Schedule;

use App\Enums\ScheduleContentType;
use App\Enums\ScheduleRecurrence;
use App\Enums\ScheduleTargetType;
use App\Models\Schedule;
use App\Support\StayOnPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $schedule = $this->route('schedule');

        if ($schedule instanceof Schedule) {
            return $this->user()?->can('update', $schedule) ?? false;
        }

        return $this->user()?->can('create', Schedule::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'content_type' => ['required', Rule::enum(ScheduleContentType::class)],
            'channel_id' => ['nullable', 'integer'],
            'playlist_id' => ['nullable', 'integer'],
            'timezone' => ['required', 'timezone:all'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'recurrence' => ['required', Rule::enum(ScheduleRecurrence::class)],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'min:1', 'max:7'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'is_enabled' => ['sometimes', 'boolean'],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.target_type' => ['required', Rule::enum(ScheduleTargetType::class)],
            'targets.*.screen_id' => ['nullable', 'integer'],
            'targets.*.screen_group_id' => ['nullable', 'integer'],
            'targets.*.location_id' => ['nullable', 'integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $start = $this->input('start_time');
        $end = $this->input('end_time');

        if (is_string($start) && strlen($start) === 8) {
            $this->merge(['start_time' => substr($start, 0, 5)]);
        }

        if (is_string($end) && strlen($end) === 8) {
            $this->merge(['end_time' => substr($end, 0, 5)]);
        }

        if ($this->has('is_enabled')) {
            $this->merge([
                'is_enabled' => filter_var($this->input('is_enabled'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * Validation failures use UrlGenerator::previous(), not redirect()->back().
     */
    protected function getRedirectUrl(): string
    {
        return StayOnPage::url($this);
    }
}
