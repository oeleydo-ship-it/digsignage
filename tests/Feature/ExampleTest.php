<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login_from_the_home_page(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_are_sent_to_their_organization_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('dashboard', $user->currentTeam));

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('dashboard', $user->currentTeam));
    }
}
