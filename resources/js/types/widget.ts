export type WidgetFieldOption = { value: string; label: string };

export type WidgetField = {
    name: string;
    type: string;
    label: string;
    default: string | number | boolean | null;
    required: boolean;
    options: WidgetFieldOption[];
    help: string | null;
};

export type WidgetDefinition = {
    key: string;
    label: string;
    description: string;
    defaults: Record<string, string | number | boolean | null>;
    schema: WidgetField[];
};

export type WidgetJsonValue = string | number | boolean | null | WidgetJsonValue[] | { [key: string]: WidgetJsonValue };

export type WidgetPayload = {
    key: string;
    settings: Record<string, WidgetJsonValue>;
    data: Record<string, WidgetJsonValue>;
};
