import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Checkbox } from '@/components/ui/checkbox';

type Flag = {
    id: number;
    key: string;
    name: string;
    description: string | null;
    enabled: boolean;
};

type Props = { flags: Flag[] };

export default function PlatformFeatureFlags({ flags }: Props) {
    return (
        <>
            <Head title="Feature flags" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Feature flags" description="Toggle platform-wide behavior." />
                <div className="space-y-3">
                    {flags.map((flag) => (
                        <label key={flag.id} className="flex items-start gap-3 rounded-lg border p-4">
                            <Checkbox
                                checked={flag.enabled}
                                onCheckedChange={(checked) =>
                                    router.patch(`/platform/feature-flags/${flag.id}`, {
                                        enabled: checked === true,
                                    })
                                }
                                aria-label={flag.name}
                            />
                            <span>
                                <span className="block font-medium">{flag.name}</span>
                                <span className="text-muted-foreground text-sm">{flag.description}</span>
                            </span>
                        </label>
                    ))}
                </div>
            </div>
        </>
    );
}

PlatformFeatureFlags.layout = () => ({
    breadcrumbs: [
        { title: 'Platform', href: '/platform' },
        { title: 'Feature flags', href: '/platform/feature-flags' },
    ],
});
