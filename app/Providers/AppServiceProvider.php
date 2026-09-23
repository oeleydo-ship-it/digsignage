<?php

namespace App\Providers;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Billing\BillingGateway;
use App\Billing\FakeBillingGateway;
use App\Billing\StripeBillingGateway;
use App\Enums\AuditAction;
use App\Enums\TeamPermission;
use App\Models\ApiToken;
use App\Models\Channel;
use App\Models\Design;
use App\Models\Playlist;
use App\Models\QueueKiosk;
use App\Models\Screen;
use App\Models\Template;
use App\Models\User;
use App\Support\ContentWorkflow;
use App\Support\StayOnPageRedirector;
use App\Support\StorageDisks;
use App\Widgets\WidgetRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Artisan's reloading server filters environment variables. PHP needs
        // these to find a writable upload directory, especially on Windows.
        ServeCommand::$passthroughVariables = array_values(array_unique([
            ...ServeCommand::$passthroughVariables,
            'TEMP', 'TMP', 'TMPDIR',
        ]));
        $this->app->singleton(WidgetRegistry::class);
        $this->app->singleton(StorageDisks::class);
        $this->app->singleton(BillingGateway::class, function ($app) {
            return config('billing.driver') === 'stripe'
                ? $app->make(StripeBillingGateway::class)
                : $app->make(FakeBillingGateway::class);
        });

        $this->app->extend('redirect', function ($unused, $app): StayOnPageRedirector {
            $stay = new StayOnPageRedirector($app['url']);

            if (isset($app['session.store'])) {
                $stay->setSession($app['session.store']);
            }

            return $stay;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureContentApprovals();
        $this->configureOrganizationAudits();
    }

    /**
     * Configure rate limiters for player pairing.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('player-registration-start', function (Request $request) {
            return Limit::perMinute((int) config('signage.registration.start_per_minute'))
                ->by((string) $request->ip());
        });

        RateLimiter::for('player-registration-status', function (Request $request) {
            return Limit::perMinute((int) config('signage.registration.status_per_minute'))
                ->by((string) $request->ip());
        });

        RateLimiter::for('player-heartbeat', function (Request $request) {
            return Limit::perMinute((int) config('signage.player.heartbeat_per_minute'))
                ->by($this->playerRateLimitKey($request));
        });

        RateLimiter::for('player-telemetry', function (Request $request) {
            return Limit::perMinute((int) config('signage.player.telemetry_per_minute'))
                ->by($this->playerRateLimitKey($request));
        });

        RateLimiter::for('player-playback', function (Request $request) {
            return Limit::perMinute((int) config('signage.player.playback_per_minute'))
                ->by($this->playerRateLimitKey($request));
        });

        RateLimiter::for('partner-api', function (Request $request) {
            $token = $request->attributes->get('apiToken');

            return Limit::perMinute((int) config('partner.rate_per_minute'))
                ->by($token instanceof ApiToken ? 'token:'.$token->id : (string) $request->ip());
        });

        RateLimiter::for('queue-kiosk-issue', function (Request $request) {
            $kiosk = $request->route('queueKiosk');
            $kioskKey = $kiosk instanceof QueueKiosk ? (string) $kiosk->id : 'unknown';

            return Limit::perMinute(20)->by($request->ip().'|'.$kioskKey);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Prefer the authenticated screen so reconnect bursts are not IP-wide.
     */
    protected function playerRateLimitKey(Request $request): string
    {
        $screen = $request->attributes->get('playerScreen');

        if ($screen instanceof Screen) {
            return 'screen:'.$screen->id;
        }

        return (string) $request->ip();
    }

    /**
     * Morph map and gates for the content approval workflow.
     */
    protected function configureContentApprovals(): void
    {
        Relation::enforceMorphMap([
            'playlist' => Playlist::class,
            'design' => Design::class,
            'template' => Template::class,
            'channel' => Channel::class,
        ]);

        Gate::define('view-approvals', function (User $user): bool {
            $team = $user->currentTeam;

            return $team !== null && (
                $user->hasTeamPermission($team, TeamPermission::ViewPlaylists)
                || $user->hasTeamPermission($team, TeamPermission::ViewDesigns)
                || $user->hasTeamPermission($team, TeamPermission::ViewTemplates)
                || $user->hasTeamPermission($team, TeamPermission::ViewChannels)
            );
        });

        Gate::define('submit-content', function (User $user, Model $content): bool {
            $team = $user->currentTeam;

            return $team !== null
                && $team->id === $content->getAttribute('team_id')
                && $user->hasTeamPermission($team, TeamPermission::SubmitContent)
                && $user->can('update', $content)
                && in_array(ContentWorkflow::statusValue($content), ['draft', 'rejected'], true);
        });

        Gate::define('approve-content', function (User $user, Model $content): bool {
            $team = $user->currentTeam;

            return $team !== null
                && $team->id === $content->getAttribute('team_id')
                && $user->hasTeamPermission($team, TeamPermission::ApproveContent)
                && ContentWorkflow::statusValue($content) === 'pending_approval';
        });

        Gate::define('publish-content', function (User $user, Model $content): bool {
            $team = $user->currentTeam;
            $status = ContentWorkflow::statusValue($content);
            $publishable = in_array($status, ContentWorkflow::publishFromStatuses($content), true);

            return $team !== null
                && $team->id === $content->getAttribute('team_id')
                && $user->hasTeamPermission($team, TeamPermission::PublishContent)
                && $publishable;
        });

        Gate::define('archive-content', function (User $user, Model $content): bool {
            $team = $user->currentTeam;

            return $team !== null
                && $team->id === $content->getAttribute('team_id')
                && $user->hasTeamPermission($team, TeamPermission::ArchiveContent)
                && ContentWorkflow::statusValue($content) !== 'pending_approval';
        });
    }

    /**
     * Record successful logins against the user's current organization.
     */
    protected function configureOrganizationAudits(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            $user = $event->user;

            if (! $user instanceof User) {
                return;
            }

            $team = $user->currentTeam;

            if ($team === null) {
                return;
            }

            app(RecordOrganizationAudit::class)->handle(
                $team,
                AuditAction::UserLoggedIn,
                $user,
                'user',
                $user->id,
            );
        });

        Event::listen(Failed::class, function (Failed $event): void {
            $email = $event->credentials['email'] ?? null;
            $user = $event->user instanceof User
                ? $event->user
                : (is_string($email) ? User::query()->where('email', $email)->first() : null);

            if (! $user instanceof User) {
                return;
            }

            $team = $user->currentTeam;

            if ($team === null) {
                return;
            }

            app(RecordOrganizationAudit::class)->handle(
                $team,
                AuditAction::UserLoginFailed,
                $user,
                'user',
                $user->id,
            );
        });
    }
}
