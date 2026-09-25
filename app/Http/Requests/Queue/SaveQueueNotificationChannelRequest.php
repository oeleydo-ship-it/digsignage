<?php

namespace App\Http\Requests\Queue;

use App\Models\QueueSetting;
use App\Support\QueueNotificationChannels;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveQueueNotificationChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->user()?->currentTeam;

        return $team !== null && Gate::allows('update', QueueSetting::resolveForTeam($team));
    }

    /**
     * Secret fields may be left blank to keep the saved value.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $channel = (string) $this->route('channel');

        return match ($channel) {
            'sms', 'whatsapp' => [
                'driver' => ['nullable', Rule::in(QueueNotificationChannels::DRIVERS[$channel])],
                'twilio_sid' => ['nullable', 'string', 'max:64', 'regex:/^AC[a-zA-Z0-9]{32}$/'],
                'twilio_token' => ['nullable', 'string', 'max:128'],
                'twilio_from' => ['nullable', 'string', 'max:40'],
                'meta_phone_number_id' => ['nullable', 'string', 'max:40', 'regex:/^\d+$/'],
                'meta_token' => ['nullable', 'string', 'max:1000'],
                'meta_template' => ['nullable', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
                'meta_template_language' => ['nullable', 'string', 'max:15', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/'],
                'webhook_url' => ['nullable', 'url:https,http', 'max:500'],
                'webhook_secret' => ['nullable', 'string', 'max:255'],
            ],
            'push' => ['enabled' => ['required', 'boolean']],
            'email' => [
                'enabled' => ['required', 'boolean'],
                'reply_to' => ['nullable', 'email', 'max:255'],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'twilio_sid.regex' => __('The Twilio account SID starts with AC followed by 32 characters.'),
            'meta_phone_number_id.regex' => __('The phone number ID is the number shown in Meta → WhatsApp → API setup.'),
            'meta_template.regex' => __('Template names use lowercase letters, numbers and underscores.'),
            'meta_template_language.regex' => __('Use a language code such as en_US or ar.'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $driver = $this->input('driver');

            if ($driver === 'twilio' && blank($this->input('twilio_from'))) {
                $validator->errors()->add('twilio_from', __('Enter the Twilio number messages are sent from.'));
            }

            if ($driver === 'twilio' && blank($this->input('twilio_sid'))) {
                $validator->errors()->add('twilio_sid', __('Enter the Twilio account SID.'));
            }

            if ($driver === 'meta' && blank($this->input('meta_phone_number_id'))) {
                $validator->errors()->add('meta_phone_number_id', __('Enter the WhatsApp phone number ID.'));
            }

            if ($driver === 'webhook' && blank($this->input('webhook_url'))) {
                $validator->errors()->add('webhook_url', __('Enter the webhook URL.'));
            }
        }];
    }
}
