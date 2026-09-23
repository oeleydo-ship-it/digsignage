export type MediaPermissions = {
    canViewMedia: boolean;
    canCreateMedia: boolean;
    canUpdateMedia: boolean;
    canDeleteMedia: boolean;
};

export type MediaTypeValue =
    | 'image'
    | 'video'
    | 'audio'
    | 'pdf'
    | 'html_package'
    | 'url'
    | 'live_stream';

export type MediaFolderRecord = {
    id: number;
    parent_id: number | null;
    name: string;
    path: string;
    depth: number;
    children_count: number;
    media_count: number;
};

export type MediaRecord = {
    id: number;
    name: string;
    type: MediaTypeValue;
    type_label: string;
    source: string;
    original_filename: string | null;
    mime_type: string | null;
    file_size: number | null;
    duration: number | null;
    width: number | null;
    height: number | null;
    processing_status: string;
    processing_status_label: string;
    processing_error: string | null;
    external_url: string | null;
    usage_count: number;
    archived_at: string | null;
    folder_id: number | null;
    folder_name: string | null;
    has_file: boolean;
    has_thumbnail: boolean;
    created_at: string | null;
    tags: string[];
};
