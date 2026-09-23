<?php

namespace App\Enums;

enum SignageAlert: string
{
    case ScreenOffline = 'screen_offline';
    case ScreenRestored = 'screen_restored';
    case StorageLow = 'storage_low';
    case SyncFailed = 'sync_failed';
    case MediaProcessingFailed = 'media_processing_failed';
    case EmergencyActivated = 'emergency_activated';
    case EmergencyStopped = 'emergency_stopped';
    case SubscriptionIssue = 'subscription_issue';
    case PlayerOutdated = 'player_outdated';
    case ContentPendingApproval = 'content_pending_approval';
    case ContentApproved = 'content_approved';
    case ContentRejected = 'content_rejected';
    case ContentPublished = 'content_published';
    case DeviceCommandFailed = 'device_command_failed';
    case QueueAverageWaitHigh = 'queue_average_wait_high';
    case QueueWaitingCountHigh = 'queue_waiting_count_high';
    case QueueCustomerWaitHigh = 'queue_customer_wait_high';
    case QueueNoCounterAvailable = 'queue_no_counter_available';
    case QueueCapacityReached = 'queue_capacity_reached';
    case QueueCounterOffline = 'queue_counter_offline';

    public function label(): string
    {
        return match ($this) {
            self::ScreenOffline => 'Screen offline',
            self::ScreenRestored => 'Screen restored',
            self::StorageLow => 'Storage low',
            self::SyncFailed => 'Synchronization failed',
            self::MediaProcessingFailed => 'Media processing failed',
            self::EmergencyActivated => 'Emergency activated',
            self::EmergencyStopped => 'Emergency stopped',
            self::SubscriptionIssue => 'Subscription issue',
            self::PlayerOutdated => 'Player outdated',
            self::ContentPendingApproval => 'Content pending approval',
            self::ContentApproved => 'Content approved',
            self::ContentRejected => 'Content rejected',
            self::ContentPublished => 'Content published',
            self::DeviceCommandFailed => 'Device command failed',
            self::QueueAverageWaitHigh => 'Queue average wait high',
            self::QueueWaitingCountHigh => 'Queue waiting count high',
            self::QueueCustomerWaitHigh => 'Customer wait high',
            self::QueueNoCounterAvailable => 'No queue counter available',
            self::QueueCapacityReached => 'Queue capacity reached',
            self::QueueCounterOffline => 'Queue counter offline',
        };
    }

    /**
     * @return array{email: bool, in_app: bool, webhook: bool, slack: bool}
     */
    public function defaultChannels(): array
    {
        return [
            'email' => true,
            'in_app' => true,
            'webhook' => false,
            'slack' => false,
        ];
    }
}
