import type { WidgetPayload } from './widget';

export type DesignPermissions = {
    canViewDesigns: boolean;
    canCreateDesign: boolean;
    canUpdateDesign: boolean;
    canDeleteDesign: boolean;
};

export type DesignDocumentElement = {
    id: string;
    type: string;
    name: string;
    x: number;
    y: number;
    width: number;
    height: number;
    rotation: number;
    opacity: number;
    zIndex: number;
    locked: boolean;
    hidden: boolean;
    props: Record<string, string | number | boolean | null>;
    widget?: WidgetPayload;
};

export type DesignDocument = {
    width: number;
    height: number;
    background: string;
    elements: DesignDocumentElement[];
};

export type DesignRecord = {
    id: number;
    name: string;
    description: string | null;
    status: string;
    status_label: string;
    width: number;
    height: number;
    version: number;
    updated_at: string | null;
    document?: DesignDocument;
};
