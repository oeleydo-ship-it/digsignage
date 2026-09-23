# Queue display offline resilience

Phase 27 uses the signage player's existing verified-manifest and asset-cache pipeline to keep queue boards useful during temporary network interruption.

## Last-known queue state

- A successfully verified player manifest is activated atomically and stored in browser storage.
- Queue widget settings and resolved queue data are part of that manifest, so the last known now-serving, waiting, counter, and metric values remain available offline.
- Referenced media is cached and checksum-verified before a new manifest replaces the current one.
- A failed or incomplete update is discarded while the last verified manifest keeps playing.
- The player service worker caches the loaded player shell and build assets for recovery during a temporary outage.

## Connection states

The player always exposes one of three states:

- `ONLINE`: REST reconciliation completed and the realtime connection is healthy, or polling is the active transport.
- `RECONNECTING`: the browser has network access but session, manifest, or WebSocket reconciliation is still in progress.
- `OFFLINE`: the browser reports no network connection; the last verified manifest remains on screen.

## Reconciliation and announcements

On browser or WebSocket reconnection the player:

1. Marks itself as reconnecting and clears queued/in-progress voice announcements.
2. Re-establishes the authenticated player session.
3. Flushes durable offline telemetry.
4. requests and verifies the latest manifest and queue snapshot.
5. resumes realtime events and marks itself online.

Queue events received before reconciliation finishes may refresh data but cannot enqueue speech. The restored snapshot is never converted into an announcement, preventing stale calls from being replayed after an outage.
