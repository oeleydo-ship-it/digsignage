import { Head, usePage } from '@inertiajs/react';
import { FullscreenCanvasPreview } from '@/components/canvas-preview';
import { edit } from '@/routes/designs';
import type { DesignDocument, DesignRecord } from '@/types';

type Props = {
    design: Pick<DesignRecord, 'id' | 'name'> & { document: DesignDocument };
};

export default function DesignPreview({ design }: Props) {
    const { currentTeam } = usePage().props;
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const closeHref =
        currentTeam && design
            ? edit.url({
                  current_team: currentTeam.slug,
                  design: design.id,
              })
            : '/';

    return (
        <>
            <Head title={`${design.name} preview`}>
                <meta head-key="referrer" name="referrer" content="origin" />
            </Head>
            <FullscreenCanvasPreview
                document={design.document}
                timezone={timezone}
                closeHref={closeHref}
            />
        </>
    );
}
