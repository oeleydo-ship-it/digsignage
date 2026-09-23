# Queue concurrency and reliability

Phase 26 hardens queue commands against concurrent operators, transient database failures, repeated HTTP requests, duplicate queue jobs, and WebSocket reconnects.

## Command guarantees

- Ticket numbering locks the service cursor and retains the period-scoped unique ticket-number constraint.
- Ticket issue requests may provide an idempotency key. Only its SHA-256 digest is stored, and the unique `(team_id, queue_service_id, idempotency_key)` constraint guarantees one ticket for a retried service request.
- Staff, kiosk, and virtual-queue clients generate one key per issue attempt and retain it when the same request is retried.
- The partner API accepts `Idempotency-Key`. A first issue returns `201`; a replay returns the original ticket with `200`.
- Appointment check-in locks the appointment and returns its linked ticket when check-in is repeated.
- Call-next locks the counter and eligible rows, conditionally claims only a waiting ticket, and returns the counter's current ticket when repeated.
- Desk lifecycle, cancellation, transfer, check-in, issue, and call-next transactions retry database concurrency failures up to five times.

## Asynchronous delivery

- Customer-notification and webhook jobs have progressive retry backoff.
- Per-delivery `WithoutOverlapping` locks prevent two workers from delivering the same record simultaneously.
- Completed notification and webhook deliveries short-circuit on replay.
- Webhook delivery records and notification dedupe keys remain the durable source of retry state.

## Real-time recovery

Laravel Echo/Pusher handles transport reconnection. Queue subscriptions additionally detect a successful reconnect and request a fresh REST snapshot. Existing polling remains the fallback while the socket is unavailable, so missed events are reconciled rather than replayed blindly.
