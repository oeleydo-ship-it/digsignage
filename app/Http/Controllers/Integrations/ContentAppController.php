<?php

namespace App\Http\Controllers\Integrations;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Actions\Integrations\SaveContentIntegrations;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\SaveContentAppsRequest;
use App\Support\ContentApps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContentAppController extends Controller
{
    /**
     * Workspace content apps that supply defaults to widgets.
     */
    public function edit(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        Gate::authorize('view', $team);

        return Inertia::render('settings/apps', [
            'apps' => ContentApps::forPage($team),
            'canManage' => $request->user()->can('update', $team),
        ]);
    }

    /**
     * Save enabled content apps and their widget defaults.
     */
    public function update(SaveContentAppsRequest $request, SaveContentIntegrations $save): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        Gate::authorize('update', $team);

        $before = ContentApps::stored($team);
        $apps = $request->validated('apps');
        $save->handle($team, is_array($apps) ? $apps : []);
        $team->refresh();

        app(RecordOrganizationAudit::class)->handle(
            $team,
            AuditAction::IntegrationsUpdated,
            $request->user(),
            'integrations',
            $team->id,
            $this->auditSnapshot($before),
            $this->auditSnapshot(ContentApps::stored($team)),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Apps saved.')]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    protected function auditSnapshot(array $stored): array
    {
        $snapshot = [];

        foreach ($stored as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            unset($row['token']);
            $row['has_token'] = filled($stored[$key]['token'] ?? null);
            $snapshot[$key] = $row;
        }

        return $snapshot;
    }
}
