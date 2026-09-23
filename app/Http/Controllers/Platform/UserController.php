<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\RecordPlatformAudit;
use App\Enums\PlatformAuditAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $users = User::query()
            ->with('currentTeam:id,name,slug')
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_platform_admin' => $user->is_platform_admin,
                'team' => $user->currentTeam ? [
                    'name' => $user->currentTeam->name,
                    'slug' => $user->currentTeam->slug,
                ] : null,
                'can_impersonate' => $user->is_platform_admin !== true && $user->id !== $request->user()->id,
            ]);

        return Inertia::render('platform/users/index', [
            'filters' => ['search' => $search],
            'users' => $users,
        ]);
    }

    public function update(Request $request, User $user, RecordPlatformAudit $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null && $actor->is_platform_admin === true, 403);
        abort_if($user->is($actor) && ! $request->boolean('is_platform_admin'), 422, __('You cannot remove your own platform access.'));

        $before = ['is_platform_admin' => $user->is_platform_admin];
        $user->forceFill(['is_platform_admin' => $request->boolean('is_platform_admin')])->save();

        $audit->handle(
            PlatformAuditAction::PlatformAdminUpdated,
            $actor,
            'user',
            $user->id,
            $before,
            ['is_platform_admin' => $user->is_platform_admin, 'email' => $user->email],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User platform access updated.')]);

        return back();
    }

    public function impersonate(Request $request, User $user, RecordPlatformAudit $audit): RedirectResponse
    {
        $actor = $request->user();

        abort_if($user->is($actor), 403, __('You cannot impersonate yourself.'));
        abort_if($user->is_platform_admin === true, 403, __('Platform administrators cannot be impersonated.'));
        abort_unless($actor->is_platform_admin === true, 403);

        $audit->handle(
            PlatformAuditAction::ImpersonationStarted,
            $actor,
            'user',
            $user->id,
            null,
            ['email' => $user->email, 'name' => $user->name],
        );

        $request->session()->put(Impersonation::SESSION_KEY, $actor->id);
        Auth::login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Now viewing the platform as :name.', ['name' => $user->name])]);

        if ($user->currentTeam) {
            return to_route('dashboard', $user->currentTeam);
        }

        return to_route('teams.index');
    }
}
