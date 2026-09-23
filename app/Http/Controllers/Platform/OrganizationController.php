<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\RecordPlatformAudit;
use App\Enums\PlanKey;
use App\Enums\PlatformAuditAction;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateOrganizationRequest;
use App\Models\Media;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $organizations = Team::query()
            ->withCount(['screens', 'memberships'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Team $team) => $this->summary($team));

        return Inertia::render('platform/organizations/index', [
            'filters' => ['search' => $search],
            'organizations' => $organizations,
        ]);
    }

    public function show(Team $team): Response
    {
        $team->loadCount(['screens', 'memberships', 'media']);

        return Inertia::render('platform/organizations/show', [
            'organization' => [
                ...$this->summary($team),
                'storage_bytes' => (int) Media::query()->forTeam($team)->sum('file_size'),
                'bandwidth_used_bytes' => (int) $team->bandwidth_used_bytes,
                'members' => $team->members()->orderBy('name')->get()->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                ]),
            ],
            'plans' => collect(PlanKey::cases())->map(fn (PlanKey $plan) => [
                'value' => $plan->value,
                'label' => $plan->label(),
            ]),
            'statuses' => collect(SubscriptionStatus::cases())->map(fn (SubscriptionStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Team $team, RecordPlatformAudit $audit): RedirectResponse
    {
        $before = [
            'plan_key' => $team->plan_key->value,
            'subscription_status' => $team->subscription_status->value,
        ];

        $team->forceFill([
            'plan_key' => PlanKey::from($request->validated('plan_key')),
            'subscription_status' => SubscriptionStatus::from($request->validated('subscription_status')),
        ])->save();

        $audit->handle(
            PlatformAuditAction::OrganizationUpdated,
            $request->user(),
            'team',
            $team->id,
            $before,
            [
                'plan_key' => $team->plan_key->value,
                'subscription_status' => $team->subscription_status->value,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization updated.')]);

        return back();
    }

    public function suspend(Request $request, Team $team, RecordPlatformAudit $audit): RedirectResponse
    {
        $team->forceFill(['suspended_at' => now()])->save();

        $audit->handle(
            PlatformAuditAction::OrganizationSuspended,
            $request->user(),
            'team',
            $team->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization suspended.')]);

        return back();
    }

    public function restore(Request $request, Team $team, RecordPlatformAudit $audit): RedirectResponse
    {
        $team->forceFill(['suspended_at' => null])->save();

        $audit->handle(
            PlatformAuditAction::OrganizationRestored,
            $request->user(),
            'team',
            $team->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization restored.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(Team $team): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'plan_key' => $team->plan_key->value,
            'plan_name' => $team->plan_key->label(),
            'subscription_status' => $team->subscription_status->value,
            'status_label' => $team->subscription_status->label(),
            'suspended_at' => $team->suspended_at?->toIso8601String(),
            'screens_count' => $team->screens_count ?? $team->screens()->count(),
            'members_count' => $team->memberships_count ?? $team->memberships()->count(),
            'created_at' => $team->created_at?->toIso8601String(),
        ];
    }
}
