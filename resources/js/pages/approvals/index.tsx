import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { PendingApprovalItem } from '@/types';

type Props = {
    pending: PendingApprovalItem[];
    approvalEnabled: boolean;
};

export default function ApprovalsIndex({ pending, approvalEnabled }: Props) {
    return (
        <>
            <Head title="Approvals" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Approvals"
                    description="Designers submit work. Content managers approve or reject. Publishers release approved items to screens."
                />

                {!approvalEnabled && (
                    <p className="rounded-lg border px-4 py-3 text-sm">
                        Content approval is disabled in Team settings. Authorized publishers can publish directly from an editor.
                    </p>
                )}

                <section className="rounded-lg border" data-test="approvals-inbox">
                    <div className="border-b px-4 py-3">
                        <h2 className="font-semibold">Pending review</h2>
                    </div>
                    {pending.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-8 text-sm">
                            Nothing is waiting for approval.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {pending.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex flex-col gap-2 px-4 py-3 md:flex-row md:items-center md:justify-between"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {item.title}
                                        </p>
                                        <p className="text-muted-foreground text-sm">
                                            {item.type_label}
                                            {item.submitted_by
                                                ? ` · submitted by ${item.submitted_by}`
                                                : ''}
                                            {` · rev ${item.revision}`}
                                        </p>
                                        {item.comment && (
                                            <p className="text-muted-foreground mt-1 text-sm">
                                                {item.comment}
                                            </p>
                                        )}
                                    </div>
                                    <Button size="sm" asChild>
                                        <Link href={item.edit_url}>Review</Link>
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

ApprovalsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Approvals',
            href: props.currentTeam
                ? `/${props.currentTeam.slug}/approvals`
                : '/',
        },
    ],
});
