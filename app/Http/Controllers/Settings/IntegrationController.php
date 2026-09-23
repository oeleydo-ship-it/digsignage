<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\ApiScope;
use App\Enums\AuditAction;
use App\Enums\PlanFeature;
use App\Enums\WebhookEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\SaveApiTokenRequest;
use App\Http\Requests\Partner\SaveWebhookEndpointRequest;
use App\Models\ApiToken;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\TeamQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function edit(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        Gate::authorize('update', $team);

        return Inertia::render('settings/api', [
            'tokens' => $team->apiTokens()->with('user:id,name')->latest()->get()->map(fn (ApiToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'token_prefix' => $token->token_prefix,
                'scopes' => $token->scopes,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
                'created_by' => $token->user->name,
            ]),
            'endpoints' => $team->webhookEndpoints()->latest()->get()->map(fn (WebhookEndpoint $endpoint) => [
                'id' => $endpoint->id,
                'url' => $endpoint->url,
                'events' => $endpoint->events,
                'is_active' => $endpoint->is_active,
                'last_delivery_at' => $endpoint->last_delivery_at?->toIso8601String(),
                'created_at' => $endpoint->created_at?->toIso8601String(),
            ]),
            'deliveries' => WebhookDelivery::query()
                ->forTeam($team)
                ->with('endpoint:id,url')
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (WebhookDelivery $delivery) => [
                    'id' => $delivery->id,
                    'uuid' => $delivery->uuid,
                    'event' => $delivery->event->value,
                    'status' => $delivery->status->value,
                    'attempts' => $delivery->attempts,
                    'response_code' => $delivery->response_code,
                    'url' => $delivery->endpoint->url,
                    'created_at' => $delivery->created_at?->toIso8601String(),
                ]),
            'scopes' => ApiScope::options(),
            'events' => WebhookEvent::options(),
            'openapi_url' => url('/api/v1/openapi.json'),
            'plain_token' => $request->session()->get('plain_token'),
            'plain_secret' => $request->session()->get('plain_secret'),
        ]);
    }

    public function storeToken(SaveApiTokenRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        Gate::authorize('update', $team);

        $scopes = $request->validated('scopes');
        $scopeList = [];

        if (is_array($scopes)) {
            foreach ($scopes as $scope) {
                if (is_string($scope)) {
                    $scopeList[] = $scope;
                }
            }
        }

        app(TeamQuota::class)->assertCanUseFeature($team, PlanFeature::PartnerApi);

        [, $plain] = ApiToken::issue(
            $request->user(),
            $team,
            $request->string('name')->toString(),
            $scopeList,
        );

        app(RecordOrganizationAudit::class)->handle(
            $team,
            AuditAction::ApiTokenCreated,
            $request->user(),
            'api_token',
            null,
            null,
            ['name' => $request->string('name')->toString()],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API token created. Copy it now; it will not be shown again.')]);

        return back()->with('plain_token', $plain);
    }

    public function destroyToken(Request $request, ApiToken $apiToken): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null && $apiToken->team_id === $team->id, 403);
        Gate::authorize('update', $team);

        $name = $apiToken->name;
        $apiToken->delete();

        app(RecordOrganizationAudit::class)->handle(
            $team,
            AuditAction::ApiTokenRevoked,
            $request->user(),
            'api_token',
            null,
            ['name' => $name],
            null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API token revoked.')]);

        return back();
    }

    public function rotateToken(Request $request, ApiToken $apiToken): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null && $apiToken->team_id === $team->id, 403);
        Gate::authorize('update', $team);

        [, $plain] = $apiToken->rotate();

        app(RecordOrganizationAudit::class)->handle(
            $team,
            AuditAction::ApiTokenRotated,
            $request->user(),
            'api_token',
            $apiToken->id,
            null,
            ['name' => $apiToken->name],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API token rotated. Copy the new token now.')]);

        return back()->with('plain_token', $plain);
    }

    public function storeWebhook(SaveWebhookEndpointRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        Gate::authorize('update', $team);

        app(TeamQuota::class)->assertCanUseFeature($team, PlanFeature::Webhooks);

        $secret = Str::random(40);

        $endpoint = $team->webhookEndpoints()->create([
            'user_id' => $request->user()->id,
            'url' => $request->validated('url'),
            'secret' => $secret,
            'events' => $request->validated('events'),
            'is_active' => true,
        ]);

        app(RecordOrganizationAudit::class)->handle(
            $team,
            AuditAction::WebhookCreated,
            $request->user(),
            'webhook_endpoint',
            $endpoint->id,
            null,
            ['url' => $endpoint->url],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook endpoint created. Copy the signing secret now.')]);

        return back()->with('plain_secret', $secret);
    }

    public function updateWebhook(SaveWebhookEndpointRequest $request, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null && $webhookEndpoint->team_id === $team->id, 403);
        Gate::authorize('update', $team);

        $before = ['url' => $webhookEndpoint->url, 'is_active' => $webhookEndpoint->is_active];

        $webhookEndpoint->forceFill([
            'url' => $request->validated('url'),
            'events' => $request->validated('events'),
            'is_active' => $request->boolean('is_active', $webhookEndpoint->is_active),
        ])->save();

        app(RecordOrganizationAudit::class)->handle(
            $team,
            AuditAction::WebhookUpdated,
            $request->user(),
            'webhook_endpoint',
            $webhookEndpoint->id,
            $before,
            ['url' => $webhookEndpoint->url, 'is_active' => $webhookEndpoint->is_active],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook endpoint updated.')]);

        return back();
    }

    public function destroyWebhook(Request $request, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null && $webhookEndpoint->team_id === $team->id, 403);
        Gate::authorize('update', $team);

        $url = $webhookEndpoint->url;
        $id = $webhookEndpoint->id;
        $webhookEndpoint->delete();

        app(RecordOrganizationAudit::class)->handle(
            $team,
            AuditAction::WebhookDeleted,
            $request->user(),
            'webhook_endpoint',
            $id,
            ['url' => $url],
            null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook endpoint removed.')]);

        return back();
    }

    public function rotateWebhook(Request $request, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null && $webhookEndpoint->team_id === $team->id, 403);
        Gate::authorize('update', $team);

        $secret = Str::random(40);
        $webhookEndpoint->forceFill(['secret' => $secret])->save();

        app(RecordOrganizationAudit::class)->handle(
            $team,
            AuditAction::WebhookSecretRotated,
            $request->user(),
            'webhook_endpoint',
            $webhookEndpoint->id,
            null,
            ['url' => $webhookEndpoint->url],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook signing secret rotated. Copy it now.')]);

        return back()->with('plain_secret', $secret);
    }
}
