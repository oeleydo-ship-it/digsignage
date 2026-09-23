<?php

namespace Tests\Feature\Media;

use App\Enums\MediaProcessingStatus;
use App\Enums\MediaType;
use App\Enums\TeamRole;
use App\Http\Requests\Media\UploadMediaRequest;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_files_includes_images_inside_folders(): void
    {
        $user = User::factory()->create();
        $folder = MediaFolder::factory()->create(['team_id' => $user->currentTeam->id]);
        Media::factory()->create(['team_id' => $user->currentTeam->id, 'folder_id' => $folder->id]);
        Media::factory()->create(['team_id' => $user->currentTeam->id, 'folder_id' => null]);
        $this->actingAs($user)->get(route('media.index', [$user->currentTeam, 'folder_id' => '']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('media.data', 2)->has('uploadLimits'));
    }

    public function test_owners_can_upload_images(): void
    {
        Storage::fake('media');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('lobby.png', 64, 64);

        $this->actingAs($user)
            ->post(route('media.store', $user->currentTeam), [
                'files' => [$file],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('media', [
            'team_id' => $user->currentTeam->id,
            'type' => MediaType::Image->value,
            'processing_status' => MediaProcessingStatus::Ready->value,
        ]);
    }

    public function test_missing_upload_directory_has_an_actionable_error(): void
    {
        $user = User::factory()->create();
        $file = new UploadedFile('', 'image.png', 'image/png', UPLOAD_ERR_NO_TMP_DIR, true);
        $this->actingAs($user)->post(route('media.store', $user->currentTeam), ['files' => [$file]], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.0');
        $request = new UploadMediaRequest;
        $request->files->set('files', [$file]);
        $this->assertStringContainsString('temporary upload files', $request->messages()['files.0.uploaded']);
    }

    public function test_owners_can_upload_mp4_and_other_videos(): void
    {
        Storage::fake('media');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('lobby.mp4', 256, 'video/mp4');

        $this->actingAs($user)
            ->post(route('media.store', $user->currentTeam), [
                'files' => [$file],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('media', [
            'team_id' => $user->currentTeam->id,
            'type' => MediaType::Video->value,
            'original_filename' => 'lobby.mp4',
        ]);
    }

    public function test_owners_can_store_external_video_urls(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('media.external', $user->currentTeam), [
                'name' => 'Promo loop',
                'type' => MediaType::Video->value,
                'external_url' => 'https://cdn.example.com/promo.webm',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('media', [
            'team_id' => $user->currentTeam->id,
            'name' => 'Promo loop',
            'type' => MediaType::Video->value,
            'external_url' => 'https://cdn.example.com/promo.webm',
        ]);
    }

    public function test_external_mp4_url_is_classified_as_video(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('media.external', $user->currentTeam), [
                'name' => 'Direct MP4',
                'type' => MediaType::Url->value,
                'external_url' => 'https://cdn.example.com/clips/welcome.mp4',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('media', [
            'team_id' => $user->currentTeam->id,
            'name' => 'Direct MP4',
            'type' => MediaType::Video->value,
            'external_url' => 'https://cdn.example.com/clips/welcome.mp4',
        ]);
    }

    public function test_owners_can_store_external_urls(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('media.external', $user->currentTeam), [
                'name' => 'Promo page',
                'type' => MediaType::Url->value,
                'external_url' => 'https://example.com/promo',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('media', [
            'team_id' => $user->currentTeam->id,
            'name' => 'Promo page',
            'type' => MediaType::Url->value,
            'external_url' => 'https://example.com/promo',
        ]);
    }

    public function test_designer_upload_returns_an_image_that_can_be_loaded(): void
    {
        Storage::fake('media');
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('media.store', $user->currentTeam), [
            'files' => [UploadedFile::fake()->image('designer.png', 120, 80)],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('media.0.type', 'image')
            ->assertJsonPath('media.0.has_file', true)
            ->assertJsonPath('media.0.width', 120)
            ->assertJsonPath('media.0.height', 80);

        $this->get(route('media.file', [$user->currentTeam, $response->json('media.0.id')]))->assertOk();
    }

    public function test_designer_upload_returns_validation_errors_as_json(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('media.store', $user->currentTeam), [], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('files');
    }

    public function test_user_cannot_view_another_teams_media_file(): void
    {
        Storage::fake('media');

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $media = Media::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'storage_path' => $userB->currentTeam->id.'/originals/secret.png',
        ]);
        Storage::disk('media')->put($media->storage_path, 'secret');

        $this->actingAs($userA)
            ->get(route('media.file', [$userA->currentTeam, $media]))
            ->assertForbidden();
    }

    public function test_user_cannot_upload_into_another_teams_folder(): void
    {
        Storage::fake('media');

        $user = User::factory()->create();
        $foreignFolder = MediaFolder::factory()->create();
        $file = UploadedFile::fake()->image('lobby.png', 32, 32);

        $this->actingAs($user)
            ->post(route('media.store', $user->currentTeam), [
                'files' => [$file],
                'folder_id' => $foreignFolder->id,
            ])
            ->assertSessionHasErrors('folder_id');
    }

    public function test_members_cannot_upload_media(): void
    {
        Storage::fake('media');

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->post(route('media.store', $team), [
                'files' => [UploadedFile::fake()->image('nope.png')],
            ])
            ->assertForbidden();
    }

    public function test_members_can_view_the_library(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);
        Media::factory()->create(['team_id' => $team->id, 'name' => 'Lobby still']);

        $this->actingAs($member)
            ->withoutVite()
            ->get(route('media.index', $team))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('media/index')
                ->has('media.data', 1));
    }

    public function test_folders_cannot_be_deleted_while_they_contain_media(): void
    {
        $user = User::factory()->create();
        $folder = MediaFolder::factory()->create(['team_id' => $user->currentTeam->id]);
        Media::factory()->create([
            'team_id' => $user->currentTeam->id,
            'folder_id' => $folder->id,
        ]);

        $this->actingAs($user)
            ->delete(route('media-folders.destroy', [$user->currentTeam, $folder]))
            ->assertSessionHasErrors('folder');
    }

    public function test_media_can_be_archived_duplicated_and_deleted(): void
    {
        Storage::fake('media');

        $user = User::factory()->create();
        $media = Media::factory()->create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Hero',
            'storage_path' => $user->currentTeam->id.'/originals/hero.png',
        ]);
        Storage::disk('media')->put($media->storage_path, 'png-bytes');

        $this->actingAs($user)
            ->patch(route('media.update', [$user->currentTeam, $media]), [
                'name' => 'Hero updated',
                'archived' => true,
                'tags' => ['lobby'],
            ])
            ->assertRedirect();

        $this->assertNotNull($media->fresh()->archived_at);

        $this->actingAs($user)
            ->post(route('media.duplicate', [$user->currentTeam, $media]))
            ->assertRedirect();

        $this->assertDatabaseHas('media', [
            'team_id' => $user->currentTeam->id,
            'name' => 'Hero updated copy',
        ]);

        $this->actingAs($user)
            ->delete(route('media.destroy', [$user->currentTeam, $media]))
            ->assertRedirect();

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_executable_html_packages_are_rejected(): void
    {
        Storage::fake('media');

        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/unsafe.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('payload.php', '<?php echo 1;');
        $zip->close();

        $user = User::factory()->create();
        $file = new UploadedFile($path, 'package.zip', 'application/zip', null, true);

        $this->actingAs($user)
            ->post(route('media.store', $user->currentTeam), [
                'files' => [$file],
            ])
            ->assertSessionHasErrors('files');
    }
}
