# Queue Management testing

Phase 30 closes the Queue Management specification with automated coverage at action, HTTP, API, player, and browser-module boundaries.

## Required scenario matrix

| Requirement | Primary coverage |
| --- | --- |
| Ticket creation and numbering | `QueueTicketTest`, `QueueKioskTest`, `QueueVirtualTest`, `QueueApiTest` |
| Call next and counter assignment | `QueueCounterTest` |
| Concurrent call next | `QueueCounterTest::test_two_workers_with_the_same_stale_snapshot_claim_different_tickets` |
| Priority and starvation | `QueuePriorityTest` |
| Transfers and no-shows | `QueueTicketTransferTest`, `QueueCounterTest` |
| Permissions and tenant isolation | `QueueAuthorizationTest`, queue feature suites, `QueueApiTest` |
| Appointments | `QueueAppointmentTest` |
| Queue events and realtime displays | `QueueRealtimeTest`, `QueueDashboardTest`, `queue-echo.test.ts`, `player-echo.test.ts` |
| API authentication and idempotency | `QueueApiTest` |
| Webhooks | `QueueWebhookTest` |
| Offline and emergency recovery | player offline/command tests and `QueueRealtimeTest` |
| Plan entitlements | `QueuePlanControlsTest` |
| High-volume behavior | `QueueLoadTest` |

## Concurrency invariant

The race regression gives two simulated workers the same pre-claim ranked snapshot containing A101 followed by A102. Both attempt the same atomic conditional claim operation. The first worker receives A101; the second loses that conditional update and continues to A102. Assertions verify two distinct serving tickets and counter assignments.

This deterministic test complements the transaction and lock behavior used by production databases while remaining reliable on SQLite, where row locks are not representative.

## Realtime integration

The call-next integration test starts from a cached player manifest, calls the real counter HTTP endpoint, verifies the customer-safe queue event targets the service and relevant private player channel, then polls the player manifest and confirms the new ticket and counter are present under a new manifest version.

## Load guard

The load test builds five services, five counters, and 1,000 waiting tickets. It verifies the complete operations snapshot while enforcing a bounded query count, guarding against ticket-volume-dependent N+1 regressions without relying on fragile wall-clock timing.

## Quality gates

Run the backend suite with the PHP 8.4 runtime and enough memory for fake upload tests, then run frontend lint, type checking, tests, and the production build. The repository's normal CI commands remain the source of truth.
