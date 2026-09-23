<?php

namespace App\Enums;

enum QueueNotificationChannel: string
{
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
    case Email = 'email';
    case Push = 'push';

    public function label(): string
    {
        return match ($this) {
            self::Sms => 'SMS',
            self::WhatsApp => 'WhatsApp',
            self::Email => 'Email',
            self::Push => 'Push',
        };
    }
}
