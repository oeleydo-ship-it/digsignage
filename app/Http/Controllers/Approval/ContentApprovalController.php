<?php

namespace App\Http\Controllers\Approval;

use App\Actions\Approval\TransitionContentApproval;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ContentApprovalEvent;
use App\Models\Design;
use App\Models\Playlist;
use App\Models\Template;
use App\Support\ContentWorkflow;
use App\Support\ResolveApprovable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContentApprovalController extends Controller
{
    /**
     * Queue of items waiting for review in the current organization.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('view-approvals');

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $pending = ContentApprovalEvent::query()
            ->forTeam($team)
            ->where('action', 'submitted')
            ->whereIn('id', function ($query) use ($team) {
                $query->selectRaw('max(id)')
                    ->from('content_approval_events')
                    ->where('team_id', $team->id)
                    ->groupBy('approvable_type', 'approvable_id');
            })
            ->with('approvable')
            ->with('user:id,name')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (ContentApprovalEvent $event) use ($team) {
                $content = $event->approvable;

                if ($content === null || ContentWorkflow::statusValue($content) !== 'pending_approval') {
                    return null;
                }

                return [
                    'id' => $event->id,
                    'type' => $event->approvable_type,
                    'type_label' => ContentWorkflow::typeLabel($content),
                    'content_id' => $event->approvable_id,
                    'title' => ContentWorkflow::title($content),
                    'revision' => $event->revision,
                    'submitted_by' => $event->user?->name,
                    'comment' => $event->comment,
                    'submitted_at' => $event->created_at?->toIso8601String(),
                    'edit_url' => $this->editUrl($team->slug, $event->approvable_type, $event->approvable_id),
                ];
            })
            ->filter()
            ->values();

        return Inertia::render('approvals/index', [
            'pending' => $pending,
            'approvalEnabled' => $team->approvalEnabled(),
        ]);
    }

    public function submit(Request $request, string $current_team, string $type, int $id, TransitionContentApproval $transition): RedirectResponse
    {
        $content = $this->content($request, $current_team, $type, $id);
        Gate::authorize('submit-content', $content);

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $transition->submit($request->user(), $content, $validated['comment'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Submitted for approval.')]);

        return redirect()->to($this->editorUrlFor($request, $type, $id));
    }

    public function approve(Request $request, string $current_team, string $type, int $id, TransitionContentApproval $transition): RedirectResponse
    {
        $content = $this->content($request, $current_team, $type, $id);
        Gate::authorize('approve-content', $content);

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $transition->approve($request->user(), $content, $validated['comment'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Content approved.')]);

        return back(fallback: $this->editorUrlFor($request, $type, $id));
    }

    public function reject(Request $request, string $current_team, string $type, int $id, TransitionContentApproval $transition): RedirectResponse
    {
        $content = $this->content($request, $current_team, $type, $id);
        Gate::authorize('approve-content', $content);

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $transition->reject($request->user(), $content, $validated['comment']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Content rejected.')]);

        return back(fallback: $this->editorUrlFor($request, $type, $id));
    }

    public function publish(Request $request, string $current_team, string $type, int $id, TransitionContentApproval $transition): RedirectResponse
    {
        $content = $this->content($request, $current_team, $type, $id);
        Gate::authorize('publish-content', $content);

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $transition->publish($request->user(), $content, $validated['comment'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Published.')]);

        return back(fallback: $this->editorUrlFor($request, $type, $id));
    }

    public function archive(Request $request, string $current_team, string $type, int $id, TransitionContentApproval $transition): RedirectResponse
    {
        $content = $this->content($request, $current_team, $type, $id);
        Gate::authorize('archive-content', $content);

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $transition->archive($request->user(), $content, $validated['comment'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Content archived.')]);

        return back(fallback: $this->editorUrlFor($request, $type, $id));
    }

    protected function content(Request $request, string $current_team, string $type, int $id): Playlist|Design|Template|Channel
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null && $team->slug === $current_team, 404);

        $content = ResolveApprovable::handle($team, $type, $id);
        abort_unless($content instanceof Playlist || $content instanceof Design || $content instanceof Template || $content instanceof Channel, 404);

        return $content;
    }

    protected function editUrl(string $slug, string $type, int $id): string
    {
        return match ($type) {
            'playlist' => "/{$slug}/playlists/{$id}/edit",
            'design' => "/{$slug}/designs/{$id}/edit",
            'template' => "/{$slug}/templates/{$id}/edit",
            'channel' => "/{$slug}/channels/{$id}/edit",
            default => "/{$slug}/approvals",
        };
    }

    protected function editorUrlFor(Request $request, string $type, int $id): string
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        return match ($type) {
            'playlist' => route('playlists.edit', [$team, $id]),
            'design' => route('designs.edit', [$team, $id]),
            'template' => route('templates.edit', [$team, $id]),
            'channel' => route('channels.edit', [$team, $id]),
            default => route('approvals.index', $team),
        };
    }
}
