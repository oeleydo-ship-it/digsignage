<?php

namespace App\Http\Controllers\Player;

use App\Actions\Player\BuildPlayerManifest;
use App\Actions\Player\FlushPlayerTelemetry;
use App\Actions\Player\RecordPlayerHeartbeat;
use App\Actions\Player\RecordPlayerPlayback;
use App\Http\Controllers\Controller;
use App\Models\Screen;
use App\Support\PlayerTelemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerSessionController extends Controller
{
    /**
     * Return the authenticated player configuration.
     */
    public function show(Request $request): JsonResponse
    {
        $screen = $this->screen($request);

        return response()->json([
            'screen' => [
                'id' => $screen->id,
                'uuid' => $screen->device_uuid,
                'name' => $screen->name,
                'orientation' => $screen->orientation->value,
                'width' => $screen->resolution_width ?? 1920,
                'height' => $screen->resolution_height ?? 1080,
                'timezone' => $screen->timezone,
                'status' => $screen->status->value,
            ],
            'heartbeat_seconds' => (int) config('signage.player.heartbeat_seconds'),
            'poll_seconds' => (int) config('signage.player.manifest_poll_seconds'),
            'telemetry_batch_max' => (int) config('signage.player.telemetry_batch_max'),
        ]);
    }

    /**
     * Return the current immutable playback manifest.
     */
    public function manifest(Request $request, BuildPlayerManifest $buildManifest): JsonResponse
    {
        return response()->json($buildManifest->handle($this->screen($request)))
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Record a heartbeat from the player.
     */
    public function heartbeat(Request $request, RecordPlayerHeartbeat $recordHeartbeat): JsonResponse
    {
        $validated = $request->validate(PlayerTelemetry::heartbeatRules());
        $recordHeartbeat->handle($this->screen($request), $validated, $request->ip());

        return response()->json(['ok' => true, 'server_time' => now()->toIso8601String()]);
    }

    /**
     * Queue a proof-of-play playback event.
     */
    public function playback(Request $request, RecordPlayerPlayback $recordPlayback): JsonResponse
    {
        $validated = $request->validate(PlayerTelemetry::playbackRules());
        $recordPlayback->handle($this->screen($request), $validated);

        return response()->json(['ok' => true], 201);
    }

    /**
     * Upload telemetry queued while the player was offline.
     */
    public function telemetry(Request $request, FlushPlayerTelemetry $flushTelemetry): JsonResponse
    {
        $max = (int) config('signage.player.telemetry_batch_max');
        $validated = $request->validate([
            'items' => ['required', 'array', 'max:'.$max],
            'items.*.type' => ['required', 'in:heartbeat,playback'],
            'items.*.payload' => ['required', 'array'],
        ]);

        $applied = $flushTelemetry->handle(
            $this->screen($request),
            $validated['items'],
            $request->ip(),
        );

        return response()->json(['ok' => true, ...$applied]);
    }

    protected function screen(Request $request): Screen
    {
        $screen = $request->attributes->get('playerScreen');

        abort_unless($screen instanceof Screen, 401);

        return $screen;
    }
}
