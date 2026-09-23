<?php

namespace App\Actions\Design;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Enums\DesignStatus;
use App\Models\Design;
use App\Models\DesignRevision;
use App\Models\Team;
use App\Models\User;
use App\Support\ContentWorkflow;
use App\Support\DesignDocument;
use Illuminate\Support\Facades\DB;

class SaveDesign
{
    /**
     * Create or update a design and snapshot a revision when the canvas changes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Team $team, array $attributes, ?Design $design = null): Design
    {
        return DB::transaction(function () use ($user, $team, $attributes, $design) {
            $design ??= new Design([
                'team_id' => $team->id,
                'created_by' => $user->id,
                'status' => DesignStatus::Draft,
                'version' => 1,
            ]);
            $creating = ! $design->exists;

            $document = DesignDocument::normalize(
                $attributes['document'] ?? $design->document ?? DesignDocument::blank(),
                (int) ($attributes['width'] ?? $design->width ?? 1920),
                (int) ($attributes['height'] ?? $design->height ?? 1080),
            );

            $previous = json_encode($design->document);
            $next = json_encode($document);
            $documentChanged = $design->exists
                && is_string($previous)
                && is_string($next)
                && $previous !== $next;

            $statusValue = ContentWorkflow::editorStatus(
                $design,
                isset($attributes['status']) ? (string) $attributes['status'] : null,
                $documentChanged,
            );
            $status = DesignStatus::from($statusValue);

            $design->fill([
                'name' => $attributes['name'] ?? $design->name,
                'description' => $attributes['description'] ?? $design->description,
                'status' => $status,
                'width' => $document['width'],
                'height' => $document['height'],
                'document' => $document,
                'updated_by' => $user->id,
                'published_at' => $status === DesignStatus::Published
                    ? ($design->published_at ?? now())
                    : $design->published_at,
            ]);

            if ($documentChanged) {
                $design->version = $design->version + 1;
            }

            $design->save();

            if (! $design->revisions()->exists() || $documentChanged) {
                DesignRevision::query()->create([
                    'team_id' => $team->id,
                    'design_id' => $design->id,
                    'created_by' => $user->id,
                    'version' => $design->version,
                    'document' => $document,
                ]);
            }

            $fresh = $design->refresh();

            if ($creating || $documentChanged) {
                app(RecordOrganizationAudit::class)->handle(
                    $team,
                    AuditAction::ContentChanged,
                    $user,
                    'design',
                    $fresh->id,
                    null,
                    ['name' => $fresh->name, 'version' => $fresh->version],
                );
            }

            return $fresh;
        });
    }
}
