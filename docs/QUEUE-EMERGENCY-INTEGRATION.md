# Queue emergency integration

Phase 28 integrates queue displays with the existing emergency-broadcast system without creating a separate playback path.

## Playback contract

- An active emergency is rendered as a full-screen player overlay above every normal signage layer, including queue boards, media, advanced layouts, and fallbacks.
- Queue voice announcements are stopped when an emergency starts and new queue calls are not spoken while the emergency is active.
- Before an emergency manifest is activated, the player persists the current verified, non-emergency manifest as a recovery checkpoint.
- A matching emergency-stop command restores that checkpoint immediately, then fetches and activates the latest server manifest. This avoids a blank transition while ensuring calls and queue positions changed during the emergency are current.
- The checkpoint survives a player reload. It is removed after normal playback is successfully activated.
- A stop command for an older emergency cannot dismiss a newer emergency currently displayed on the same screen.

## Failure behavior

If the network is unavailable when an emergency ends, the player restores the last verified signage manifest and retains normal offline playback. Queue audio remains disabled until the player is online and reconciliation has completed. The next successful manifest sync replaces the restored snapshot with current queue data.

## Verification

Automated coverage verifies command ordering, checkpoint persistence and restoration, queue-audio suppression, and the server flow where queue state changes during an emergency and the first post-stop manifest contains the latest called ticket.
