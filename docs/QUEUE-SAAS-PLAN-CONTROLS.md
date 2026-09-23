# Queue SaaS plan controls

Phase 29 makes Queue Management an optional SaaS module through the existing plan feature map.

## Entitlements

- `queue_management` controls access to the core queue module.
- `analytics` independently controls queue reports and CSV exports.
- `partner_api` and `queue_management` are both required for queue API endpoints.
- `webhooks` controls queue webhook delivery, including after a plan downgrade.

Enforcement checks feature keys rather than plan names. Platform administrators can therefore enable Queue Management on any editable plan without changing application code.

## Enforcement boundaries

The core entitlement is enforced for authenticated queue pages and mutations, public kiosks, appointment check-in, virtual queue and ticket links, versioned queue API endpoints, scheduled alerts, and appointment reminders. When disabled, queue navigation permissions and Designer queue widgets are omitted. Existing queue widgets delivered to a player resolve to an entitlement error instead of live queue data.

Subscription status remains separate from feature inclusion: the existing billing mutation rules continue to decide whether an otherwise entitled organization may add or change resources.

## Default catalog

The built-in Starter plan excludes Queue Management. Business and Enterprise include it. These are catalog defaults only; persisted plan feature settings override them through the platform plan editor.
