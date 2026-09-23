<?php

namespace App\Http\Controllers\Queue;

use App\Actions\Queue\DeleteQueuePriority;
use App\Actions\Queue\SaveQueuePriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queue\SaveQueuePriorityRequest;
use App\Models\QueuePriority;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class QueuePriorityController extends Controller
{
    /**
     * Store a newly created priority level.
     */
    public function store(SaveQueuePriorityRequest $request, SaveQueuePriority $saveQueuePriority): RedirectResponse
    {
        Gate::authorize('create', QueuePriority::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $saveQueuePriority->handle($team, $request->validated(), actor: $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Queue priority created.')]);

        return back(fallback: route('queue.settings', $team));
    }

    /**
     * Update the specified priority level.
     */
    public function update(
        SaveQueuePriorityRequest $request,
        string $current_team,
        QueuePriority $queuePriority,
        SaveQueuePriority $saveQueuePriority,
    ): RedirectResponse {
        Gate::authorize('update', $queuePriority);
        abort_unless($queuePriority->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $saveQueuePriority->handle(
            $request->user()->currentTeam,
            $request->validated(),
            $queuePriority,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Queue priority updated.')]);

        return back(fallback: route('queue.settings', $request->user()->currentTeam));
    }

    /**
     * Remove the specified priority level.
     */
    public function destroy(
        Request $request,
        string $current_team,
        QueuePriority $queuePriority,
        DeleteQueuePriority $deleteQueuePriority,
    ): RedirectResponse {
        Gate::authorize('delete', $queuePriority);
        abort_unless($queuePriority->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $deleteQueuePriority->handle($queuePriority);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Queue priority deleted.')]);

        return back(fallback: route('queue.settings', $request->user()->currentTeam));
    }
}
