<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PlayerSetupController extends Controller
{
    /**
     * Display the player pairing screen.
     */
    public function __invoke(): Response
    {
        return Inertia::render('player/setup');
    }
}
