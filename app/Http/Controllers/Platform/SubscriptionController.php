<?php

namespace App\Http\Controllers\Platform;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        $subscriptions = Team::query()
            ->withCount('screens')
            ->when($status !== '', fn ($query) => $query->where('subscription_status', $status))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'plan_key' => $team->plan_key->value,
                'plan_name' => $team->plan_key->label(),
                'subscription_status' => $team->subscription_status->value,
                'status_label' => $team->subscription_status->label(),
                'trial_ends_at' => $team->trial_ends_at?->toIso8601String(),
                'suspended_at' => $team->suspended_at?->toIso8601String(),
                'screens_count' => $team->screens_count,
            ]);

        return Inertia::render('platform/subscriptions/index', [
            'filters' => ['status' => $status],
            'statuses' => collect(SubscriptionStatus::cases())->map(fn (SubscriptionStatus $item) => [
                'value' => $item->value,
                'label' => $item->label(),
            ]),
            'subscriptions' => $subscriptions,
        ]);
    }
}
