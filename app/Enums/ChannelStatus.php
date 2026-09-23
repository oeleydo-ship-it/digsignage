<?php

namespace App\Enums;

enum ChannelStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Archived = 'archived';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Pending approval',
            default => ucfirst($this->value),
        };
    }
}
