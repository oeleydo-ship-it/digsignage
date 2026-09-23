<?php

namespace App\Enums;

enum ContentApprovalAction: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Published = 'published';
    case Archived = 'archived';
    case ReturnedToDraft = 'returned_to_draft';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Published => 'Published',
            self::Archived => 'Archived',
            self::ReturnedToDraft => 'Returned to draft',
        };
    }
}
