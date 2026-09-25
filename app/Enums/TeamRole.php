<?php

namespace App\Enums;

enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case ContentManager = 'content_manager';
    case Publisher = 'publisher';
    case Member = 'member';
    case BranchManager = 'branch_manager';
    case QueueSupervisor = 'queue_supervisor';
    case CounterStaff = 'counter_staff';
    case ReportingUser = 'reporting_user';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::ContentManager => 'Content manager',
            self::Publisher => 'Publisher',
            self::BranchManager => 'Branch manager',
            self::QueueSupervisor => 'Queue supervisor',
            self::CounterStaff => 'Counter staff',
            self::ReportingUser => 'Reporting user',
            default => ucfirst($this->value),
        };
    }

    /**
     * Get all the permissions for this role.
     *
     * @return array<TeamPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => TeamPermission::cases(),
            self::Admin => [
                TeamPermission::UpdateTeam,
                TeamPermission::CreateInvitation,
                TeamPermission::CancelInvitation,
                TeamPermission::ViewLocations,
                TeamPermission::CreateLocation,
                TeamPermission::UpdateLocation,
                TeamPermission::DeleteLocation,
                TeamPermission::ViewScreens,
                TeamPermission::CreateScreen,
                TeamPermission::UpdateScreen,
                TeamPermission::DeleteScreen,
                TeamPermission::PairScreen,
                TeamPermission::ViewScreenGroups,
                TeamPermission::CreateScreenGroup,
                TeamPermission::UpdateScreenGroup,
                TeamPermission::DeleteScreenGroup,
                TeamPermission::ViewMedia,
                TeamPermission::CreateMedia,
                TeamPermission::UpdateMedia,
                TeamPermission::DeleteMedia,
                TeamPermission::ViewDesigns,
                TeamPermission::CreateDesign,
                TeamPermission::UpdateDesign,
                TeamPermission::DeleteDesign,
                TeamPermission::ViewTemplates,
                TeamPermission::CreateTemplate,
                TeamPermission::UpdateTemplate,
                TeamPermission::DeleteTemplate,
                TeamPermission::ViewPlaylists,
                TeamPermission::CreatePlaylist,
                TeamPermission::UpdatePlaylist,
                TeamPermission::DeletePlaylist,
                TeamPermission::ViewChannels,
                TeamPermission::CreateChannel,
                TeamPermission::UpdateChannel,
                TeamPermission::DeleteChannel,
                TeamPermission::ViewSchedules,
                TeamPermission::CreateSchedule,
                TeamPermission::UpdateSchedule,
                TeamPermission::DeleteSchedule,
                TeamPermission::ViewEmergencies,
                TeamPermission::CreateEmergency,
                TeamPermission::UpdateEmergency,
                TeamPermission::StartEmergency,
                TeamPermission::StopEmergency,
                TeamPermission::ViewQueue,
                TeamPermission::ManageQueue,
                TeamPermission::CallQueue,
                TeamPermission::TransferQueue,
                TeamPermission::CompleteQueue,
                TeamPermission::CancelQueue,
                TeamPermission::ManageQueueServices,
                TeamPermission::ManageQueueCounters,
                TeamPermission::ManageQueueKiosks,
                TeamPermission::ManageQueueAppointments,
                TeamPermission::ViewQueueReports,
                TeamPermission::ManageQueueSettings,
                TeamPermission::SubmitContent,
                TeamPermission::ApproveContent,
                TeamPermission::PublishContent,
                TeamPermission::ArchiveContent,
                TeamPermission::ViewAuditLogs,
                TeamPermission::ManageBilling,
                TeamPermission::ViewBookings,
                TeamPermission::CreateBooking,
                TeamPermission::ManageBookings,
                TeamPermission::ManageRooms,
            ],
            self::ContentManager => [
                TeamPermission::ViewLocations,
                TeamPermission::ViewScreens,
                TeamPermission::ViewScreenGroups,
                TeamPermission::ViewMedia,
                TeamPermission::CreateMedia,
                TeamPermission::UpdateMedia,
                TeamPermission::DeleteMedia,
                TeamPermission::ViewDesigns,
                TeamPermission::CreateDesign,
                TeamPermission::UpdateDesign,
                TeamPermission::DeleteDesign,
                TeamPermission::ViewTemplates,
                TeamPermission::CreateTemplate,
                TeamPermission::UpdateTemplate,
                TeamPermission::DeleteTemplate,
                TeamPermission::ViewPlaylists,
                TeamPermission::CreatePlaylist,
                TeamPermission::UpdatePlaylist,
                TeamPermission::DeletePlaylist,
                TeamPermission::ViewChannels,
                TeamPermission::CreateChannel,
                TeamPermission::UpdateChannel,
                TeamPermission::DeleteChannel,
                TeamPermission::ViewSchedules,
                TeamPermission::ViewEmergencies,
                TeamPermission::ViewQueue,
                TeamPermission::SubmitContent,
                TeamPermission::ApproveContent,
                TeamPermission::ViewBookings,
                TeamPermission::CreateBooking,
            ],
            self::Publisher => [
                TeamPermission::ViewLocations,
                TeamPermission::ViewScreens,
                TeamPermission::ViewScreenGroups,
                TeamPermission::ViewMedia,
                TeamPermission::ViewDesigns,
                TeamPermission::ViewTemplates,
                TeamPermission::ViewPlaylists,
                TeamPermission::ViewChannels,
                TeamPermission::ViewSchedules,
                TeamPermission::CreateSchedule,
                TeamPermission::UpdateSchedule,
                TeamPermission::ViewEmergencies,
                TeamPermission::ViewQueue,
                TeamPermission::PublishContent,
                TeamPermission::ArchiveContent,
                TeamPermission::ViewBookings,
                TeamPermission::CreateBooking,
            ],
            self::Member => [
                TeamPermission::ViewLocations,
                TeamPermission::ViewScreens,
                TeamPermission::ViewScreenGroups,
                TeamPermission::ViewMedia,
                TeamPermission::ViewDesigns,
                TeamPermission::ViewTemplates,
                TeamPermission::ViewPlaylists,
                TeamPermission::ViewChannels,
                TeamPermission::ViewSchedules,
                TeamPermission::ViewEmergencies,
                TeamPermission::ViewQueue,
                TeamPermission::CallQueue,
                TeamPermission::TransferQueue,
                TeamPermission::CompleteQueue,
                TeamPermission::ViewBookings,
                TeamPermission::CreateBooking,
            ],
            self::BranchManager => [
                TeamPermission::ViewLocations,
                TeamPermission::ViewScreens,
                TeamPermission::ViewQueue,
                TeamPermission::ManageQueue,
                TeamPermission::CallQueue,
                TeamPermission::TransferQueue,
                TeamPermission::CompleteQueue,
                TeamPermission::CancelQueue,
                TeamPermission::ManageQueueServices,
                TeamPermission::ManageQueueCounters,
                TeamPermission::ManageQueueKiosks,
                TeamPermission::ManageQueueAppointments,
                TeamPermission::ViewQueueReports,
                TeamPermission::ManageQueueSettings,
                TeamPermission::ViewBookings,
                TeamPermission::CreateBooking,
                TeamPermission::ManageBookings,
                TeamPermission::ManageRooms,
            ],
            self::QueueSupervisor => [
                TeamPermission::ViewLocations,
                TeamPermission::ViewQueue,
                TeamPermission::ManageQueue,
                TeamPermission::CallQueue,
                TeamPermission::TransferQueue,
                TeamPermission::CompleteQueue,
                TeamPermission::CancelQueue,
                TeamPermission::ManageQueueServices,
                TeamPermission::ManageQueueCounters,
                TeamPermission::ManageQueueKiosks,
                TeamPermission::ManageQueueAppointments,
                TeamPermission::ViewQueueReports,
                TeamPermission::ViewBookings,
                TeamPermission::CreateBooking,
            ],
            self::CounterStaff => [
                TeamPermission::ViewQueue,
                TeamPermission::CallQueue,
                TeamPermission::TransferQueue,
                TeamPermission::CompleteQueue,
                TeamPermission::ViewBookings,
            ],
            self::ReportingUser => [
                TeamPermission::ViewQueue,
                TeamPermission::ViewQueueReports,
                TeamPermission::ViewBookings,
            ],
        };
    }

    /**
     * Determine if the role has the given permission.
     */
    public function hasPermission(TeamPermission $permission): bool
    {
        return in_array($permission, $this->permissions());
    }

    /**
     * Get the hierarchy level for this role.
     * Higher numbers indicate higher privileges.
     */
    public function level(): int
    {
        return match ($this) {
            self::Owner => 4,
            self::Admin => 3,
            self::ContentManager => 2,
            self::Publisher => 2,
            self::Member => 1,
            self::BranchManager => 3,
            self::QueueSupervisor => 2,
            self::CounterStaff => 1,
            self::ReportingUser => 1,
        };
    }

    /**
     * @return list<TeamPermission>
     */
    public function signagePermissions(): array
    {
        return array_values(array_filter(
            $this->permissions(),
            fn (TeamPermission $permission) => str_starts_with($permission->value, 'location:')
                || str_starts_with($permission->value, 'screen:')
                || str_starts_with($permission->value, 'screen-group:'),
        ));
    }

    /**
     * Check if this role is at least as privileged as another role.
     */
    public function isAtLeast(TeamRole $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * Get the roles that can be assigned to team members (excludes Owner).
     *
     * @return array<array{value: string, label: string}>
     */
    public static function assignable(): array
    {
        return collect(self::cases())
            ->filter(fn (self $role) => $role !== self::Owner)
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->toArray();
    }
}
