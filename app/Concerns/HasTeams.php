<?php

namespace App\Concerns;

use App\Data\BillingPermissions;
use App\Data\BookingPermissions;
use App\Data\ChannelPermissions;
use App\Data\DesignPermissions;
use App\Data\EmergencyPermissions;
use App\Data\MediaPermissions;
use App\Data\PlaylistPermissions;
use App\Data\QueuePermissions;
use App\Data\SchedulePermissions;
use App\Data\SignagePermissions;
use App\Data\TeamPermissions;
use App\Data\TemplatePermissions;
use App\Data\UserTeam;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

trait HasTeams
{
    /**
     * Get all of the teams the user belongs to.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all of the teams the user owns.
     *
     * @return HasManyThrough<Team, Membership, $this>
     */
    public function ownedTeams(): HasManyThrough
    {
        return $this->hasManyThrough(
            Team::class,
            Membership::class,
            'user_id',
            'id',
            'id',
            'team_id',
        )->where('team_members.role', TeamRole::Owner->value);
    }

    /**
     * Get all of the memberships for the user.
     *
     * @return HasMany<Membership, $this>
     */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * Get the user's current team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Get the user's personal team.
     */
    public function personalTeam(): ?Team
    {
        return $this->ownedTeams()
            ->where('teams.is_personal', true)
            ->first();
    }

    /**
     * Switch to the given team.
     */
    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);
        $this->setRelation('currentTeam', $team);

        URL::defaults(['current_team' => $team->slug]);

        return true;
    }

    /**
     * Determine if the user belongs to the given team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    /**
     * Determine if the given team is the user's current team.
     */
    public function isCurrentTeam(Team $team): bool
    {
        return $this->current_team_id === $team->id;
    }

    /**
     * Determine if the user is the owner of the given team.
     */
    public function ownsTeam(Team $team): bool
    {
        return $this->teamRole($team) === TeamRole::Owner;
    }

    /**
     * Get the user's role on the given team.
     */
    public function teamRole(Team $team): ?TeamRole
    {
        return $this->teamMemberships()
            ->where('team_id', $team->id)
            ->first()
            ?->role;
    }

    /**
     * Get the user's teams as a collection of UserTeam objects.
     *
     * @return Collection<int, UserTeam>
     */
    public function toUserTeams(bool $includeCurrent = false): Collection
    {
        return $this->teams()
            ->get()
            ->map(fn (Team $team) => ! $includeCurrent && $this->isCurrentTeam($team) ? null : $this->toUserTeam($team))
            ->filter()
            ->values();
    }

    /**
     * Get the user's team as a UserTeam object.
     */
    public function toUserTeam(Team $team): UserTeam
    {
        $role = $this->teamRole($team);

        return new UserTeam(
            id: $team->id,
            name: $team->name,
            slug: $team->slug,
            isPersonal: $team->is_personal,
            role: $role?->value,
            roleLabel: $role?->label(),
            isCurrent: $this->isCurrentTeam($team),
        );
    }

    /**
     * Get the standard permissions for a team as a TeamPermissions object.
     */
    public function toTeamPermissions(Team $team): TeamPermissions
    {
        $role = $this->teamRole($team);

        return new TeamPermissions(
            canUpdateTeam: $role?->hasPermission(TeamPermission::UpdateTeam) ?? false,
            canDeleteTeam: $role?->hasPermission(TeamPermission::DeleteTeam) ?? false,
            canAddMember: $role?->hasPermission(TeamPermission::AddMember) ?? false,
            canUpdateMember: $role?->hasPermission(TeamPermission::UpdateMember) ?? false,
            canRemoveMember: $role?->hasPermission(TeamPermission::RemoveMember) ?? false,
            canCreateInvitation: $role?->hasPermission(TeamPermission::CreateInvitation) ?? false,
            canCancelInvitation: $role?->hasPermission(TeamPermission::CancelInvitation) ?? false,
        );
    }

    /**
     * Get signage permissions for a team.
     */
    public function toSignagePermissions(Team $team): SignagePermissions
    {
        $role = $this->teamRole($team);

        return new SignagePermissions(
            canViewLocations: $role?->hasPermission(TeamPermission::ViewLocations) ?? false,
            canCreateLocation: $role?->hasPermission(TeamPermission::CreateLocation) ?? false,
            canUpdateLocation: $role?->hasPermission(TeamPermission::UpdateLocation) ?? false,
            canDeleteLocation: $role?->hasPermission(TeamPermission::DeleteLocation) ?? false,
            canViewScreens: $role?->hasPermission(TeamPermission::ViewScreens) ?? false,
            canCreateScreen: $role?->hasPermission(TeamPermission::CreateScreen) ?? false,
            canUpdateScreen: $role?->hasPermission(TeamPermission::UpdateScreen) ?? false,
            canDeleteScreen: $role?->hasPermission(TeamPermission::DeleteScreen) ?? false,
            canPairScreen: $role?->hasPermission(TeamPermission::PairScreen) ?? false,
            canViewScreenGroups: $role?->hasPermission(TeamPermission::ViewScreenGroups) ?? false,
            canCreateScreenGroup: $role?->hasPermission(TeamPermission::CreateScreenGroup) ?? false,
            canUpdateScreenGroup: $role?->hasPermission(TeamPermission::UpdateScreenGroup) ?? false,
            canDeleteScreenGroup: $role?->hasPermission(TeamPermission::DeleteScreenGroup) ?? false,
        );
    }

    /**
     * Get media library permissions for a team.
     */
    public function toMediaPermissions(Team $team): MediaPermissions
    {
        $role = $this->teamRole($team);

        return new MediaPermissions(
            canViewMedia: $role?->hasPermission(TeamPermission::ViewMedia) ?? false,
            canCreateMedia: $role?->hasPermission(TeamPermission::CreateMedia) ?? false,
            canUpdateMedia: $role?->hasPermission(TeamPermission::UpdateMedia) ?? false,
            canDeleteMedia: $role?->hasPermission(TeamPermission::DeleteMedia) ?? false,
        );
    }

    /**
     * Get design permissions for a team.
     */
    public function toDesignPermissions(Team $team): DesignPermissions
    {
        $role = $this->teamRole($team);

        return new DesignPermissions(
            canViewDesigns: $role?->hasPermission(TeamPermission::ViewDesigns) ?? false,
            canCreateDesign: $role?->hasPermission(TeamPermission::CreateDesign) ?? false,
            canUpdateDesign: $role?->hasPermission(TeamPermission::UpdateDesign) ?? false,
            canDeleteDesign: $role?->hasPermission(TeamPermission::DeleteDesign) ?? false,
        );
    }

    /**
     * Get template permissions for a team.
     */
    public function toTemplatePermissions(Team $team): TemplatePermissions
    {
        $role = $this->teamRole($team);

        return new TemplatePermissions(
            canViewTemplates: $role?->hasPermission(TeamPermission::ViewTemplates) ?? false,
            canCreateTemplate: $role?->hasPermission(TeamPermission::CreateTemplate) ?? false,
            canUpdateTemplate: $role?->hasPermission(TeamPermission::UpdateTemplate) ?? false,
            canDeleteTemplate: $role?->hasPermission(TeamPermission::DeleteTemplate) ?? false,
            canManagePlatformTemplates: (bool) $this->is_platform_admin,
        );
    }

    /**
     * Get playlist permissions for a team.
     */
    public function toPlaylistPermissions(Team $team): PlaylistPermissions
    {
        $role = $this->teamRole($team);

        return new PlaylistPermissions(
            canViewPlaylists: $role?->hasPermission(TeamPermission::ViewPlaylists) ?? false,
            canCreatePlaylist: $role?->hasPermission(TeamPermission::CreatePlaylist) ?? false,
            canUpdatePlaylist: $role?->hasPermission(TeamPermission::UpdatePlaylist) ?? false,
            canDeletePlaylist: $role?->hasPermission(TeamPermission::DeletePlaylist) ?? false,
        );
    }

    /**
     * Get channel permissions for a team.
     */
    public function toChannelPermissions(Team $team): ChannelPermissions
    {
        $role = $this->teamRole($team);

        return new ChannelPermissions(
            canViewChannels: $role?->hasPermission(TeamPermission::ViewChannels) ?? false,
            canCreateChannel: $role?->hasPermission(TeamPermission::CreateChannel) ?? false,
            canUpdateChannel: $role?->hasPermission(TeamPermission::UpdateChannel) ?? false,
            canDeleteChannel: $role?->hasPermission(TeamPermission::DeleteChannel) ?? false,
        );
    }

    /**
     * Get schedule permissions for a team.
     */
    public function toSchedulePermissions(Team $team): SchedulePermissions
    {
        $role = $this->teamRole($team);

        return new SchedulePermissions(
            canViewSchedules: $role?->hasPermission(TeamPermission::ViewSchedules) ?? false,
            canCreateSchedule: $role?->hasPermission(TeamPermission::CreateSchedule) ?? false,
            canUpdateSchedule: $role?->hasPermission(TeamPermission::UpdateSchedule) ?? false,
            canDeleteSchedule: $role?->hasPermission(TeamPermission::DeleteSchedule) ?? false,
        );
    }

    /**
     * Get emergency broadcast permissions for a team.
     */
    public function toEmergencyPermissions(Team $team): EmergencyPermissions
    {
        $role = $this->teamRole($team);

        return new EmergencyPermissions(
            canViewEmergencies: $role?->hasPermission(TeamPermission::ViewEmergencies) ?? false,
            canCreateEmergency: $role?->hasPermission(TeamPermission::CreateEmergency) ?? false,
            canUpdateEmergency: $role?->hasPermission(TeamPermission::UpdateEmergency) ?? false,
            canStartEmergency: $role?->hasPermission(TeamPermission::StartEmergency) ?? false,
            canStopEmergency: $role?->hasPermission(TeamPermission::StopEmergency) ?? false,
        );
    }

    /**
     * Get queue management permissions for a team.
     */
    public function toQueuePermissions(Team $team, ?TeamRole $role = null): QueuePermissions
    {
        $role ??= $this->teamRole($team);

        return new QueuePermissions(
            canViewQueue: $role?->hasPermission(TeamPermission::ViewQueue) ?? false,
            canManageQueue: $role?->hasPermission(TeamPermission::ManageQueue) ?? false,
            canCallQueue: $role?->hasPermission(TeamPermission::CallQueue) ?? false,
            canTransferQueue: $role?->hasPermission(TeamPermission::TransferQueue) ?? false,
            canCompleteQueue: $role?->hasPermission(TeamPermission::CompleteQueue) ?? false,
            canCancelQueue: $role?->hasPermission(TeamPermission::CancelQueue) ?? false,
            canManageServices: $role?->hasPermission(TeamPermission::ManageQueueServices) ?? false,
            canManageCounters: $role?->hasPermission(TeamPermission::ManageQueueCounters) ?? false,
            canManageKiosks: $role?->hasPermission(TeamPermission::ManageQueueKiosks) ?? false,
            canManageAppointments: $role?->hasPermission(TeamPermission::ManageQueueAppointments) ?? false,
            canViewReports: $role?->hasPermission(TeamPermission::ViewQueueReports) ?? false,
            canManageSettings: $role?->hasPermission(TeamPermission::ManageQueueSettings) ?? false,
        );
    }

    /**
     * Get room booking permissions for a team.
     */
    public function toBookingPermissions(Team $team, ?TeamRole $role = null): BookingPermissions
    {
        $role ??= $this->teamRole($team);

        return new BookingPermissions(
            canViewBookings: $role?->hasPermission(TeamPermission::ViewBookings) ?? false,
            canCreateBooking: $role?->hasPermission(TeamPermission::CreateBooking) ?? false,
            canManageBookings: $role?->hasPermission(TeamPermission::ManageBookings) ?? false,
            canManageRooms: $role?->hasPermission(TeamPermission::ManageRooms) ?? false,
        );
    }

    /**
     * Get billing permissions for a team.
     */
    public function toBillingPermissions(Team $team, ?TeamRole $role = null): BillingPermissions
    {
        $role ??= $this->teamRole($team);

        return new BillingPermissions(
            canManageBilling: $role?->hasPermission(TeamPermission::ManageBilling) ?? false,
        );
    }

    public function fallbackTeam(?Team $excluding = null): ?Team
    {
        return $this->teams()
            ->when($excluding, fn ($query) => $query->where('teams.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(teams.name)')
            ->first();
    }

    /**
     * Determine if the user has the given permission on the team.
     */
    public function hasTeamPermission(Team $team, TeamPermission $permission): bool
    {
        return $this->teamRole($team)?->hasPermission($permission) ?? false;
    }
}
