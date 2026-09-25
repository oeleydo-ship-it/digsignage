<?php

namespace App\Enums;

enum PlanFeature: string
{
    case QueueManagement = 'queue_management';
    case MultiZone = 'multi_zone';
    case Emergencies = 'emergencies';
    case PartnerApi = 'partner_api';
    case Webhooks = 'webhooks';
    case Analytics = 'analytics';
    case ProofOfPlay = 'proof_of_play';
    case Designer = 'designer';
    case Schedules = 'schedules';
    case RoomBooking = 'room_booking';

    public function label(): string
    {
        return match ($this) {
            self::QueueManagement => 'Queue Management',
            self::MultiZone => 'Multi-zone channels',
            self::Emergencies => 'Emergency broadcasts',
            self::PartnerApi => 'Partner API',
            self::Webhooks => 'Webhooks',
            self::Analytics => 'Analytics',
            self::ProofOfPlay => 'Proof of play',
            self::Designer => 'Designer',
            self::Schedules => 'Schedules',
            self::RoomBooking => 'Room booking',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $feature) => $feature->value, self::cases());
    }
}
