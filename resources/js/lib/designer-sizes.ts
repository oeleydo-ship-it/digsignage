/**
 * Natural size of each element when added to a 1920×1080 canvas. Widgets
 * with rich layouts (door signs, schedules, KPI tiles) need far more room
 * than the generic default to be legible straight away.
 */
const NATURAL_SIZES: Record<string, [number, number]> = {
    icon: [200, 200],
    qr_code: [320, 320],
    ticker: [1600, 90],
    clock: [480, 200],
    analog_clock: [380, 380],
    date: [640, 160],
    world_clock: [1200, 280],
    countdown: [640, 240],
    weather: [480, 280],
    weather_forecast: [1200, 380],
    room_status: [1280, 720],
    room_board: [960, 680],
    room_info: [720, 420],
    booking: [800, 640],
    calendar: [720, 540],
    event_schedule: [960, 640],
    metric_tiles: [1000, 520],
    progress_goal: [800, 460],
    gauge: [420, 420],
    charts: [800, 520],
    chart: [800, 520],
    table: [900, 520],
    safety_counter: [720, 520],
    celebrations: [860, 580],
    directory: [860, 580],
    menu_board: [860, 760],
    quote: [1100, 420],
    alert_banner: [1400, 300],
    social_wall: [800, 700],
    rss: [900, 560],
    news: [900, 560],
    image_gallery: [960, 540],
    web_page: [1200, 700],
    youtube: [960, 540],
};

/**
 * Size for a newly added element, scaled down proportionally so it fits
 * within 90% of the canvas on any orientation. Null means "use the caller's
 * own default".
 */
export function defaultElementSize(
    type: string,
    canvasWidth: number,
    canvasHeight: number,
): { width: number; height: number } | null {
    const natural = NATURAL_SIZES[type];

    if (!natural) {
        return null;
    }

    const [width, height] = natural;
    const ratio = Math.min(
        1,
        (canvasWidth * 0.9) / width,
        (canvasHeight * 0.9) / height,
    );

    return {
        width: Math.round(width * ratio),
        height: Math.round(height * ratio),
    };
}
