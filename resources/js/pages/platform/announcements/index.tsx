import { Head, router, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Row = {
    id: number;
    title: string;
    body: string;
    published_at: string | null;
    expires_at: string | null;
    author: string | null;
};

type Props = { announcements: Row[] };

export default function PlatformAnnouncements({ announcements }: Props) {
    const form = useForm({
        title: '',
        body: '',
        published_at: '',
        expires_at: '',
    });

    return (
        <>
            <Head title="Announcements" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Announcements" description="Messages shown on every organization dashboard." />
                <form
                    className="grid max-w-xl gap-3 rounded-lg border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/platform/announcements', { onSuccess: () => form.reset() });
                    }}
                >
                    <div className="space-y-1">
                        <Label htmlFor="title">Title</Label>
                        <Input id="title" value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="body">Body</Label>
                        <textarea
                            id="body"
                            className="min-h-24 w-full rounded-md border bg-background p-2"
                            value={form.data.body}
                            onChange={(event) => form.setData('body', event.target.value)}
                        />
                    </div>
                    <Button type="submit">Publish</Button>
                </form>
                <div className="space-y-3">
                    {announcements.map((item) => (
                        <article key={item.id} className="rounded-lg border p-4">
                            <h2 className="font-medium">{item.title}</h2>
                            <p className="mt-1 text-sm text-muted-foreground">{item.body}</p>
                            <Button
                                className="mt-2"
                                size="sm"
                                variant="outline"
                                onClick={() => router.delete(`/platform/announcements/${item.id}`)}
                            >
                                Remove
                            </Button>
                        </article>
                    ))}
                </div>
            </div>
        </>
    );
}

PlatformAnnouncements.layout = () => ({
    breadcrumbs: [
        { title: 'Platform', href: '/platform' },
        { title: 'Announcements', href: '/platform/announcements' },
    ],
});
