export const WIDGET_KEYS = [
    'clock',
    'date',
    'weather',
    'rss',
    'news',
    'qr_code',
    'queue_now_serving',
    'queue_recently_called',
    'queue_waiting_tickets',
    'queue_position',
    'queue_counter_number',
    'queue_service_name',
    'queue_estimated_wait',
    'queue_statistics',
    'queue_ticker',
    'queue_join_qr',
    'queue_status',
    'queue_board',
    'web_page',
    'youtube',
    'calendar',
    'countdown',
    'ticker',
    'json_api',
    'charts',
    'room_info',
    'booking',
    'menu_board',
    'social_wall',
    'alert_banner',
    'world_clock',
    'table',
    'analog_clock',
    'weather_forecast',
    'metric_tiles',
    'progress_goal',
    'gauge',
    'quote',
    'safety_counter',
    'image_gallery',
    'directory',
    'celebrations',
    'event_schedule',
    'room_status',
    'room_board',
] as const;

export type WidgetKey = (typeof WIDGET_KEYS)[number];

export function canonicalWidgetKey(type: string): string {
    if (type === 'chart') {
        return 'charts';
    }

    if (type === 'iframe') {
        return 'web_page';
    }

    return type;
}

export function isWidgetType(type: string): boolean {
    return (WIDGET_KEYS as readonly string[]).includes(
        canonicalWidgetKey(type),
    );
}
