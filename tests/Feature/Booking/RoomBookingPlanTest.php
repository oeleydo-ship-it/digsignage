<?php

namespace Tests\Feature\Booking;

use App\Enums\PlanFeature;
use App\Enums\PlanKey;
use App\Models\CalendarConnection;
use App\Models\MeetingRoom;
use App\Models\Plan;
use App\Models\User;
use App\Support\BillingCatalog;
use App\Widgets\WidgetCatalog;
use App\Widgets\WidgetContext;
use App\Widgets\WidgetRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoomBookingPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function switchRoomBooking(PlanKey $key, bool $enabled): void
    {
        $defaults = BillingCatalog::defaults($key);

        Plan::query()->updateOrCreate(['key' => $key->value], [
            'name' => $defaults['name'],
            'screens' => $defaults['screens'],
            'storage_gb' => $defaults['storage_gb'],
            'users' => $defaults['users'],
            'bandwidth_gb' => $defaults['bandwidth_gb'],
            'price_cents' => $defaults['price_cents'],
            'stripe_price_id' => $defaults['stripe_price_id'],
            'features' => [...$defaults['features'], PlanFeature::RoomBooking->value => $enabled],
        ]);
        BillingCatalog::forgetPlanCache($key->value);
    }

    protected function ownerOnPlan(PlanKey $key): User
    {
        $owner = User::factory()->create();
        $owner->currentTeam->forceFill(['plan_key' => $key])->save();

        return $owner->fresh();
    }

    public function test_room_booking_is_included_unless_a_plan_turns_it_off(): void
    {
        $this->assertTrue(BillingCatalog::allows(PlanKey::Starter, PlanFeature::RoomBooking));

        $this->switchRoomBooking(PlanKey::Starter, false);

        $this->assertFalse(BillingCatalog::allows(PlanKey::Starter, PlanFeature::RoomBooking));
        $this->assertTrue(BillingCatalog::allows(PlanKey::Business, PlanFeature::RoomBooking));
    }

    public function test_the_plans_page_lists_the_toggle(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->withoutVite()
            ->get(route('platform.plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where(
                'features',
                fn ($features) => collect($features)->contains('value', 'room_booking'),
            ));
    }

    public function test_teams_without_the_feature_lose_pages_forms_and_the_menu(): void
    {
        $this->switchRoomBooking(PlanKey::Starter, false);
        $owner = $this->ownerOnPlan(PlanKey::Starter);
        $team = $owner->currentTeam;
        $room = MeetingRoom::factory()->create(['team_id' => $team->id]);

        $this->actingAs($owner)->get(route('bookings.index', $team))->assertForbidden();
        $this->actingAs($owner)->get(route('rooms.index', $team))->assertForbidden();
        $this->actingAs($owner)->get(route('bookings.microsoft.show', $team))->assertForbidden();
        $this->get(route('rooms.public.show', $room->booking_token))->assertForbidden();

        $this->actingAs($owner)
            ->withoutVite()
            ->get(route('dashboard', $team))
            ->assertInertia(fn (Assert $page) => $page->where('bookingPermissions', null));
    }

    public function test_room_widgets_disappear_and_stop_resolving_without_the_feature(): void
    {
        $this->switchRoomBooking(PlanKey::Starter, false);
        $owner = $this->ownerOnPlan(PlanKey::Starter);
        $team = $owner->currentTeam;
        $room = MeetingRoom::factory()->create(['team_id' => $team->id]);
        $registry = app(WidgetRegistry::class);

        $keys = array_column($registry->toArrayForTeam($team), 'key');
        $this->assertNotContains('room_status', $keys);
        $this->assertNotContains('room_status', array_column($registry->designerElementTypes($team), 'value'));

        $data = WidgetCatalog::roomStatus(
            ['room_id' => (string) $room->id],
            new WidgetContext($team, 'UTC', CarbonImmutable::now()),
        );
        $this->assertSame('Room booking is not included in this plan.', $data['error']);
        $this->assertArrayNotHasKey('bookings', $data);
    }

    public function test_microsoft_sync_skips_teams_without_the_feature(): void
    {
        Http::fake();
        $this->switchRoomBooking(PlanKey::Starter, false);
        $owner = $this->ownerOnPlan(PlanKey::Starter);
        $connection = CalendarConnection::factory()->create(['team_id' => $owner->currentTeam->id]);
        MeetingRoom::factory()->linkedToMicrosoft($connection)->create();

        $this->artisan('bookings:sync-microsoft')->assertSuccessful();

        Http::assertNothingSent();
    }
}
