# Queue database architecture

Phase 25 confirms that Queue Management is a normalized, team-owned module that reuses the platform's existing identity, location, and signage entities.

## Ownership map

| Queue concern | Storage | Ownership and reuse |
| --- | --- | --- |
| Module configuration | `queue_settings` | One row per `teams` record |
| Services and priorities | `queue_services`, `queue_priorities` | Team-owned; services optionally reference `locations` |
| Counters and capabilities | `queue_counters`, `queue_counter_service` | Team-owned counters; optional `locations` and `users`; normalized many-to-many service assignment |
| Tickets and history | `queue_tickets`, `queue_ticket_events` | Team-owned; references services, priorities, counters, locations, and users |
| Kiosks | `queue_kiosks` | Team-owned; optionally references an existing location |
| Appointments | `queue_appointments` | Team-owned; references services, locations, and the ticket created at check-in |
| Customer notifications | `queue_notification_rules`, `queue_notification_deliveries` | Team-owned rules and durable, deduplicated delivery records |
| Queue displays | Existing `designs`, `playlists`, `channels`, and `screens` | Queue boards are designs deployed through the standard signage pipeline; there is intentionally no `queue_displays` table |

`teams`, `users`, `locations`, and `screens` remain the system-of-record platform entities. Queue Management does not duplicate them under queue-specific names.

## Integrity and lifecycle

- Team-owned queue data is protected by foreign keys and is removed with its owning team.
- Optional reusable relationships such as location, assigned user, counter, priority, and linked ticket use `NULL` on deletion where preserving the queue record is useful.
- Dependent history and pivot rows cascade with their queue parent.
- Queue tables use explicit lifecycle/status fields rather than soft deletes. The module has no recovery workflow that requires deleted queue records to remain queryable.
- Uniqueness constraints protect tenant service codes and ticket prefixes, ticket numbers within their numbering period, appointment references, kiosk/public tokens, notification rules, and notification deduplication keys.

## Index strategy

Single-column foreign-key and status indexes are supplemented by composite indexes shaped around production query paths:

- Atomic next-ticket selection by team, service, status, and age.
- Active ticket lookup by counter and status.
- Live board lookup by team, status, and call time.
- Dashboard/report filtering by team, location, status, and creation time.
- Active service, priority, counter, and kiosk lists scoped by team and location.
- Reverse service-to-counter pivot lookup.
- Appointment reminder scans by team, status, and scheduled time.
- Enabled notification-rule dispatch and team delivery history.

The migration is reversible and uses explicit index names so the schema contract can be tested consistently across supported databases.
