<?php

namespace App\Actions\Playlist;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Enums\PlaylistItemType;
use App\Enums\PlaylistStatus;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\PlaylistRevision;
use App\Models\Team;
use App\Models\User;
use App\Support\ContentWorkflow;
use App\Support\PlaylistItems;
use Illuminate\Support\Facades\DB;

class SavePlaylist
{
    /**
     * Create or update a playlist and snapshot a revision when the sequence changes.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $legacyTemplateIds
     */
    public function handle(User $user, Team $team, array $attributes, ?Playlist $playlist = null, array $legacyTemplateIds = []): Playlist
    {
        return DB::transaction(function () use ($user, $team, $attributes, $playlist, $legacyTemplateIds) {
            $playlist ??= new Playlist([
                'team_id' => $team->id,
                'created_by' => $user->id,
                'status' => PlaylistStatus::Draft,
                'loop' => true,
                'version' => 1,
                'duration_seconds' => 0,
            ]);

            $playlist->loadMissing('items');
            $creating = ! $playlist->exists;

            $rawItems = [];

            if (isset($attributes['items']) && is_array($attributes['items'])) {
                foreach ($attributes['items'] as $item) {
                    if (is_array($item)) {
                        $rawItems[] = $item;
                    }
                }

                $items = PlaylistItems::normalize(
                    $rawItems,
                    $team,
                    self::allowedTemplateIds($playlist, $legacyTemplateIds),
                );
            } else {
                $items = $playlist->itemsSnapshot();
            }

            $loop = array_key_exists('loop', $attributes)
                ? (bool) $attributes['loop']
                : ($playlist->loop ?? true);

            $previous = json_encode(['items' => $playlist->itemsSnapshot(), 'loop' => $playlist->loop]);
            $next = json_encode(['items' => $items, 'loop' => $loop]);
            $sequenceChanged = $playlist->exists
                && is_string($previous)
                && is_string($next)
                && $previous !== $next;

            $statusValue = ContentWorkflow::editorStatus(
                $playlist,
                isset($attributes['status']) ? (string) $attributes['status'] : null,
                $sequenceChanged,
            );
            $status = PlaylistStatus::from($statusValue);

            $playlist->fill([
                'name' => $attributes['name'] ?? $playlist->name,
                'description' => $attributes['description'] ?? $playlist->description,
                'status' => $status,
                'loop' => $loop,
                'duration_seconds' => PlaylistItems::totalDuration($items),
                'updated_by' => $user->id,
                'published_at' => $status === PlaylistStatus::Published
                    ? ($playlist->published_at ?? now())
                    : $playlist->published_at,
            ]);

            if ($sequenceChanged) {
                $playlist->version = $playlist->version + 1;
            }

            $playlist->save();

            if (isset($attributes['items']) && is_array($attributes['items'])) {
                $playlist->items()->delete();

                foreach ($items as $item) {
                    PlaylistItem::query()->create([
                        ...$item,
                        'team_id' => $team->id,
                        'playlist_id' => $playlist->id,
                    ]);
                }
            }

            $playlist->load('items');

            if (! $playlist->revisions()->exists() || $sequenceChanged) {
                PlaylistRevision::query()->create([
                    'team_id' => $team->id,
                    'playlist_id' => $playlist->id,
                    'created_by' => $user->id,
                    'version' => $playlist->version,
                    'items' => $playlist->itemsSnapshot(),
                    'loop' => $playlist->loop,
                ]);
            }

            $fresh = $playlist->refresh()->load('items');

            if ($creating || $sequenceChanged) {
                app(RecordOrganizationAudit::class)->handle(
                    $team,
                    AuditAction::ContentChanged,
                    $user,
                    'playlist',
                    $fresh->id,
                    null,
                    ['name' => $fresh->name, 'version' => $fresh->version],
                );
            }

            return $fresh;
        });
    }

    /**
     * @param  list<int>  $legacyTemplateIds
     * @return list<int>
     */
    protected static function allowedTemplateIds(?Playlist $playlist, array $legacyTemplateIds): array
    {
        $allowed = [];

        foreach ($legacyTemplateIds as $templateId) {
            if ($templateId > 0) {
                $allowed[] = $templateId;
            }
        }

        if ($playlist === null || ! $playlist->exists) {
            return $allowed;
        }

        $playlist->loadMissing('items');

        foreach ($playlist->items as $item) {
            if ($item->type === PlaylistItemType::Template && $item->template_id) {
                $allowed[] = (int) $item->template_id;
            }
        }

        return $allowed;
    }
}
