import type { LucideIcon } from 'lucide-react';
import {
    BarChart3,
    Braces,
    Calendar,
    Clock,
    CloudSun,
    FileText,
    Film,
    Globe,
    Image as ImageIcon,
    LayoutTemplate,
    Music,
    Newspaper,
    Palette,
    QrCode,
    Radio,
    Rss,
    Timer,
    Type,
    Youtube,
} from 'lucide-react';
import { cn } from '@/lib/utils';

const widgetIcons: Record<string, LucideIcon> = {
    clock: Clock,
    date: Calendar,
    weather: CloudSun,
    rss: Rss,
    news: Newspaper,
    qr_code: QrCode,
    web_page: Globe,
    youtube: Youtube,
    calendar: Calendar,
    countdown: Timer,
    ticker: Type,
    json_api: Braces,
    charts: BarChart3,
};

function iconFor(
    type: string,
    mediaType?: string | null,
    widgetKey?: string | null,
): LucideIcon {
    if (type === 'widget' && widgetKey && widgetIcons[widgetKey]) {
        return widgetIcons[widgetKey];
    }

    if (type === 'media') {
        if (mediaType === 'video') return Film;
        if (mediaType === 'audio') return Music;
        if (mediaType === 'pdf') return FileText;
        if (mediaType === 'live_stream') return Radio;
        if (mediaType === 'url' || mediaType === 'html_package') return Globe;

        return ImageIcon;
    }

    if (type === 'design') return Palette;
    if (type === 'template') return LayoutTemplate;
    if (type === 'live_stream') return Radio;
    if (type === 'web_page') return Globe;

    return LayoutTemplate;
}

export function PlaylistThumb({
    src,
    alt,
    type,
    mediaType,
    widgetKey,
    label,
    className,
}: {
    src?: string | null;
    alt: string;
    type: string;
    mediaType?: string | null;
    widgetKey?: string | null;
    label: string;
    className?: string;
}) {
    const Icon = iconFor(type, mediaType, widgetKey);

    return (
        <div
            className={cn(
                'bg-muted relative overflow-hidden',
                className,
            )}
        >
            {src ? (
                <img
                    src={src}
                    alt={alt}
                    className="h-full w-full object-cover"
                    loading="lazy"
                />
            ) : (
                <div className="text-muted-foreground flex h-full flex-col items-center justify-center gap-1 p-2">
                    <Icon className="size-6" />
                    <span className="max-w-full truncate text-[10px] font-medium tracking-wide uppercase">
                        {label}
                    </span>
                </div>
            )}
        </div>
    );
}
