<?php

namespace App\Enums;

enum DesignStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Published = 'published';
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
