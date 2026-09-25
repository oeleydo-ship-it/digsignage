<?php

namespace App\Http\Middleware;

use App\Enums\PlanFeature;
use App\Enums\TeamRole;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\InAppNotification;
use App\Support\Impersonation;
use App\Support\OpaqueProp;
use App\Support\PlatformSettings;
use App\Support\TeamQuota;
use App\Widgets\WidgetRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $impersonator = $user ? Impersonation::actor($request) : null;

        // Several permission props need the same membership role; look it up
        // once per request instead of once per prop.
        $resolvedRole = null;
        $roleResolved = false;
        $teamRole = function () use ($user, &$resolvedRole, &$roleResolved): ?TeamRole {
            if (! $roleResolved) {
                $resolvedRole = $user?->currentTeam ? $user->teamRole($user->currentTeam) : null;
                $roleResolved = true;
            }

            return $resolvedRole;
        };

        $settings = app(PlatformSettings::class);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'branding' => fn () => [
                'logo_url' => $settings->logoUrl(),
                'favicon_url' => $settings->faviconUrl(),
                'logo_tone' => $settings->logoTone(),
                'show_name' => $settings->showName(),
                'support_email' => $settings->get('general.support_email'),
                'terms_url' => $settings->get('general.terms_url'),
                'privacy_url' => $settings->get('general.privacy_url'),
            ],
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'isPlatformAdmin' => $user?->is_platform_admin === true && $impersonator === null,
            'impersonation' => $impersonator ? [
                'actor_name' => $impersonator->name,
                'actor_email' => $impersonator->email,
                'target_name' => $user->name,
            ] : null,
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
            'signage' => fn () => $user?->currentTeam
                ? $user->toSignagePermissions($user->currentTeam)
                : null,
            'mediaPermissions' => fn () => $user?->currentTeam
                ? $user->toMediaPermissions($user->currentTeam)
                : null,
            'designPermissions' => fn () => $user?->currentTeam
                ? $user->toDesignPermissions($user->currentTeam)
                : null,
            'templatePermissions' => fn () => $user?->currentTeam
                ? $user->toTemplatePermissions($user->currentTeam)
                : null,
            'playlistPermissions' => fn () => $user?->currentTeam
                ? $user->toPlaylistPermissions($user->currentTeam)
                : null,
            'channelPermissions' => fn () => $user?->currentTeam
                ? $user->toChannelPermissions($user->currentTeam)
                : null,
            'schedulePermissions' => fn () => $user?->currentTeam
                ? $user->toSchedulePermissions($user->currentTeam)
                : null,
            'emergencyPermissions' => fn () => $user?->currentTeam
                ? $user->toEmergencyPermissions($user->currentTeam)
                : null,
            'queuePermissions' => fn () => $user?->currentTeam
                && app(TeamQuota::class)->allowsFeature($user->currentTeam, PlanFeature::QueueManagement)
                ? $user->toQueuePermissions($user->currentTeam, $teamRole())
                : null,
            'bookingPermissions' => fn () => $user?->currentTeam
                && app(TeamQuota::class)->allowsFeature($user->currentTeam, PlanFeature::RoomBooking)
                ? $user->toBookingPermissions($user->currentTeam, $teamRole())
                : null,
            'billingPermissions' => fn () => $user?->currentTeam
                ? $user->toBillingPermissions($user->currentTeam, $teamRole())
                : null,
            'canViewAuditLogs' => fn () => $user?->currentTeam
                ? $user->can('viewAny', AuditLog::class)
                : false,
            'unreadNotifications' => function () use ($user) {
                $team = $user?->currentTeam;

                if ($team === null) {
                    return 0;
                }

                // Short TTL plus event-based invalidation keeps the badge
                // fresh without a count query on every Inertia response.
                return Cache::remember(
                    'notifications:unread:'.$team->id.':'.$user->id,
                    30,
                    fn () => InAppNotification::query()
                        ->forTeam($team)
                        ->where('user_id', $user->id)
                        ->whereNull('read_at')
                        ->count(),
                );
            },
            'recentNotifications' => function () use ($user) {
                $team = $user?->currentTeam;

                if ($team === null) {
                    return [];
                }

                return InAppNotification::query()
                    ->forTeam($team)
                    ->where('user_id', $user->id)
                    ->latest('id')
                    ->limit(8)
                    ->get()
                    ->map(fn (InAppNotification $item) => $item->toInboxItem($team->slug))
                    ->values()
                    ->all();
            },
            'widgets' => fn () => OpaqueProp::from(app(WidgetRegistry::class)->toArrayForTeam(
                $user?->currentTeam,
                // Room pickers are only needed where widget settings are edited.
                withRoomOptions: $request->routeIs('designs.edit', 'templates.edit', 'playlists.edit'),
            )),
            'announcements' => fn () => $user
                ? Cache::remember('platform:announcements:published', 300, fn () => Announcement::query()
                    ->published()
                    ->orderByDesc('published_at')
                    ->limit(5)
                    ->get(['id', 'title', 'body', 'published_at'])
                    ->map(fn (Announcement $item) => [
                        'id' => $item->id,
                        'title' => $item->title,
                        'body' => $item->body,
                        'published_at' => $item->published_at?->toIso8601String(),
                    ])
                    ->all())
                : [],
        ];
    }
}
