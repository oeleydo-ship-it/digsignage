import { Head, usePage } from '@inertiajs/react';
import { FullscreenCanvasPreview } from '@/components/canvas-preview';
import { index } from '@/routes/templates';
import type { DesignDocument, TemplateRecord } from '@/types';

type Props = {
    template: Pick<TemplateRecord, 'id' | 'name'> & { document: DesignDocument };
};

export default function TemplatePreview({ template }: Props) {
    const { currentTeam } = usePage().props;
    const closeHref = currentTeam
        ? index.url({ current_team: currentTeam.slug })
        : '/';

    return (
        <>
            <Head title={`${template.name} preview`} />
            <FullscreenCanvasPreview
                document={template.document}
                timezone={Intl.DateTimeFormat().resolvedOptions().timeZone}
                closeHref={closeHref}
            />
        </>
    );
}
