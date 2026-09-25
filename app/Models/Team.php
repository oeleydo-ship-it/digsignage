<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\TeamRole;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_personal
 * @property array<string, mixed>|null $settings
 * @property PlanKey $plan_key
 * @property SubscriptionStatus $subscription_status
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $subscription_ends_at
 * @property string|null $stripe_customer_id
 * @property string|null $stripe_subscription_id
 * @property string|null $stripe_price_id
 * @property string|null $coupon_code
 * @property int $bandwidth_used_bytes
 * @property Carbon|null $suspended_at
 * @property int|null $storage_disk_id
 * @property-read StorageDisk|null $storageDisk
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, TeamInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, Template> $templates
 * @property-read Collection<int, Playlist> $playlists
 * @property-read Collection<int, Channel> $channels
 * @property-read Collection<int, Schedule> $schedules
 * @property-read Collection<int, ApiToken> $apiTokens
 * @property-read Collection<int, WebhookEndpoint> $webhookEndpoints
 * @property-read QueueSetting|null $queueSetting
 * @property-read Collection<int, QueueService> $queueServices
 * @property-read Collection<int, QueuePriority> $queuePriorities
 * @property-read Collection<int, QueueTicket> $queueTickets
 * @property-read Collection<int, QueueCounter> $queueCounters
 * @property-read Collection<int, QueueKiosk> $queueKiosks
 */
#[Fillable([
    'name',
    'slug',
    'is_personal',
    'settings',
    'plan_key',
    'subscription_status',
    'trial_ends_at',
    'subscription_ends_at',
    'stripe_customer_id',
    'stripe_subscription_id',
    'stripe_price_id',
    'coupon_code',
    'bandwidth_used_bytes',
    'suspended_at',
    'storage_disk_id',
])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes;

    public function approvalEnabled(): bool
    {
        return (bool) ($this->settings['approval_enabled'] ?? true);
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * @return HasMany<MeetingRoom, $this>
     */
    public function meetingRooms(): HasMany
    {
        return $this->hasMany(MeetingRoom::class);
    }

    /**
     * External calendar providers such as Microsoft 365.
     *
     * @return HasMany<CalendarConnection, $this>
     */
    public function calendarConnections(): HasMany
    {
        return $this->hasMany(CalendarConnection::class);
    }

    /**
     * Storage backend this organization's uploads are written to.
     *
     * @return BelongsTo<StorageDisk, $this>
     */
    public function storageDisk(): BelongsTo
    {
        return $this->belongsTo(StorageDisk::class);
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * @return HasMany<Screen, $this>
     */
    public function screens(): HasMany
    {
        return $this->hasMany(Screen::class);
    }

    /**
     * @return HasMany<ScreenGroup, $this>
     */
    public function screenGroups(): HasMany
    {
        return $this->hasMany(ScreenGroup::class);
    }

    /**
     * @return HasMany<MediaFolder, $this>
     */
    public function mediaFolders(): HasMany
    {
        return $this->hasMany(MediaFolder::class);
    }

    /**
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * @return HasMany<Design, $this>
     */
    public function designs(): HasMany
    {
        return $this->hasMany(Design::class);
    }

    /**
     * @return HasMany<Template, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    /**
     * @return HasMany<Playlist, $this>
     */
    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    /**
     * @return HasMany<Channel, $this>
     */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    /**
     * @return HasMany<Schedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * @return HasMany<Emergency, $this>
     */
    public function emergencies(): HasMany
    {
        return $this->hasMany(Emergency::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<ApiToken, $this>
     */
    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    /**
     * @return HasMany<WebhookEndpoint, $this>
     */
    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }

    /**
     * @return HasOne<QueueSetting, $this>
     */
    public function queueSetting(): HasOne
    {
        return $this->hasOne(QueueSetting::class);
    }

    /**
     * @return HasMany<QueueService, $this>
     */
    public function queueServices(): HasMany
    {
        return $this->hasMany(QueueService::class);
    }

    /**
     * @return HasMany<QueuePriority, $this>
     */
    public function queuePriorities(): HasMany
    {
        return $this->hasMany(QueuePriority::class);
    }

    /**
     * @return HasMany<QueueTicket, $this>
     */
    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    /**
     * @return HasMany<QueueCounter, $this>
     */
    public function queueCounters(): HasMany
    {
        return $this->hasMany(QueueCounter::class);
    }

    /**
     * @return HasMany<QueueKiosk, $this>
     */
    public function queueKiosks(): HasMany
    {
        return $this->hasMany(QueueKiosk::class);
    }

    /**
     * @return HasMany<InAppNotification, $this>
     */
    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(InAppNotification::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'settings' => 'array',
            'plan_key' => PlanKey::class,
            'subscription_status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'bandwidth_used_bytes' => 'integer',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
