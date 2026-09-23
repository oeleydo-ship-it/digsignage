export type BillingPermissions = {
    canManageBilling: boolean;
};

export type BillingPlan = {
    key: string;
    name: string;
    screens: number | null;
    storage_gb: number | null;
    users: number | null;
    bandwidth_gb: number | null;
    advanced: boolean;
    price_cents: number | null;
    features: Record<string, boolean>;
};

export type TeamPlanSummary = {
    key: string;
    name: string;
    price_cents: number | null;
    status_label: string;
    screens: number;
    screens_limit: number | null;
    users: number;
    users_limit: number | null;
};

export type BillingUsage = {
    screens: number;
    screens_limit: number | null;
    users: number;
    users_limit: number | null;
    storage_bytes: number;
    storage_limit_bytes: number | null;
    bandwidth_bytes: number;
    bandwidth_limit_bytes: number | null;
    advanced: boolean;
    features: Record<string, boolean>;
};

export type BillingSubscription = {
    plan_key: string | null;
    plan_name: string | null;
    status: string | null;
    status_label: string | null;
    trial_ends_at: string | null;
    subscription_ends_at: string | null;
    coupon_code: string | null;
    allows_mutations: boolean;
};

export type BillingInvoice = {
    id: number;
    number: string | null;
    amount_cents: number;
    currency: string;
    status: string;
    hosted_invoice_url: string | null;
    period_start: string | null;
    period_end: string | null;
    paid_at: string | null;
    created_at: string | null;
};
