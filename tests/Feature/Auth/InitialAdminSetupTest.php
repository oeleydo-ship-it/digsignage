<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InitialAdminSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.env' => 'production',
            'setup.initial_admin_key' => 'a-long-private-setup-key-for-the-site',
        ]);
    }

    public function test_first_visit_requires_administrator_setup(): void
    {
        $this->get('/')->assertRedirect(route('register'));
        $this->get('/login')->assertRedirect(route('register'));
        $this->post('/login', [])->assertStatus(503);

        $this->withoutVite()->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/register')
                ->where('initialSetup', true)
                ->where('setupConfigured', true));
    }

    public function test_wrong_setup_key_cannot_create_an_administrator(): void
    {
        $this->post(route('register.store'), $this->registration(['setup_key' => 'wrong-key']))
            ->assertSessionHasErrors('setup_key');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_first_registration_creates_a_platform_administrator(): void
    {
        $this->post(route('register.store'), $this->registration())
            ->assertRedirect();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue($admin->is_platform_admin);
        $this->assertNotNull($admin->currentTeam);
        $this->assertAuthenticatedAs($admin);

        $this->post('/logout')->assertRedirect();
        $this->get('/login')->assertOk();
        $this->get(route('register'))->assertOk();
    }

    public function test_setup_key_is_required_on_the_server(): void
    {
        config(['setup.initial_admin_key' => null]);

        $this->withoutVite()->get(route('register'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/register')
                ->where('initialSetup', true)
                ->where('setupConfigured', false));

        $this->post(route('register.store'), $this->registration())
            ->assertSessionHasErrors('setup_key');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_platform_administrator_skips_first_run_setup(): void
    {
        User::factory()->platformAdmin()->create();

        $this->get('/login')->assertOk();
        $this->withoutVite()->get(route('register'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/register')
                ->where('initialSetup', false));
    }

    /** @param array<string, string> $overrides
     *  @return array<string, string>
     */
    private function registration(array $overrides = []): array
    {
        return [
            'name' => 'Platform Admin',
            'email' => 'admin@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'setup_key' => 'a-long-private-setup-key-for-the-site',
            ...$overrides,
        ];
    }
}
