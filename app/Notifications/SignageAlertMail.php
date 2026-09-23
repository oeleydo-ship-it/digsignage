<?php

namespace App\Notifications;

use App\Enums\SignageAlert;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignageAlertMail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public Team $team,
        public SignageAlert $alert,
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('['.$this->team->name.'] '.$this->title)
            ->line($this->body)
            ->line(__('Event: :event', ['event' => $this->alert->label()]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'team_id' => $this->team->id,
            'event' => $this->alert->value,
            'title' => $this->title,
            'data' => $this->data,
        ];
    }
}
