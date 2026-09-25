<?php

namespace Tests\Feature\Template;

use App\Enums\TeamRole;
use App\Enums\TemplateCategory;
use App\Enums\TemplateStatus;
use App\Models\Design;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;
use App\Support\CatalogTemplateLibrary;
use App\Support\DesignDocument;
use Database\Seeders\CatalogTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_can_create_and_instantiate_team_templates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('templates.store', $user->currentTeam), [
                'name' => 'Lobby Welcome',
                'category' => TemplateCategory::Corporate->value,
                'width' => 1920,
                'height' => 1080,
            ])
            ->assertRedirect();

        $template = Template::query()->firstOrFail();
        $this->assertSame($user->currentTeam->id, $template->team_id);
        $this->assertSame(TemplateStatus::Draft, $template->status);

        $this->actingAs($user)
            ->post(route('templates.instantiate', [$user->currentTeam, $template]))
            ->assertRedirect();

        $this->assertDatabaseHas('designs', [
            'team_id' => $user->currentTeam->id,
            'name' => 'Lobby Welcome',
        ]);
    }

    public function test_user_cannot_update_another_teams_template(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $template = Template::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'name' => 'Secret layout',
        ]);

        $this->actingAs($userA)
            ->patch(route('templates.update', [$userA->currentTeam, $template]), [
                'name' => 'Stolen',
                'category' => TemplateCategory::Corporate->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'name' => 'Secret layout',
        ]);
    }

    public function test_published_platform_templates_are_visible_to_other_teams(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->platform()->published()->create([
            'name' => 'Retail Menu',
            'category' => TemplateCategory::Retail,
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('templates.index', $user->currentTeam).'?search=Retail%20Menu')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('templates/index')
                ->has('templates.data', 1)
                ->where('templates.data.0.name', 'Retail Menu')
                ->where('templates.data.0.platform', true)
                ->has('featured'));

        $this->assertTrue($template->isPlatform());
    }

    public function test_unpublished_platform_templates_are_hidden_from_tenants(): void
    {
        $user = User::factory()->create();
        Template::factory()->platform()->create([
            'name' => 'Hidden catalog',
            'status' => TemplateStatus::Draft,
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('templates.index', $user->currentTeam).'?search=Hidden%20catalog')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('templates/index')
                ->has('templates.data', 0));
    }

    public function test_opening_templates_installs_missing_catalog_without_touching_team_templates(): void
    {
        $user = User::factory()->create();
        $teamTemplate = Template::factory()->create(['team_id' => $user->currentTeam->id]);
        $expected = count(CatalogTemplateLibrary::definitions());

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('templates.index', $user->currentTeam).'?scope=platform')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('templates/index')
                ->where('templates.total', $expected)
                ->has('featured', count(CatalogTemplateLibrary::featuredKeys())));

        $this->assertSame($expected, Template::query()->whereNull('team_id')->count());
        $this->assertSame(TemplateStatus::Published, Template::query()->where('slug', 'lobby-welcome')->firstOrFail()->status);
        $this->assertNull(Template::query()->where('slug', 'lobby-welcome')->firstOrFail()->thumbnail_path);
        $this->assertNotNull($teamTemplate->fresh());

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('templates.index', $user->currentTeam))
            ->assertOk();

        $this->assertSame($expected, Template::query()->whereNull('team_id')->count());
    }

    public function test_catalog_installation_preserves_an_existing_platform_layout(): void
    {
        $user = User::factory()->create();
        $existing = Template::factory()->platform()->published()->create([
            'slug' => 'lobby-welcome',
            'name' => 'Customized lobby',
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('templates.index', $user->currentTeam))
            ->assertOk();

        $this->assertSame('Customized lobby', $existing->fresh()->name);
        $this->assertSame(
            count(CatalogTemplateLibrary::definitions()),
            Template::query()->whereNull('team_id')->count(),
        );
        $this->assertSame(1, Template::query()->where('slug', 'lobby-welcome')->count());
    }

    public function test_designer_omits_inline_gallery_and_template_library_installs_catalog_on_visit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('designs.index', $user->currentTeam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('designs/index')
                ->missing('starterTemplates'));

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('templates.index', $user->currentTeam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('templates/index'));

        $this->assertSame(
            count(CatalogTemplateLibrary::definitions()),
            Template::query()->whereNull('team_id')->count(),
        );
    }

    public function test_platform_admins_can_create_catalog_templates(): void
    {
        $user = User::factory()->platformAdmin()->create();

        $this->actingAs($user)
            ->post(route('templates.store', $user->currentTeam), [
                'name' => 'Emergency Alert',
                'category' => TemplateCategory::Emergency->value,
                'platform' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('templates', [
            'name' => 'Emergency Alert',
            'team_id' => null,
            'category' => TemplateCategory::Emergency->value,
        ]);
    }

    public function test_members_cannot_create_templates(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->post(route('templates.store', $team), [
                'name' => 'No Access',
                'category' => TemplateCategory::Corporate->value,
            ])
            ->assertForbidden();
    }

    public function test_designs_can_be_saved_as_team_templates(): void
    {
        $user = User::factory()->create();
        $design = Design::factory()->create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Morning Board',
            'document' => DesignDocument::blank(1080, 1920),
        ]);

        $this->actingAs($user)
            ->post(route('templates.from-design', [$user->currentTeam, $design]), [
                'name' => 'Morning Board template',
                'category' => TemplateCategory::Announcements->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('templates', [
            'team_id' => $user->currentTeam->id,
            'source_design_id' => $design->id,
            'name' => 'Morning Board template',
        ]);
    }

    public function test_user_cannot_save_another_teams_design_as_a_template(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $design = Design::factory()->create([
            'team_id' => $userB->currentTeam->id,
        ]);

        $this->actingAs($userA)
            ->post(route('templates.from-design', [$userA->currentTeam, $design]), [
                'name' => 'Stolen template',
                'category' => TemplateCategory::Corporate->value,
            ])
            ->assertForbidden();
    }

    public function test_catalog_templates_duplicate_into_the_current_team(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->platform()->published()->create([
            'name' => 'Lobby Catalog',
        ]);

        $this->actingAs($user)
            ->post(route('templates.duplicate', [$user->currentTeam, $template]))
            ->assertRedirect();

        $this->assertDatabaseHas('templates', [
            'team_id' => $user->currentTeam->id,
            'name' => 'Lobby Catalog copy',
            'status' => TemplateStatus::Draft->value,
        ]);
        $this->assertTrue($template->fresh()->isPlatform());
    }

    public function test_creating_a_template_stores_a_thumbnail(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required to generate template thumbnails.');
        }

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('templates.store', $user->currentTeam), [
                'name' => 'Thumb Lobby',
                'category' => TemplateCategory::Corporate->value,
            ])
            ->assertRedirect();

        $template = Template::query()->firstOrFail();
        $this->assertNotNull($template->thumbnail_path);

        $this->actingAs($user)
            ->get(route('templates.thumbnail', [$user->currentTeam, $template]))
            ->assertOk();
    }

    public function test_ready_made_catalog_templates_are_published_and_visible(): void
    {
        $this->seed(CatalogTemplateSeeder::class);

        $user = User::factory()->create();
        $expected = count(CatalogTemplateLibrary::definitions());
        $lobby = Template::query()
            ->whereNull('team_id')
            ->where('slug', 'lobby-welcome')
            ->firstOrFail();

        $this->assertSame(TemplateStatus::Published, $lobby->status);
        $this->assertNotEmpty($lobby->document['elements'] ?? []);
        $this->assertSame($expected, Template::query()->whereNull('team_id')->count());

        if (function_exists('imagecreatetruecolor')) {
            $this->assertNotNull($lobby->thumbnail_path);
            $this->assertGreaterThan(4096, Storage::disk((string) config('media.disk'))->size($lobby->thumbnail_path));
        }

        $ticker = collect($lobby->document['elements'])->firstWhere('type', 'ticker');
        $this->assertIsArray($ticker);
        $this->assertNotEmpty($ticker['props']['text'] ?? null);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('templates.index', $user->currentTeam).'?scope=platform')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('templates/index')
                ->has('templates.data', min($expected, 48))
                ->where('templates.total', $expected)
                ->where('templates.data.0.platform', true)
                ->has('featured', count(CatalogTemplateLibrary::featuredKeys())));

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('designs.index', $user->currentTeam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('designs/index')
                ->missing('starterTemplates'));
    }

    public function test_using_a_catalog_template_copies_widget_props_into_a_team_design(): void
    {
        $this->seed(CatalogTemplateSeeder::class);

        $user = User::factory()->create();
        $template = Template::query()
            ->whereNull('team_id')
            ->where('slug', 'weather-world-clock')
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('templates.instantiate', [$user->currentTeam, $template]))
            ->assertRedirect();

        $design = Design::query()
            ->where('team_id', $user->currentTeam->id)
            ->where('name', $template->name)
            ->firstOrFail();

        $weather = collect($design->document['elements'])->firstWhere('type', 'weather');
        $this->assertIsArray($weather);
        $this->assertSame('Dubai', $weather['props']['location'] ?? null);
        $this->assertSame(
            $template->normalizedDocument()['elements'],
            $design->normalizedDocument()['elements'],
        );
    }

    public function test_tenants_cannot_edit_platform_catalog_templates(): void
    {
        $this->seed(CatalogTemplateSeeder::class);

        $user = User::factory()->create();
        $template = Template::query()
            ->whereNull('team_id')
            ->where('slug', 'lobby-welcome')
            ->firstOrFail();

        $this->actingAs($user)
            ->patch(route('templates.update', [$user->currentTeam, $template]), [
                'name' => 'Hijacked lobby',
                'category' => TemplateCategory::Corporate->value,
            ])
            ->assertForbidden();

        $this->assertSame('Lobby welcome', $template->fresh()->name);
    }

    public function test_platform_admins_can_edit_catalog_templates(): void
    {
        $this->seed(CatalogTemplateSeeder::class);

        $user = User::factory()->platformAdmin()->create();
        $template = Template::query()
            ->whereNull('team_id')
            ->where('slug', 'lobby-welcome')
            ->firstOrFail();

        $this->actingAs($user)
            ->patch(route('templates.update', [$user->currentTeam, $template]), [
                'name' => 'Lobby welcome',
                'description' => 'Updated catalog copy',
                'category' => TemplateCategory::Corporate->value,
            ])
            ->assertRedirect();

        $this->assertSame('Updated catalog copy', $template->fresh()->description);
    }

    public function test_catalog_seeder_is_idempotent_by_slug(): void
    {
        $this->seed(CatalogTemplateSeeder::class);
        $this->seed(CatalogTemplateSeeder::class);

        $this->assertSame(
            count(CatalogTemplateLibrary::definitions()),
            Template::query()->whereNull('team_id')->count(),
        );
        $this->assertSame(1, Template::query()->where('slug', 'lobby-welcome')->count());
    }

    public function test_portrait_catalog_templates_use_vertical_dimensions(): void
    {
        $this->seed(CatalogTemplateSeeder::class);

        $portrait = Template::query()
            ->whereNull('team_id')
            ->where('slug', 'portrait-lobby-welcome')
            ->firstOrFail();

        $this->assertSame(1080, $portrait->width);
        $this->assertSame(1920, $portrait->height);
        $this->assertGreaterThan($portrait->width, $portrait->height);

        $elements = collect($portrait->document['elements']);
        $this->assertNotNull($elements->firstWhere('type', 'clock'));
        $this->assertNotNull($elements->firstWhere('type', 'ticker'));

        $hdMenu = collect(CatalogTemplateLibrary::definitions())
            ->firstWhere('key', 'portrait-hd-menu');
        $this->assertIsArray($hdMenu);
        $this->assertSame(720, $hdMenu['document']['width']);
        $this->assertSame(1280, $hdMenu['document']['height']);
        $this->assertNotNull(collect($hdMenu['document']['elements'])->firstWhere('type', 'menu_board'));

        $fourByFive = collect(CatalogTemplateLibrary::definitions())
            ->firstWhere('key', 'portrait-4x5-retail');
        $this->assertIsArray($fourByFive);
        $this->assertSame(1080, $fourByFive['document']['width']);
        $this->assertSame(1350, $fourByFive['document']['height']);
        $this->assertNotNull(collect($fourByFive['document']['elements'])->firstWhere('type', 'countdown'));
    }

    public function test_every_catalog_template_includes_a_photo(): void
    {
        foreach (CatalogTemplateLibrary::definitions() as $definition) {
            $images = collect($definition['document']['elements'])->where('type', 'image');

            $this->assertGreaterThan(
                0,
                $images->count(),
                $definition['key'].' should include at least one image element',
            );
        }
    }

    public function test_breakfast_lunch_menu_boards_fill_the_canvas(): void
    {
        $definition = collect(CatalogTemplateLibrary::definitions())
            ->firstWhere('key', 'menu-breakfast-lunch');

        $this->assertIsArray($definition);
        $document = $definition['document'];
        $this->assertSame(1920, $document['width']);
        $this->assertSame(1080, $document['height']);

        $boards = collect($document['elements'])->where('type', 'menu_board')->values();
        $this->assertCount(2, $boards);

        $this->assertSame(0, (int) $boards[0]['x']);
        $this->assertSame(960, (int) $boards[1]['x']);
        $this->assertSame(1920, (int) ($boards[0]['width'] + $boards[1]['width']));
        $this->assertSame(1080, (int) ($boards[0]['y'] + $boards[0]['height']));
        $this->assertSame(1080, (int) ($boards[1]['y'] + $boards[1]['height']));
        $this->assertGreaterThan(0, (int) $boards[0]['y']);
        $this->assertNotNull(collect($document['elements'])->firstWhere('type', 'image'));
    }

    public function test_catalog_includes_square_templates_and_orientation_filter(): void
    {
        $this->seed(CatalogTemplateSeeder::class);

        $square = Template::query()
            ->whereNull('team_id')
            ->where('slug', 'square-brand-spotlight')
            ->firstOrFail();

        $this->assertSame(1080, $square->width);
        $this->assertSame(1080, $square->height);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('templates.index', $user->currentTeam).'?scope=platform&orientation=square')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('templates/index')
                ->where('filters.orientation', 'square')
                ->where('templates.total', Template::query()
                    ->whereNull('team_id')
                    ->whereColumn('width', '=', 'height')
                    ->count()));
    }

    public function test_regenerate_thumbnails_command_updates_platform_templates(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required to generate template thumbnails.');
        }

        $this->seed(CatalogTemplateSeeder::class);

        $template = Template::query()
            ->whereNull('team_id')
            ->where('slug', 'now-hiring')
            ->firstOrFail();

        $original = $template->thumbnail_path;
        $template->forceFill(['thumbnail_path' => null])->save();

        $this->artisan('templates:regenerate-thumbnails', ['--platform' => true])
            ->assertSuccessful();

        $template->refresh();
        $this->assertNotNull($template->thumbnail_path);
        $this->assertNotSame($original, $template->thumbnail_path);
    }
}
