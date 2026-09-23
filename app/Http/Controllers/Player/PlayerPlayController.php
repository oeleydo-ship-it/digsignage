<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PlayerPlayController extends Controller
{
    /**
     * Dedicated fullscreen player application.
     */
    public function __invoke(): Response
    {
        return Inertia::render('player/play', [
            'heartbeatSeconds' => (int) config('signage.player.heartbeat_seconds'),
            'pollSeconds' => (int) config('signage.player.manifest_poll_seconds'),
            'commandPollSeconds' => (int) config('signage.player.command_poll_seconds'),
            'reverb' => [
                'enabled' => config('broadcasting.default') === 'reverb'
                    && filled(config('broadcasting.connections.reverb.key')),
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host') ?: 'localhost',
                'port' => (int) (config('broadcasting.connections.reverb.options.port') ?: 8080),
                'scheme' => config('broadcasting.connections.reverb.options.scheme') ?: 'http',
            ],
        ]);
    }
}
