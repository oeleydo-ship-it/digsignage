<?php

namespace Tests\Feature\Platform;

use App\Actions\Media\UploadMediaFiles;
use App\Enums\MediaProcessingStatus;
use App\Enums\MediaType;
use App\Enums\PlatformAuditAction;
use App\Enums\StorageProvider;
use App\Enums\TeamRole;
use App\Models\Media;
use App\Models\PlatformAudit;
use App\Models\StorageDisk;
use App\Models\Team;
use App\Models\User;
use App\Services\Media\ProcessMediaFile;
use App\Support\StorageDisks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorageBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_users_cannot_manage_storage_backends(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('platform.storage.index'))
            ->assertForbidden();
    }

    public function test_platform_admins_see_the_backends_and_provider_catalog(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        StorageDisk::factory()->wasabi()->default()->create(['name' => 'Wasabi primary']);

        $this->actingAs($admin)
            ->withoutVite()
            ->get(route('platform.storage.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/storage/index')
                ->has('disks', 1)
                ->where('disks.0.name', 'Wasabi primary')
                ->where('disks.0.provider', 'wasabi')
                ->where('disks.0.endpoint', 'https://s3.us-east-1.wasabisys.com')
                ->where('disks.0.is_default', true)
                ->has('providers', count(StorageProvider::cases()))
                ->has('organizations'));
    }

    public function test_a_wasabi_backend_can_be_created_and_becomes_the_default(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $existing = StorageDisk::factory()->local()->default()->create();

        $this->actingAs($admin)
            ->post(route('platform.storage.store'), [
                'name' => 'Wasabi EU',
                'provider' => 'wasabi',
                'bucket' => 'signage-eu',
                'region' => 'eu-central-1',
                'access_key' => 'AKIAWASABI',
                'secret_key' => 'wasabi-secret',
                'visibility' => 'private',
                'is_default' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $disk = StorageDisk::query()->where('name', 'Wasabi EU')->sole();

        $this->assertTrue($disk->is_default);
        $this->assertSame(StorageProvider::Wasabi, $disk->provider);
        $this->assertSame('wasabi-secret', $disk->secret_key);
        $this->assertFalse($existing->refresh()->is_default);

        // Credentials are encrypted at rest.
        $raw = (string) DB::table('storage_disks')->where('id', $disk->id)->value('secret_key');
        $this->assertNotSame('wasabi-secret', $raw);

        $this->assertDatabaseHas('platform_audits', [
            'action' => PlatformAuditAction::StorageDiskCreated->value,
            'resource_type' => 'storage_disk',
            'resource_id' => $disk->id,
        ]);
    }

    public function test_s3_backends_require_a_bucket_and_credentials(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->from(route('platform.storage.index'))
            ->post(route('platform.storage.store'), [
                'name' => 'Broken S3',
                'provider' => 's3',
                'visibility' => 'private',
            ])
            ->assertSessionHasErrors(['bucket', 'region', 'access_key', 'secret_key']);

        $this->assertDatabaseCount('storage_disks', 0);
    }

    public function test_providers_without_a_derivable_endpoint_require_one(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->from(route('platform.storage.index'))
            ->post(route('platform.storage.store'), [
                'name' => 'R2',
                'provider' => 'cloudflare_r2',
                'bucket' => 'signage',
                'region' => 'auto',
                'access_key' => 'key',
                'secret_key' => 'secret',
                'visibility' => 'private',
            ])
            ->assertSessionHasErrors('endpoint');
    }

    public function test_updating_without_credentials_keeps_the_stored_secret(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $disk = StorageDisk::factory()->wasabi()->create(['name' => 'Wasabi']);

        $this->actingAs($admin)
            ->patch(route('platform.storage.update', $disk), [
                'name' => 'Wasabi renamed',
                'provider' => 'wasabi',
                'bucket' => 'digsignage-media',
                'region' => 'us-east-1',
                'visibility' => 'private',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $disk->refresh();

        $this->assertSame('Wasabi renamed', $disk->name);
        $this->assertSame('secret-example', $disk->secret_key);
    }

    public function test_a_connection_test_records_its_outcome(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $disk = StorageDisk::factory()->local(storage_path('app/private/test-backend'))->create();

        $this->actingAs($admin)
            ->post(route('platform.storage.test', $disk))
            ->assertRedirect();

        $disk->refresh();

        $this->assertNotNull($disk->last_tested_at);
        $this->assertNull($disk->last_test_error);
        $this->assertDatabaseHas('platform_audits', [
            'action' => PlatformAuditAction::StorageDiskTested->value,
            'resource_id' => $disk->id,
        ]);

        // The probe object is cleaned up after the round trip.
        $this->assertEmpty(Storage::disk($disk->diskName())->allFiles('digsignage-connection-test'));
    }

    public function test_a_backend_holding_media_cannot_be_deleted(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $disk = StorageDisk::factory()->local()->create();
        $team = Team::factory()->create();

        Media::factory()->for($team)->create([
            'storage_disk' => $disk->diskName(),
            'type' => MediaType::Image,
        ]);

        $this->actingAs($admin)
            ->delete(route('platform.storage.destroy', $disk))
            ->assertRedirect();

        $this->assertDatabaseHas('storage_disks', ['id' => $disk->id]);
    }

    public function test_an_empty_backend_can_be_deleted(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $disk = StorageDisk::factory()->local()->create();

        $this->actingAs($admin)
            ->delete(route('platform.storage.destroy', $disk))
            ->assertRedirect();

        $this->assertDatabaseMissing('storage_disks', ['id' => $disk->id]);
    }

    public function test_an_organization_can_be_assigned_a_backend(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $team = Team::factory()->create();
        $disk = StorageDisk::factory()->local()->create();

        $this->actingAs($admin)
            ->patch(route('platform.storage.assign', $team), ['storage_disk_id' => $disk->id])
            ->assertRedirect();

        $this->assertSame($disk->id, $team->refresh()->storage_disk_id);

        $this->actingAs($admin)
            ->patch(route('platform.storage.assign', $team), ['storage_disk_id' => null])
            ->assertRedirect();

        $this->assertNull($team->refresh()->storage_disk_id);
    }

    public function test_an_organization_cannot_be_assigned_another_organizations_backend(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $team = Team::factory()->create();
        $other = Team::factory()->create();
        $disk = StorageDisk::factory()->local()->create(['team_id' => $other->id]);

        $this->actingAs($admin)
            ->patch(route('platform.storage.assign', $team), ['storage_disk_id' => $disk->id])
            ->assertNotFound();

        $this->assertNull($team->refresh()->storage_disk_id);
    }

    public function test_uploads_are_written_to_the_assigned_backend(): void
    {
        $root = storage_path('app/private/assigned-backend');
        $disk = StorageDisk::factory()->local($root)->create();
        $team = Team::factory()->create(['storage_disk_id' => $disk->id]);
        $user = User::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

        $media = app(UploadMediaFiles::class)->handle(
            $user,
            $team,
            [UploadedFile::fake()->image('poster.jpg', 40, 30)],
            null,
        )[0];

        $this->assertSame($disk->diskName(), $media->storage_disk);
        $this->assertSame($disk->diskName(), $media->disk());
        $this->assertTrue(Storage::disk($disk->diskName())->exists((string) $media->storage_path));
        $this->assertFileExists($root.'/'.$media->storage_path);
    }

    public function test_uploads_fall_back_to_the_platform_default_then_to_config(): void
    {
        $disks = app(StorageDisks::class);
        $team = Team::factory()->create();

        $this->assertSame(config('media.disk'), $disks->forTeam($team));

        $default = StorageDisk::factory()->local()->default()->create();
        $disks->flush();

        $this->assertSame($default->diskName(), $disks->forTeam($team));
    }

    public function test_media_keeps_reading_from_the_backend_it_was_written_to(): void
    {
        $first = StorageDisk::factory()->local(storage_path('app/private/backend-one'))->create();
        $second = StorageDisk::factory()->local(storage_path('app/private/backend-two'))->default()->create();
        $team = Team::factory()->create(['storage_disk_id' => $second->id]);

        $media = Media::factory()->for($team)->create(['storage_disk' => $first->diskName()]);

        $this->assertSame($first->diskName(), $media->disk());
    }

    public function test_resolving_the_backend_of_a_missing_file_does_not_recurse(): void
    {
        Storage::fake('media');

        $media = Media::factory()->create(['storage_path' => 'missing.bin']);

        $this->assertSame('media', $media->disk());

        app(ProcessMediaFile::class)->handle($media);

        $this->assertSame(
            MediaProcessingStatus::Failed,
            $media->refresh()->processing_status,
        );
    }

    public function test_a_deleted_backend_falls_back_instead_of_throwing(): void
    {
        $this->assertSame(
            config('media.disk'),
            app(StorageDisks::class)->resolveStored(StorageDisk::NAME_PREFIX.'999999'),
        );
    }

    public function test_audit_actions_expose_labels(): void
    {
        foreach (PlatformAuditAction::cases() as $action) {
            $this->assertNotSame('', $action->label());
        }

        $this->assertSame(0, PlatformAudit::query()->whereNull('action')->count());
    }
}
