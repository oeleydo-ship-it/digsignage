import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import type { ContentApprovalPayload } from '@/types';

type Props = {
    approval: ContentApprovalPayload;
    hidePublish?: boolean;
    compact?: boolean;
};

const statusLabel = (status: string) =>
    status.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

export default function ContentWorkflowPanel({
    approval,
    hidePublish = false,
    compact = false,
}: Props) {
    const slug = usePage().props.currentTeam?.slug ?? '';
    const [comment, setComment] = useState('');
    const [showComment, setShowComment] = useState(!compact);

    const canAct =
        approval.can_submit ||
        approval.can_approve ||
        approval.can_reject ||
        (approval.can_publish && !hidePublish) ||
        approval.can_archive;

    const post = (action: string, withComment = false) => {
        router.post(
            `/${slug}/approvals/${approval.type}/${approval.id}/${action}`,
            withComment || action === 'reject' ? { comment } : {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const reject = () => {
        if (compact && !showComment) {
            setShowComment(true);
            return;
        }
        post('reject', true);
    };

    return (
        <section
            className={
                compact
                    ? 'flex min-w-0 flex-wrap items-center gap-1.5'
                    : 'bg-muted/40 w-full space-y-3 border-b px-3 py-3'
            }
            data-test="content-workflow"
        >
            <div
                className={
                    compact
                        ? 'flex flex-wrap items-center gap-1.5'
                        : 'flex flex-wrap items-center justify-between gap-2'
                }
            >
                <p className={compact ? 'text-muted-foreground text-xs' : 'text-sm'}>
                    <span className="font-medium">Workflow:</span>{' '}
                    {statusLabel(approval.status)}
                    {!approval.approval_enabled && (
                        <span className="text-muted-foreground ml-2">(review disabled)</span>
                    )}
                    {approval.locked && (
                        <span className="text-muted-foreground ml-2">
                            Locked while awaiting review
                        </span>
                    )}
                </p>
                <div className="flex flex-wrap gap-1.5">
                    {approval.can_submit && (
                        <Button
                            size="sm"
                            onClick={() => post('submit', true)}
                            data-test="submit-approval"
                        >
                            Submit for approval
                        </Button>
                    )}
                    {approval.can_approve && (
                        <Button
                            size="sm"
                            onClick={() => post('approve', true)}
                            data-test="approve-content"
                        >
                            Approve
                        </Button>
                    )}
                    {approval.can_reject && (
                        <Button
                            size="sm"
                            variant="destructive"
                            onClick={reject}
                            data-test="reject-content"
                        >
                            Reject
                        </Button>
                    )}
                    {approval.can_publish && !hidePublish && (
                        <Button
                            size="sm"
                            onClick={() => post('publish', true)}
                            data-test="publish-content"
                        >
                            Publish
                        </Button>
                    )}
                    {approval.can_archive && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => post('archive', true)}
                            data-test="archive-content"
                        >
                            Archive
                        </Button>
                    )}
                    {compact && canAct && !showComment && (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setShowComment(true)}
                        >
                            Comment
                        </Button>
                    )}
                </div>
            </div>
            {canAct && showComment && (
                <textarea
                    className={
                        compact
                            ? 'border-input bg-background h-8 min-w-[12rem] flex-1 rounded-md border px-2 py-1 text-xs'
                            : 'border-input bg-background min-h-16 w-full rounded-md border px-3 py-2 text-sm'
                    }
                    placeholder="Reviewer comment (required to reject)"
                    value={comment}
                    onChange={(event) => setComment(event.target.value)}
                    data-test="approval-comment"
                />
            )}
        </section>
    );
}
