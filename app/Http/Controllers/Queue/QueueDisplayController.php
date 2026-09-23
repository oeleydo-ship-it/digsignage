<?php

namespace App\Http\Controllers\Queue;

use App\Actions\Queue\CreateQueueBoard;
use App\Actions\Queue\DeployQueueBoard;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queue\CreateQueueBoardRequest;
use App\Http\Requests\Queue\DeployQueueBoardRequest;
use App\Models\Channel;
use App\Models\Design;
use App\Models\Playlist;
use App\Models\Screen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class QueueDisplayController extends Controller
{
    public function store(CreateQueueBoardRequest $request, CreateQueueBoard $createQueueBoard): RedirectResponse
    {
        $design = $createQueueBoard->handle(
            $request->user(),
            $request->user()->currentTeam,
            $request->validated(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Queue board created. Customize and publish it before deployment.'),
        ]);

        return redirect()->route('designs.edit', [$request->user()->currentTeam, $design]);
    }

    public function deploy(DeployQueueBoardRequest $request, DeployQueueBoard $deployQueueBoard): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;
        $design = Design::query()->forTeam($team)->published()->findOrFail($request->integer('design_id'));
        $screens = Screen::query()->forTeam($team)->whereKey($request->validated('screen_ids'))->get();

        abort_unless($user->hasTeamPermission($team, TeamPermission::ManageQueue), 403);
        Gate::authorize('create', Playlist::class);
        Gate::authorize('create', Channel::class);

        if (! $user->hasTeamPermission($team, TeamPermission::PublishContent)) {
            abort(403);
        }

        foreach ($screens as $screen) {
            Gate::authorize('update', $screen);
        }

        if (! self::isQueueBoard($design)) {
            throw ValidationException::withMessages([
                'design_id' => __('Choose a design that contains at least one queue widget.'),
            ]);
        }

        $channel = $deployQueueBoard->handle(
            $user,
            $team,
            $design,
            $screens->modelKeys(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Queue board deployed to :count screen(s).', ['count' => $screens->count()]),
        ]);

        return redirect()->route('queue.displays', $team)->with('deployed_channel_id', $channel->id);
    }

    public static function isQueueBoard(Design $design): bool
    {
        foreach ($design->normalizedDocument()['elements'] as $element) {
            if (str_starts_with((string) ($element['type'] ?? ''), 'queue_')) {
                return true;
            }
        }

        return false;
    }
}
