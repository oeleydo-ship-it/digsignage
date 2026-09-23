<?php

namespace App\Support;

use App\Enums\TeamPermission;
use App\Models\ContentApprovalEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class ContentApprovalPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function for(User $user, Model $content): array
    {
        $team = $user->currentTeam;
        $status = ContentWorkflow::statusValue($content);
        $canUpdate = $user->can('update', $content);
        $canSubmit = $team !== null && $user->hasTeamPermission($team, TeamPermission::SubmitContent);
        $canApprove = $team !== null && $user->hasTeamPermission($team, TeamPermission::ApproveContent);
        $canPublish = $team !== null && $user->hasTeamPermission($team, TeamPermission::PublishContent);
        $canArchive = $team !== null && $user->hasTeamPermission($team, TeamPermission::ArchiveContent);

        $type = $content->getMorphClass();

        return [
            'type' => $type,
            'id' => $content->getKey(),
            'status' => $status,
            'locked' => ContentWorkflow::isPending($content),
            'can_submit' => $canSubmit && $canUpdate && in_array($status, ['draft', 'rejected'], true),
            'can_approve' => $canApprove && $status === 'pending_approval',
            'can_reject' => $canApprove && $status === 'pending_approval',
            'can_publish' => $canPublish && in_array($status, ContentWorkflow::publishFromStatuses($content), true),
            'can_archive' => $canArchive && in_array($status, ['draft', 'approved', 'published', 'rejected', 'scheduled'], true),
            'history' => ContentApprovalEvent::query()
                ->where('approvable_type', $type)
                ->where('approvable_id', $content->getKey())
                ->with('user:id,name')
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (ContentApprovalEvent $event) => [
                    'id' => $event->id,
                    'action' => $event->action->value,
                    'action_label' => $event->action->label(),
                    'from_status' => $event->from_status,
                    'to_status' => $event->to_status,
                    'revision' => $event->revision,
                    'comment' => $event->comment,
                    'user_name' => $event->user?->name,
                    'created_at' => $event->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }
}
