export type QueuePermissions = {
    canViewQueue: boolean;
    canManageQueue: boolean;
    canCallQueue: boolean;
    canTransferQueue: boolean;
    canCompleteQueue: boolean;
    canCancelQueue: boolean;
    canManageServices: boolean;
    canManageCounters: boolean;
    canManageKiosks: boolean;
    canManageAppointments: boolean;
    canViewReports: boolean;
    canManageSettings: boolean;
};

export type QueueOverviewStats = {
    waiting: number;
    serving: number;
    completed_today: number;
    no_shows_today: number;
    average_wait_seconds: number | null;
    average_service_seconds: number | null;
    longest_waiting: {
        id: number;
        number: string;
        customer_name: string | null;
        service_name: string;
        seconds: number;
    } | null;
    open_counters: number;
    closed_counters: number;
};

export type QueueCongestion = {
    level: 'normal' | 'moderate' | 'high' | 'critical';
    label: string;
};

export type QueueOperationsService = {
    id: number;
    name: string;
    location_id: number | null;
    location_name: string;
    waiting: number;
    serving: number;
    longest_wait_seconds: number;
    open_counters: number;
    total_counters: number;
    congestion: QueueCongestion;
};

export type QueueOperationsLocation = Omit<
    QueueOperationsService,
    'location_id' | 'location_name'
> & {
    id: number | null;
    services: QueueOperationsService[];
};

export type QueueOperationsCounter = {
    id: number;
    name: string;
    code: string;
    location_name: string;
    status: QueueCounterStatusValue;
    status_label: string;
    services: string[];
    waiting: number;
    current_ticket: string | null;
    current_status: string | null;
};

export type QueueOperationsDashboard = {
    stats: QueueOverviewStats;
    locations: QueueOperationsLocation[];
    services: QueueOperationsService[];
    counters: QueueOperationsCounter[];
    service_ids: number[];
    refreshed_at: string;
};

export type QueueAnalyticsDurationStats = {
    average: number | null;
    median: number | null;
    maximum: number | null;
    minimum?: number | null;
    samples: number;
};

export type QueueAnalyticsBar = { label: string; value: number };

export type QueueAnalyticsReport = {
    summary: {
        tickets: number;
        completed: number;
        abandonment_rate: number;
        no_show_rate: number;
        sla_compliance: number;
        sla_eligible: number;
    };
    waiting_time: QueueAnalyticsDurationStats;
    service_time: QueueAnalyticsDurationStats;
    volume: {
        hour: QueueAnalyticsBar[];
        day: QueueAnalyticsBar[];
        week: QueueAnalyticsBar[];
        month: QueueAnalyticsBar[];
    };
    peak_hours: QueueAnalyticsBar[];
    locations: Array<{
        id: number | null;
        name: string;
        tickets: number;
        completed: number;
        average_wait_seconds: number | null;
        average_service_seconds: number | null;
        sla_compliance: number;
        no_shows: number;
        abandoned: number;
    }>;
    staff: Array<{
        id: number;
        name: string;
        tickets_served: number;
        average_handling_seconds: number | null;
        no_shows: number;
    }>;
    counters: Array<{
        id: number;
        name: string;
        tickets_served: number;
        average_wait_seconds: number | null;
        average_handling_seconds: number | null;
        no_shows: number;
    }>;
    services: Array<{
        id: number;
        name: string;
        tickets: number;
        completed: number;
        average_wait_seconds: number | null;
        average_service_seconds: number | null;
        no_shows: number;
        abandoned: number;
    }>;
};

export type QueueNumberingResetValue = 'daily' | 'weekly' | 'monthly' | 'never';

export type QueueOpeningHoursDay = {
    open: string;
    close: string;
    closed: boolean;
};

export type QueueOpeningHours = {
    mon: QueueOpeningHoursDay;
    tue: QueueOpeningHoursDay;
    wed: QueueOpeningHoursDay;
    thu: QueueOpeningHoursDay;
    fri: QueueOpeningHoursDay;
    sat: QueueOpeningHoursDay;
    sun: QueueOpeningHoursDay;
};

export type QueueStrategyValue = 'fifo' | 'priority';

export type QueueStarvation = {
    max_priority_wait_seconds: number | null;
    promote_after_seconds: number | null;
};

export type QueueAlertSettings = {
    enabled: boolean;
    average_wait_minutes: number;
    waiting_customers: number;
    customer_wait_minutes: number;
    no_counter_available: boolean;
    capacity_reached: boolean;
    counter_offline: boolean;
    cooldown_minutes: number;
};

export type QueueVoiceLanguage = 'en-US' | 'ar-AE' | 'fil-PH' | 'hi-IN';

export type QueueVoiceSettings = {
    enabled: boolean;
    languages: QueueVoiceLanguage[];
    voice: string | null;
    speed: number;
    volume: number;
    repeat_count: number;
    chime: boolean;
    announcement_delay_seconds: number;
};

export type QueueAppointmentStatus =
    | 'scheduled'
    | 'checked_in'
    | 'completed'
    | 'cancelled'
    | 'no_show';

export type QueueAppointmentRecord = {
    id: number;
    customer_name: string;
    customer_phone: string | null;
    customer_email: string | null;
    queue_service_id: number;
    service_name: string;
    location_id: number | null;
    location_name: string | null;
    scheduled_at: string;
    reference: string;
    status: QueueAppointmentStatus;
    status_label: string;
    check_in_source: string | null;
    checked_in_at: string | null;
    ticket_number: string | null;
    check_in_url: string;
};

export type QueueAppointmentRules = {
    priority_id: number | null;
    check_in_before_minutes: number;
    check_in_after_minutes: number;
};

export type QueueCustomerNotificationSettings = {
    events: Array<{ value: string; label: string }>;
    channels: Array<{ value: string; label: string }>;
    rules: Record<string, Record<string, boolean>>;
    appointment_minutes_before: number;
};

export type QueuePriorityRecord = {
    id: number;
    name: string;
    code: string;
    weight: number;
    color: string | null;
    is_active: boolean;
    sort_order: number;
};

export type QueueServiceRecord = {
    id: number;
    location_id: number | null;
    location_name: string | null;
    name: string;
    code: string;
    ticket_prefix: string;
    description: string | null;
    opening_hours: QueueOpeningHours;
    average_service_duration_seconds: number;
    max_queue_capacity: number | null;
    numbering_reset: QueueNumberingResetValue;
    numbering_reset_label: string;
    next_sequence: number;
    last_issued: number | null;
    sequence_period: string | null;
    next_ticket_number: string;
    priority_rules: unknown[];
    default_priority: number;
    queue_strategy: QueueStrategyValue;
    queue_strategy_label: string;
    starvation: QueueStarvation | null;
    display_color: string;
    is_active: boolean;
    join_url: string;
};

export type QueueTicketStatusValue =
    | 'waiting'
    | 'called'
    | 'serving'
    | 'on_hold'
    | 'transferred'
    | 'completed'
    | 'no_show'
    | 'cancelled';

export type QueueTicketSourceValue =
    | 'kiosk'
    | 'staff'
    | 'qr'
    | 'website'
    | 'appointment'
    | 'api';

export type QueueTicketEventTypeValue =
    | 'created'
    | 'status_changed'
    | 'transferred';

export type QueueTicketEventRecord = {
    id: number;
    type: QueueTicketEventTypeValue;
    type_label: string;
    user_name: string | null;
    created_at: string | null;
    payload: Record<string, unknown> | null;
};

export type QueueTicketRecord = {
    id: number;
    number: string;
    queue_service_id: number;
    service_name: string | null;
    status: QueueTicketStatusValue;
    status_label: string;
    queue_position: number | null;
    source: QueueTicketSourceValue;
    source_label: string;
    priority: number;
    queue_priority_id: number | null;
    priority_name: string | null;
    customer_name: string | null;
    created_at: string | null;
    events: QueueTicketEventRecord[];
};

export type QueueTicketServiceOption = {
    id: number;
    name: string;
    ticket_prefix: string;
    is_active: boolean;
    next_ticket_number: string;
};

export type QueueLiveWaitingTicket = {
    id: number;
    number: string;
    priority: number;
    queue_position: number | null;
    customer_name: string | null;
    created_at: string | null;
};

export type QueueLiveService = {
    id: number;
    name: string;
    queue_strategy: QueueStrategyValue;
    queue_strategy_label: string;
    waiting_count: number;
    waiting: QueueLiveWaitingTicket[];
};

export type QueueCounterStatusValue = 'open' | 'closed' | 'busy' | 'paused';

export type QueueCounterServiceOption = {
    id: number;
    name: string;
    code: string;
    is_active?: boolean;
    location_id?: number | null;
};

export type QueueCounterMemberOption = {
    id: number;
    name: string;
    email: string;
};

export type QueueCounterRecord = {
    id: number;
    location_id: number | null;
    location_name: string | null;
    name: string;
    code: string;
    status: QueueCounterStatusValue;
    status_label: string;
    assigned_user_id: number | null;
    assigned_user_name: string | null;
    service_ids: number[];
    services: QueueCounterServiceOption[];
    can_operate: boolean;
};

export type QueueDeskTicket = {
    id: number;
    number: string;
    status: QueueTicketStatusValue;
    status_label: string;
    customer_name: string | null;
    called_at: string | null;
    service_started_at: string | null;
    queue_service_id: number;
};

export type QueueDeskCounter = {
    id: number;
    name: string;
    code: string;
    status: QueueCounterStatusValue;
    status_label: string;
    assigned_user_name: string | null;
    services: QueueCounterServiceOption[];
};

export type QueueDeskTransferCounter = {
    id: number;
    name: string;
    code: string;
    location_id: number | null;
    service_ids: number[];
};

export type QueueKioskBranding = {
    logo_url: string | null;
    title: string | null;
    footer: string | null;
    print_qr_code: boolean;
    colors: {
        background: string;
        primary: string;
        text: string;
        button_text: string;
    };
};

export type QueueKioskRecord = {
    id: number;
    location_id: number | null;
    location_name: string | null;
    name: string;
    branding: QueueKioskBranding;
    printer_enabled: boolean;
    is_active: boolean;
    has_pin: boolean;
    serve_url: string;
};

export type QueueKioskServeService = {
    id: number;
    name: string;
    display_color: string;
    waiting_count: number;
};

export type QueueKioskIssuedTicket = {
    id: number;
    number: string;
    service_name: string | null;
    location_name: string | null;
    people_ahead: number;
    estimated_wait_seconds: number;
    estimated_wait_minutes: number;
    issued_at: string | null;
    qr_value: string;
};

export type QueueKioskServeKiosk = {
    id: number;
    name: string;
    printer_enabled: boolean;
    location_name: string | null;
    team_name: string;
    branding: QueueKioskBranding;
};
