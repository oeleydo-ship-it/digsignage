<?php

namespace App\Support;

use App\Enums\QueueNotificationEvent;
use App\Models\QueueSetting;
use App\Models\Team;

/**
 * Editable subject and message for each customer notification event, with
 * {placeholders} filled in when a notification is sent.
 */
class QueueNotificationTemplates
{
    /** @var array<string, string> */
    public const PLACEHOLDERS = [
        'ticket' => 'Ticket number',
        'name' => 'Customer name',
        'service' => 'Service',
        'counter' => 'Counter',
        'position' => 'Place in line',
        'location' => 'Location',
        'link' => 'Live ticket page',
        'team' => 'Organization name',
        'time' => 'Appointment time',
        'reference' => 'Appointment reference',
    ];

    /**
     * @return array<string, array{subject: string, body: string}>
     */
    public static function defaults(): array
    {
        return [
            QueueNotificationEvent::TicketCreated->value => [
                'subject' => 'Your ticket {ticket}',
                'body' => 'Your ticket for {service} is {ticket}. You are number {position} in line.',
            ],
            QueueNotificationEvent::FiveAhead->value => [
                'subject' => 'Ticket {ticket}: 5 ahead of you',
                'body' => 'There are 5 customers ahead of ticket {ticket}. Please start making your way back.',
            ],
            QueueNotificationEvent::ThreeAhead->value => [
                'subject' => 'Ticket {ticket}: 3 ahead of you',
                'body' => 'There are 3 customers ahead of ticket {ticket}.',
            ],
            QueueNotificationEvent::CustomerNext->value => [
                'subject' => 'Ticket {ticket}: you are next',
                'body' => 'Ticket {ticket} is next. Please be ready.',
            ],
            QueueNotificationEvent::TicketCalled->value => [
                'subject' => 'Ticket {ticket} called',
                'body' => 'Ticket {ticket} has been called. Please proceed to {counter}.',
            ],
            QueueNotificationEvent::TicketTransferred->value => [
                'subject' => 'Ticket {ticket} transferred',
                'body' => 'Ticket {ticket} was transferred to {service}.',
            ],
            QueueNotificationEvent::AppointmentApproaching->value => [
                'subject' => 'Appointment reminder',
                'body' => 'Your {service} appointment is at {time}. Reference: {reference}.',
            ],
        ];
    }

    /**
     * The team's templates, with defaults for any not customised.
     *
     * @return array<string, array{subject: string, body: string}>
     */
    public function all(int $teamId): array
    {
        $settings = QueueSetting::query()->where('team_id', $teamId)->value('settings');
        $settings = is_string($settings) ? json_decode($settings, true) : $settings;
        $stored = is_array($settings) && is_array($settings['notification_templates'] ?? null)
            ? $settings['notification_templates']
            : [];
        $templates = [];

        foreach (self::defaults() as $event => $default) {
            $custom = is_array($stored[$event] ?? null) ? $stored[$event] : [];
            $templates[$event] = [
                'subject' => filled($custom['subject'] ?? null) ? (string) $custom['subject'] : $default['subject'],
                'body' => filled($custom['body'] ?? null) ? (string) $custom['body'] : $default['body'],
            ];
        }

        return $templates;
    }

    /**
     * @param  array<string, string|int|null>  $values
     * @return array{subject: string, body: string}
     */
    public function render(int $teamId, QueueNotificationEvent $event, array $values): array
    {
        $template = $this->all($teamId)[$event->value];

        return [
            'subject' => self::fill($template['subject'], $values),
            'body' => self::fill($template['body'], $values),
        ];
    }

    /**
     * @param  array<string, array{subject?: string|null, body?: string|null}>  $templates
     */
    public function save(Team $team, array $templates): void
    {
        $setting = QueueSetting::resolveForTeam($team);
        $all = $setting->settings ?? [];
        $defaults = self::defaults();
        $stored = [];

        foreach ($defaults as $event => $default) {
            $subject = trim((string) ($templates[$event]['subject'] ?? ''));
            $body = trim((string) ($templates[$event]['body'] ?? ''));

            // Only keep what differs from the default, so improved defaults reach teams.
            if (($subject !== '' && $subject !== $default['subject']) || ($body !== '' && $body !== $default['body'])) {
                $stored[$event] = [
                    'subject' => $subject !== '' ? $subject : $default['subject'],
                    'body' => $body !== '' ? $body : $default['body'],
                ];
            }
        }

        $all['notification_templates'] = $stored;
        $setting->forceFill(['settings' => $all])->save();
    }

    /**
     * @param  array<string, string|int|null>  $values
     */
    public static function fill(string $template, array $values): string
    {
        $replacements = [];

        foreach (array_keys(self::PLACEHOLDERS) as $key) {
            $replacements['{'.$key.'}'] = (string) ($values[$key] ?? '');
        }

        // Tidy gaps left by empty placeholders ("Reference: ." → "Reference:.").
        $text = strtr($template, $replacements);
        $text = (string) preg_replace('/[ \t]{2,}/', ' ', $text);

        return trim((string) preg_replace('/\s+([.,!?])/', '$1', $text));
    }

    /**
     * Sample values for previews and test messages.
     *
     * @return array<string, string>
     */
    public static function sample(string $team): array
    {
        return [
            'ticket' => 'A102',
            'name' => 'Alex',
            'service' => 'Customer service',
            'counter' => 'Counter 3',
            'position' => '4',
            'location' => 'Main branch',
            'link' => url('/queue-ticket/example'),
            'team' => $team,
            'time' => now()->addHour()->format('M j, g:i A'),
            'reference' => 'APT-1234',
        ];
    }
}
