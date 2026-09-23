<?php

namespace App\Enums;

enum WebhookEvent: string
{
    case ScreenOnline = 'screen.online';
    case ScreenOffline = 'screen.offline';
    case ContentPublished = 'content.published';
    case PlaylistPublished = 'playlist.published';
    case MediaProcessed = 'media.processed';
    case EmergencyStarted = 'emergency.started';
    case EmergencyStopped = 'emergency.stopped';
    case TicketCreated = 'ticket.created';
    case TicketCalled = 'ticket.called';
    case TicketServing = 'ticket.serving';
    case TicketCompleted = 'ticket.completed';
    case TicketTransferred = 'ticket.transferred';
    case TicketCancelled = 'ticket.cancelled';
    case TicketNoShow = 'ticket.no_show';

    public function label(): string
    {
        return match ($this) {
            self::ScreenOnline => 'Screen online',
            self::ScreenOffline => 'Screen offline',
            self::ContentPublished => 'Content published',
            self::PlaylistPublished => 'Playlist published',
            self::MediaProcessed => 'Media processed',
            self::EmergencyStarted => 'Emergency started',
            self::EmergencyStopped => 'Emergency stopped',
            self::TicketCreated => 'Ticket created',
            self::TicketCalled => 'Ticket called',
            self::TicketServing => 'Ticket serving',
            self::TicketCompleted => 'Ticket completed',
            self::TicketTransferred => 'Ticket transferred',
            self::TicketCancelled => 'Ticket cancelled',
            self::TicketNoShow => 'Ticket marked no-show',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $event) => ['value' => $event->value, 'label' => $event->label()],
            self::cases(),
        );
    }
}
