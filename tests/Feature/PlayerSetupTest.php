<?php

namespace Tests\Feature;

use Tests\TestCase;

class PlayerSetupTest extends TestCase
{
    public function test_player_setup_page_is_public(): void
    {
        $this->get(route('player.setup'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('player/setup'));
    }
}
