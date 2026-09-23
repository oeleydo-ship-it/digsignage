import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    name: string;
    category: string;
    status_label: string;
    updated_at: string | null;
};

type Props = { templates: Paginated<Row> };

export default function PlatformTemplates({ templates }: Props) {
    return (
        <>
            <Head title="Platform templates" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Platform templates" description="Catalog templates shared with every organization." />
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="p-3">Name</th>
                                <th className="p-3">Category</th>
                                <th className="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {templates.data.length === 0 ? (
                                <tr>
                                    <td className="p-3 text-muted-foreground" colSpan={3}>
                                        No platform templates yet.
                                    </td>
                                </tr>
                            ) : (
                                templates.data.map((template) => (
                                    <tr key={template.id} className="border-b">
                                        <td className="p-3">{template.name}</td>
                                        <td className="p-3">{template.category}</td>
                                        <td className="p-3">{template.status_label}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

PlatformTemplates.layout = () => ({
    breadcrumbs: [
        { title: 'Platform', href: '/platform' },
        { title: 'Templates', href: '/platform/templates' },
    ],
});
