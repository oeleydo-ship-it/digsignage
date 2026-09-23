<?php

namespace App\Enums;

enum PlatformAuditAction: string
{
    case ImpersonationStarted = 'impersonation_started';
    case ImpersonationStopped = 'impersonation_stopped';
    case OrganizationUpdated = 'organization_updated';
    case OrganizationSuspended = 'organization_suspended';
    case OrganizationRestored = 'organization_restored';
    case FeatureFlagUpdated = 'feature_flag_updated';
    case AnnouncementPublished = 'announcement_published';
    case AnnouncementDeleted = 'announcement_deleted';
    case FailedJobRetried = 'failed_job_retried';
    case FailedJobDeleted = 'failed_job_deleted';
    case PlanUpdated = 'plan_updated';
    case PlatformAdminUpdated = 'platform_admin_updated';
    case StorageDiskCreated = 'storage_disk_created';
    case StorageDiskUpdated = 'storage_disk_updated';
    case StorageDiskDeleted = 'storage_disk_deleted';
    case StorageDiskTested = 'storage_disk_tested';
    case OrganizationStorageAssigned = 'organization_storage_assigned';

    public function label(): string
    {
        return match ($this) {
            self::ImpersonationStarted => 'Impersonation started',
            self::ImpersonationStopped => 'Impersonation stopped',
            self::OrganizationUpdated => 'Organization updated',
            self::OrganizationSuspended => 'Organization suspended',
            self::OrganizationRestored => 'Organization restored',
            self::FeatureFlagUpdated => 'Feature flag updated',
            self::AnnouncementPublished => 'Announcement published',
            self::AnnouncementDeleted => 'Announcement deleted',
            self::FailedJobRetried => 'Failed job retried',
            self::FailedJobDeleted => 'Failed job deleted',
            self::PlanUpdated => 'Plan updated',
            self::PlatformAdminUpdated => 'Platform administrator updated',
            self::StorageDiskCreated => 'Storage backend created',
            self::StorageDiskUpdated => 'Storage backend updated',
            self::StorageDiskDeleted => 'Storage backend deleted',
            self::StorageDiskTested => 'Storage backend tested',
            self::OrganizationStorageAssigned => 'Organization storage assigned',
        };
    }
}
