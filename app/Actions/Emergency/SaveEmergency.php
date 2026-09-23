<?php

namespace App\Actions\Emergency;

use App\Enums\EmergencyAuditAction;
use App\Enums\EmergencyStatus;
use App\Enums\EmergencyTargetType;
use App\Enums\MediaType;
use App\Models\Emergency;
use App\Models\Location;
use App\Models\Media;
use App\Models\Screen;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveEmergency
{
    public function __construct(protected RecordEmergencyAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, Team $team, array $data, ?Emergency $emergency = null): Emergency
    {
        if ($emergency !== null && ! $emergency->status->isEditable()) {
            throw ValidationException::withMessages([
                'status' => __('An active or finished broadcast cannot be edited.'),
            ]);
        }

        $startsAt = $data['starts_at'] ?? null;
        $expiresAt = $data['expires_at'] ?? null;

        if (filled($startsAt) && filled($expiresAt) && strtotime((string) $expiresAt) <= strtotime((string) $startsAt)) {
            throw ValidationException::withMessages([
                'expires_at' => __('Expiration must be after the start time.'),
            ]);
        }

        $imageId = $this->mediaId($team, $data['image_id'] ?? null, MediaType::Image, 'image_id');
        $videoId = $this->mediaId($team, $data['video_id'] ?? null, MediaType::Video, 'video_id');
        $screenIds = $this->ownedScreenIds($team, $data['screen_ids'] ?? []);
        $locationIds = $this->ownedLocationIds($team, $data['location_ids'] ?? []);

        if ($screenIds === [] && $locationIds === []) {
            throw ValidationException::withMessages([
                'screen_ids' => __('Select at least one screen or location.'),
            ]);
        }

        return DB::transaction(function () use ($user, $team, $data, $emergency, $imageId, $videoId, $screenIds, $locationIds) {
            $attributes = [
                'team_id' => $team->id,
                'updated_by' => $user->id,
                'title' => $data['title'],
                'message' => $data['message'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'background' => $data['background'] ?? null,
                'severity' => $data['severity'],
                'image_id' => $imageId,
                'video_id' => $videoId,
                'starts_at' => $data['starts_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
            ];

            if ($emergency === null) {
                $attributes['created_by'] = $user->id;
                $attributes['status'] = EmergencyStatus::Draft;
                $emergency = Emergency::query()->create($attributes);
                $action = EmergencyAuditAction::Created;
            } else {
                $emergency->forceFill($attributes)->save();
                $action = EmergencyAuditAction::Updated;
            }

            $emergency->targets()->delete();

            foreach ($screenIds as $screenId) {
                $emergency->targets()->create([
                    'team_id' => $team->id,
                    'target_type' => EmergencyTargetType::Screen,
                    'screen_id' => $screenId,
                ]);
            }

            foreach ($locationIds as $locationId) {
                $emergency->targets()->create([
                    'team_id' => $team->id,
                    'target_type' => EmergencyTargetType::Location,
                    'location_id' => $locationId,
                ]);
            }

            $this->audit->handle($emergency, $action, $user, null, [
                'title' => $emergency->title,
                'severity' => $emergency->severity->value,
            ]);

            return $emergency->fresh(['targets', 'image', 'video']) ?? $emergency;
        });
    }

    protected function mediaId(Team $team, mixed $value, MediaType $type, string $field): ?int
    {
        if ($value === null || $value === '' || (int) $value === 0) {
            return null;
        }

        $media = Media::query()
            ->forTeam($team)
            ->where('type', $type)
            ->whereKey((int) $value)
            ->first();

        if ($media === null) {
            throw ValidationException::withMessages([
                $field => __('The selected media is invalid.'),
            ]);
        }

        return $media->id;
    }

    /**
     * @return array<int, int>
     */
    protected function ownedScreenIds(Team $team, mixed $ids): array
    {
        $values = $this->intIds($ids);

        if ($values === []) {
            return [];
        }

        return Screen::query()
            ->forTeam($team)
            ->whereIn('id', $values)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    protected function ownedLocationIds(Team $team, mixed $ids): array
    {
        $values = $this->intIds($ids);

        if ($values === []) {
            return [];
        }

        return Location::query()
            ->forTeam($team)
            ->whereIn('id', $values)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    protected function intIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            $ids,
        )));
    }
}
