import { QueuePageShell, queueBreadcrumbs } from '@/pages/queue/queue-page-shell';

type Props = {
    title: string;
};

const copy: Record<
    string,
    { heading: string; description: string; emptyTitle: string; emptyBody: string; path: string }
> = {
    'Live Queue': {
        heading: 'Live Queue',
        description:
            'Watch waiting and serving tickets in real time. This operations board arrives in a later phase.',
        emptyTitle: 'Live queue is coming soon',
        emptyBody:
            'Phase 1 is the Queue Management shell. Live operations land with ticket calling and real-time updates.',
        path: '/live',
    },
    Counters: {
        heading: 'Counters',
        description:
            'Open desks, assign staff, and track which services each counter can take.',
        emptyTitle: 'Counters are coming soon',
        emptyBody:
            'Counter management and the staff call dashboard are later phases.',
        path: '/counters',
    },
    Appointments: {
        heading: 'Appointments',
        description:
            'Check in booked visits and convert them into queue tickets.',
        emptyTitle: 'Appointments are coming soon',
        emptyBody:
            'Appointment booking and check-in land in a later phase.',
        path: '/appointments',
    },
    Displays: {
        heading: 'Displays',
        description:
            'Connect DigSignage screens to now-serving boards and queue widgets.',
        emptyTitle: 'Displays are coming soon',
        emptyBody:
            'Designer queue widgets and display boards are later phases.',
        path: '/displays',
    },
    Reports: {
        heading: 'Reports',
        description:
            'Volume, wait times, staff performance, and SLA reports for this team.',
        emptyTitle: 'Reports are coming soon',
        emptyBody:
            'Queue analytics and exports land after the ticket engine exists.',
        path: '/reports',
    },
    Settings: {
        heading: 'Settings',
        description:
            'Workspace-level queue options. Each team already has a dedicated settings row.',
        emptyTitle: 'Queue settings are coming soon',
        emptyBody:
            'This page is the Phase 1 shell. Configurable options will appear as later phases land.',
        path: '/settings',
    },
};

export default function QueueComingSoon({ title }: Props) {
    const page = copy[title] ?? copy['Live Queue'];

    return (
        <QueuePageShell
            title={page.heading}
            description={page.description}
            empty={{
                title: page.emptyTitle,
                description: page.emptyBody,
            }}
        />
    );
}

QueueComingSoon.layout = (props: {
    currentTeam?: { slug: string } | null;
    title?: string;
}) => {
    const page = copy[props.title ?? ''] ?? copy['Live Queue'];

    return queueBreadcrumbs(page.heading, page.path)(props);
};
