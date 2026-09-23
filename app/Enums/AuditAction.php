<?php

namespace App\Enums;

enum AuditAction: string
{
    case UserLoggedIn = 'user_logged_in';
    case UserInvited = 'user_invited';
    case RoleChanged = 'role_changed';
    case ScreenRegistered = 'screen_registered';
    case ScreenDeleted = 'screen_deleted';
    case MediaUploaded = 'media_uploaded';
    case ContentChanged = 'content_changed';
    case PlaylistPublished = 'playlist_published';
    case ScheduleChanged = 'schedule_changed';
    case EmergencyActivated = 'emergency_activated';
    case DeviceCommandExecuted = 'device_command_executed';
    case ApiTokenCreated = 'api_token_created';
    case ApiTokenRevoked = 'api_token_revoked';
    case ApiTokenRotated = 'api_token_rotated';
    case WebhookCreated = 'webhook_created';
    case WebhookUpdated = 'webhook_updated';
    case WebhookDeleted = 'webhook_deleted';
    case WebhookSecretRotated = 'webhook_secret_rotated';
    case DeviceCredentialsRotated = 'device_credentials_rotated';
    case UserLoginFailed = 'user_login_failed';
    case IntegrationsUpdated = 'integrations_updated';
    case QueueTicketCreated = 'queue_ticket_created';
    case QueueTicketCalled = 'queue_ticket_called';
    case QueueTicketRecalled = 'queue_ticket_recalled';
    case QueueTicketTransferred = 'queue_ticket_transferred';
    case QueueTicketCompleted = 'queue_ticket_completed';
    case QueueTicketCancelled = 'queue_ticket_cancelled';
    case QueueTicketNoShow = 'queue_ticket_no_show';
    case QueueTicketHeld = 'queue_ticket_held';
    case QueueTicketResumed = 'queue_ticket_resumed';
    case QueuePriorityChanged = 'queue_priority_changed';
    case QueueCounterOpened = 'queue_counter_opened';
    case QueueCounterClosed = 'queue_counter_closed';
    case QueueCounterChanged = 'queue_counter_changed';
    case QueueApiRequested = 'queue_api_requested';

    public function label(): string
    {
        return match ($this) {
            self::UserLoggedIn => 'User logged in',
            self::UserInvited => 'User invited',
            self::RoleChanged => 'Role changed',
            self::ScreenRegistered => 'Screen registered',
            self::ScreenDeleted => 'Screen deleted',
            self::MediaUploaded => 'Media uploaded',
            self::ContentChanged => 'Content changed',
            self::PlaylistPublished => 'Playlist published',
            self::ScheduleChanged => 'Schedule changed',
            self::EmergencyActivated => 'Emergency activated',
            self::DeviceCommandExecuted => 'Device command executed',
            self::ApiTokenCreated => 'API token created',
            self::ApiTokenRevoked => 'API token revoked',
            self::ApiTokenRotated => 'API token rotated',
            self::WebhookCreated => 'Webhook created',
            self::WebhookUpdated => 'Webhook updated',
            self::WebhookDeleted => 'Webhook deleted',
            self::WebhookSecretRotated => 'Webhook secret rotated',
            self::DeviceCredentialsRotated => 'Device credentials rotated',
            self::UserLoginFailed => 'Login failed',
            self::IntegrationsUpdated => 'Apps updated',
            self::QueueTicketCreated => 'Queue ticket created',
            self::QueueTicketCalled => 'Queue ticket called',
            self::QueueTicketRecalled => 'Queue ticket recalled',
            self::QueueTicketTransferred => 'Queue ticket transferred',
            self::QueueTicketCompleted => 'Queue ticket completed',
            self::QueueTicketCancelled => 'Queue ticket cancelled',
            self::QueueTicketNoShow => 'Queue ticket marked no-show',
            self::QueueTicketHeld => 'Queue ticket held',
            self::QueueTicketResumed => 'Queue ticket resumed',
            self::QueuePriorityChanged => 'Queue priority changed',
            self::QueueCounterOpened => 'Queue counter opened',
            self::QueueCounterClosed => 'Queue counter closed',
            self::QueueCounterChanged => 'Queue counter changed',
            self::QueueApiRequested => 'Queue API requested',
        };
    }
}
