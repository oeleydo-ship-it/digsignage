<?php

namespace App\Http\Controllers\Player;

use App\Actions\Player\AcknowledgeDeviceCommand;
use App\Actions\Player\CompleteDeviceCommand;
use App\Actions\Player\ListPendingDeviceCommands;
use App\Enums\DeviceCommandStatus;
use App\Http\Controllers\Controller;
use App\Models\DeviceCommand;
use App\Models\Screen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerCommandController extends Controller
{
    /**
     * REST fallback for pending device commands.
     */
    public function index(Request $request, ListPendingDeviceCommands $listCommands): JsonResponse
    {
        return response()->json([
            'commands' => $listCommands->handle($this->screen($request)),
        ]);
    }

    /**
     * Authenticate a Reverb private channel for this device.
     */
    public function broadcastingAuth(Request $request): JsonResponse
    {
        $screen = $this->screen($request);
        $channel = (string) $request->input('channel_name');
        $socketId = (string) $request->input('socket_id');

        abort_unless(is_string($screen->device_uuid) && $screen->device_uuid !== '', 403);
        abort_unless($channel === 'private-player.'.$screen->device_uuid, 403);
        abort_unless($socketId !== '', 422);

        $key = (string) config('broadcasting.connections.reverb.key');
        $secret = (string) config('broadcasting.connections.reverb.secret');

        abort_unless($key !== '' && $secret !== '', 503);

        return response()->json([
            'auth' => $key.':'.hash_hmac('sha256', $socketId.':'.$channel, $secret),
        ]);
    }

    /**
     * Mark a command as received by the player.
     */
    public function acknowledge(
        Request $request,
        DeviceCommand $command,
        AcknowledgeDeviceCommand $acknowledge,
    ): JsonResponse {
        $updated = $acknowledge->handle($this->screen($request), $command);

        return response()->json(['ok' => true, 'status' => $updated->status->value]);
    }

    /**
     * Record the player's result for a command.
     */
    public function complete(
        Request $request,
        DeviceCommand $command,
        CompleteDeviceCommand $complete,
    ): JsonResponse {
        $validated = $request->validate([
            'status' => ['required', 'in:completed,failed'],
            'result' => ['nullable', 'array'],
        ]);

        $updated = $complete->handle(
            $this->screen($request),
            $command,
            DeviceCommandStatus::from($validated['status']),
            $validated['result'] ?? null,
        );

        return response()->json(['ok' => true, 'status' => $updated->status->value]);
    }

    protected function screen(Request $request): Screen
    {
        $screen = $request->attributes->get('playerScreen');

        abort_unless($screen instanceof Screen, 401);

        return $screen;
    }
}
