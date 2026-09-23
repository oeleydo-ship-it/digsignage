<?php

namespace App\Actions\Approval;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Actions\Notifications\DispatchSignageAlert;
use App\Actions\Partner\DispatchPartnerWebhook;
use App\Enums\AuditAction;
use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\ContentApprovalAction;
use App\Enums\DesignStatus;
use App\Enums\PlaylistStatus;
use App\Enums\SignageAlert;
use App\Enums\TeamRole;
use App\Enums\TemplateStatus;
use App\Enums\WebhookEvent;
use App\Models\Channel;
use App\Models\ContentApprovalEvent;
use App\Models\Design;
use App\Models\Playlist;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;
use App\Support\ContentWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionContentApproval
{
    public function __construct(protected DispatchSignageAlert $alerts) {}

    public function submit(User $user, Model $content, ?string $comment = null): Model
    {
        return $this->transition(
            $user,
            $content,
            ContentApprovalAction::Submitted,
            'pending_approval',
            ['draft', 'rejected'],
            SignageAlert::ContentPendingApproval,
            $comment,
        );
    }

    public function approve(User $user, Model $content, ?string $comment = null): Model
    {
        $this->assertNotSelfReview($user, $content);

        return $this->transition(
            $user,
            $content,
            ContentApprovalAction::Approved,
            'approved',
            ['pending_approval'],
            SignageAlert::ContentApproved,
            $comment,
        );
    }

    public function reject(User $user, Model $content, string $comment): Model
    {
        $this->assertNotSelfReview($user, $content);

        if (trim($comment) === '') {
            throw ValidationException::withMessages([
                'comment' => __('Explain why this content was rejected.'),
            ]);
        }

        return $this->transition(
            $user,
            $content,
            ContentApprovalAction::Rejected,
            'rejected',
            ['pending_approval'],
            SignageAlert::ContentRejected,
            $comment,
        );
    }

    public function publish(User $user, Model $content, ?string $comment = null): Model
    {
        $this->assertReadyToPublish($content);

        $from = ContentWorkflow::publishFromStatuses($content);

        return $this->transition(
            $user,
            $content,
            ContentApprovalAction::Published,
            'published',
            $from,
            SignageAlert::ContentPublished,
            $comment,
        );
    }

    public function archive(User $user, Model $content, ?string $comment = null): Model
    {
        return $this->transition(
            $user,
            $content,
            ContentApprovalAction::Archived,
            'archived',
            ['draft', 'approved', 'published', 'rejected', 'scheduled'],
            null,
            $comment,
        );
    }

    /**
     * @param  list<string>  $from
     */
    protected function transition(
        User $user,
        Model $content,
        ContentApprovalAction $action,
        string $to,
        array $from,
        ?SignageAlert $alert,
        ?string $comment,
    ): Model {
        $current = ContentWorkflow::statusValue($content);

        if (! in_array($current, $from, true)) {
            throw ValidationException::withMessages([
                'status' => __('This item cannot move to :status from :current.', [
                    'status' => str_replace('_', ' ', $to),
                    'current' => str_replace('_', ' ', $current),
                ]),
            ]);
        }

        DB::transaction(function () use ($user, $content, $action, $current, $to, $comment) {
            $this->applyStatus($content, $to);

            ContentApprovalEvent::query()->create([
                'team_id' => $content->getAttribute('team_id'),
                'approvable_type' => $content->getMorphClass(),
                'approvable_id' => $content->getKey(),
                'action' => $action,
                'from_status' => $current,
                'to_status' => $to,
                'revision' => ContentWorkflow::version($content),
                'user_id' => $user->id,
                'comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
            ]);
        });

        if ($alert !== null) {
            $teamId = $content->getAttribute('team_id');
            $team = is_numeric($teamId) ? Team::query()->find((int) $teamId) : null;

            if ($team instanceof Team) {
                $title = ContentWorkflow::typeLabel($content).': '.ContentWorkflow::title($content);
                $this->alerts->queue(
                    $team,
                    $alert,
                    $title,
                    $comment ?: $action->label(),
                    [
                        'type' => $content->getMorphClass(),
                        'id' => $content->getKey(),
                        'action' => $action->value,
                    ],
                    'content-'.$action->value.':'.$content->getMorphClass().':'.$content->getKey().':'.ContentWorkflow::version($content),
                    60,
                );
            }
        }

        if ($action === ContentApprovalAction::Published) {
            $teamId = $content->getAttribute('team_id');
            $team = is_numeric($teamId) ? Team::query()->find((int) $teamId) : null;

            if ($team instanceof Team) {
                app(DispatchPartnerWebhook::class)->handle(
                    $team,
                    WebhookEvent::ContentPublished,
                    [
                        'type' => $content->getMorphClass(),
                        'id' => $content->getKey(),
                        'title' => ContentWorkflow::title($content),
                    ],
                );

                if ($content instanceof Playlist) {
                    app(DispatchPartnerWebhook::class)->handle(
                        $team,
                        WebhookEvent::PlaylistPublished,
                        ['id' => $content->getKey(), 'name' => ContentWorkflow::title($content)],
                    );
                }
            }
        }

        if ($action === ContentApprovalAction::Published && $content instanceof Playlist) {
            $teamId = $content->getAttribute('team_id');
            $team = is_numeric($teamId) ? Team::query()->find((int) $teamId) : null;

            if ($team instanceof Team) {
                app(RecordOrganizationAudit::class)->handle(
                    $team,
                    AuditAction::PlaylistPublished,
                    $user,
                    'playlist',
                    $content->getKey(),
                    ['status' => $current],
                    ['status' => $to, 'name' => ContentWorkflow::title($content)],
                );
            }
        }

        return $content->fresh() ?? $content;
    }

    protected function applyStatus(Model $content, string $status): void
    {
        $enum = match (true) {
            $content instanceof Playlist => PlaylistStatus::from($status),
            $content instanceof Design => DesignStatus::from($status),
            $content instanceof Template => TemplateStatus::from($status),
            $content instanceof Channel => ChannelStatus::from($status),
            default => throw ValidationException::withMessages([
                'status' => __('This content type cannot enter the approval workflow.'),
            ]),
        };

        $values = ['status' => $enum];

        if ($status === 'published') {
            $values['published_at'] = $content->getAttribute('published_at') ?? now();
        }

        if ($status === 'archived' && $content instanceof Template) {
            $values['archived_at'] = now();
        }

        $content->forceFill($values)->save();
    }

    protected function assertNotSelfReview(User $user, Model $content): void
    {
        $teamId = $content->getAttribute('team_id');
        $team = is_numeric($teamId) ? Team::query()->find((int) $teamId) : null;
        $role = $team instanceof Team ? $user->teamRole($team) : null;

        if (in_array($role, [TeamRole::Owner, TeamRole::Admin], true)) {
            return;
        }

        $submittedBy = ContentApprovalEvent::query()
            ->where('approvable_type', $content->getMorphClass())
            ->where('approvable_id', $content->getKey())
            ->where('action', ContentApprovalAction::Submitted)
            ->latest('id')
            ->value('user_id');

        if ($submittedBy !== null && (int) $submittedBy === $user->id) {
            throw ValidationException::withMessages([
                'status' => __('Ask another content manager to review work you submitted.'),
            ]);
        }
    }

    protected function assertReadyToPublish(Model $content): void
    {
        if (! $content instanceof Channel) {
            return;
        }

        $content->loadMissing('zones');

        if ($content->type === ChannelType::Playlist && $content->playlist_id === null) {
            throw ValidationException::withMessages([
                'playlist_id' => __('Choose a playlist before publishing this channel.'),
            ]);
        }

        if ($content->type === ChannelType::Live && blank($content->live_url)) {
            throw ValidationException::withMessages([
                'live_url' => __('A stream protocol and URL are required before publishing a live channel.'),
            ]);
        }

        if ($content->type === ChannelType::Advanced && $content->zones->isEmpty()) {
            throw ValidationException::withMessages([
                'zones' => __('A multi-zone channel needs at least one zone before it can be published.'),
            ]);
        }
    }
}
