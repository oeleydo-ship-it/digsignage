export type TemplatePermissions = {
    canViewTemplates: boolean;
    canCreateTemplate: boolean;
    canUpdateTemplate: boolean;
    canDeleteTemplate: boolean;
    canManagePlatformTemplates: boolean;
};

export type TemplateRecord = {
    id: number;
    name: string;
    description: string | null;
    category: string;
    category_label: string;
    status: string;
    status_label: string;
    width: number;
    height: number;
    platform: boolean;
    has_thumbnail?: boolean;
    thumbnail_url?: string | null;
    document?: import('./design').DesignDocument | null;
    updated_at: string | null;
};
