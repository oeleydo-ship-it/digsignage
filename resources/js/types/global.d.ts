import type { InAppNotificationItem } from '@/types/notifications';
import type { Auth } from '@/types/auth';
import type { BillingPermissions } from '@/types/billing';
import type { ChannelPermissions } from '@/types/channel';
import type { DesignPermissions } from '@/types/design';
import type { EmergencyPermissions } from '@/types/emergency';
import type { MediaPermissions } from '@/types/media';
import type { PlaylistPermissions } from '@/types/playlist';
import type { QueuePermissions } from '@/types/queue';
import type {
    ImpersonationState,
    PlatformAnnouncement,
} from '@/types/platform';
import type { SchedulePermissions } from '@/types/schedule';
import type { SignagePermissions } from '@/types/signage';
import type { Team } from '@/types/teams';
import type { TemplatePermissions } from '@/types/template';
import type { WidgetDefinition } from '@/types/widget';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            currentTeam: Team | null;
            teams: Team[];
            signage: SignagePermissions | null;
            mediaPermissions: MediaPermissions | null;
            designPermissions: DesignPermissions | null;
            templatePermissions: TemplatePermissions | null;
            playlistPermissions: PlaylistPermissions | null;
            channelPermissions: ChannelPermissions | null;
            schedulePermissions: SchedulePermissions | null;
            emergencyPermissions: EmergencyPermissions | null;
            queuePermissions: QueuePermissions | null;
            billingPermissions: BillingPermissions | null;
            canViewAuditLogs: boolean;
            unreadNotifications: number;
            recentNotifications: InAppNotificationItem[];
            widgets: WidgetDefinition[];
            isPlatformAdmin: boolean;
            impersonation: ImpersonationState | null;
            announcements: PlatformAnnouncement[];
            [key: string]: unknown;
        };
    }
}

export type DigsignagePlayerShell = {
    platform: string;
    version: string;
    captureScreenshot?: () => Promise<string | null>;
    restart?: () => Promise<void>;
};

declare global {
    interface Window {
        digsignagePlayer?: DigsignagePlayerShell;
    }
}
