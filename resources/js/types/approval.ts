export type ContentApprovalHistoryItem = {
    id: number;
    action: string;
    action_label: string;
    from_status: string;
    to_status: string;
    revision: number;
    comment: string | null;
    user_name: string | null;
    created_at: string | null;
};

export type ContentApprovalPayload = {
    type: string;
    id: number;
    status: string;
    locked: boolean;
    can_submit: boolean;
    can_approve: boolean;
    can_reject: boolean;
    can_publish: boolean;
    can_archive: boolean;
    history: ContentApprovalHistoryItem[];
};

export type PendingApprovalItem = {
    id: number;
    type: string;
    type_label: string;
    content_id: number;
    title: string;
    revision: number;
    submitted_by: string | null;
    comment: string | null;
    submitted_at: string | null;
    edit_url: string;
};
