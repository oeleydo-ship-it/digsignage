<?php

namespace App\Actions\Template;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Enums\TemplateCategory;
use App\Enums\TemplateStatus;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;
use App\Services\Template\GenerateTemplateThumbnail;
use App\Support\ContentWorkflow;
use App\Support\DesignDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveTemplate
{
    /**
     * Create or update a team or platform template.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Team $team, array $attributes, ?Template $template = null): Template
    {
        $asPlatform = (bool) ($attributes['platform'] ?? false);

        if ($asPlatform && $user->is_platform_admin !== true) {
            throw ValidationException::withMessages([
                'platform' => __('Only platform administrators can manage catalog templates.'),
            ]);
        }

        if ($template && $template->isPlatform() && $user->is_platform_admin !== true) {
            throw ValidationException::withMessages([
                'platform' => __('Only platform administrators can manage catalog templates.'),
            ]);
        }

        return DB::transaction(function () use ($user, $team, $attributes, $template, $asPlatform) {
            $template ??= new Template([
                'team_id' => $asPlatform ? null : $team->id,
                'created_by' => $user->id,
                'status' => TemplateStatus::Draft,
            ]);
            $creating = ! $template->exists;

            $document = DesignDocument::normalize(
                $attributes['document'] ?? $template->document ?? DesignDocument::blank(),
                (int) ($attributes['width'] ?? $template->width ?? 1920),
                (int) ($attributes['height'] ?? $template->height ?? 1080),
            );

            $documentChanged = $template->exists
                && json_encode($template->document) !== json_encode($document);

            $statusValue = ContentWorkflow::editorStatus(
                $template,
                isset($attributes['status']) ? (string) $attributes['status'] : null,
                $documentChanged,
            );
            $status = TemplateStatus::from($statusValue);

            $template->fill([
                'name' => $attributes['name'] ?? $template->name,
                'description' => $attributes['description'] ?? $template->description,
                'category' => isset($attributes['category'])
                    ? TemplateCategory::from($attributes['category'])
                    : ($template->category ?? TemplateCategory::Corporate),
                'status' => $status,
                'width' => $document['width'],
                'height' => $document['height'],
                'document' => $document,
                'source_design_id' => $attributes['source_design_id'] ?? $template->source_design_id,
                'updated_by' => $user->id,
                'published_at' => $status === TemplateStatus::Published
                    ? ($template->published_at ?? now())
                    : $template->published_at,
                'archived_at' => $status === TemplateStatus::Archived ? now() : null,
            ]);

            if ($asPlatform) {
                $template->team_id = null;
            } elseif (! $template->exists) {
                $template->team_id = $team->id;
            }

            $template->save();
            $template = $template->refresh();
            app(GenerateTemplateThumbnail::class)->handle($template);

            $fresh = $template->refresh();
            $teamId = $fresh->team_id;

            if (! $asPlatform && is_numeric($teamId) && ($creating || $documentChanged)) {
                app(RecordOrganizationAudit::class)->handle(
                    $team,
                    AuditAction::ContentChanged,
                    $user,
                    'template',
                    $fresh->id,
                    null,
                    ['name' => $fresh->name],
                );
            }

            return $fresh;
        });
    }
}
