export type ProofOfPlayEvent = {
    id: number;
    screen: string | null;
    location: string | null;
    channel: string | null;
    playlist: string | null;
    content_id: string | null;
    title: string | null;
    started_at: string | null;
    ended_at: string | null;
    duration_ms: number | null;
    status: string;
    status_label: string;
};

export type ProofOfPlayGroup = {
    key: string;
    label: string;
    plays: number;
    duration_ms: number;
    screens: number;
};

export type ProofOfPlayTotals = {
    plays: number;
    duration_ms: number;
    screens: number;
    content: number;
};
