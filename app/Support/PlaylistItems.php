<?php

namespace App\Support;

use App\Enums\PlaylistItemType;
use App\Enums\PlaylistTransition;
use App\Enums\TemplateStatus;
use App\Models\Design;
use App\Models\Media;
use App\Models\Team;
use App\Models\Template;
use App\Widgets\WidgetRegistry;
use Illuminate\Validation\ValidationException;

final class PlaylistItems
{
    /**
     * Normalize playlist items and ensure referenced content belongs to the team.
     *
     * Template items cannot be newly attached. Existing slots (one per matching
     * template_id) may be kept so legacy playlists still save and play.
     *
     * @param  array<int|string, mixed>  $items
     * @param  list<int>  $allowedTemplateIds
     * @return list<array<string, mixed>>
     */
    public static function normalize(array $items, Team $team, array $allowedTemplateIds = []): array
    {
        $normalized = [];
        $index = 0;
        $remainingTemplateSlots = $allowedTemplateIds;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = PlaylistItemType::tryFrom((string) ($item['type'] ?? ''));

            if ($type === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.type" => __('Unsupported playlist item type.'),
                ]);
            }

            $mediaId = self::nullableId($item['media_id'] ?? null);
            $designId = self::nullableId($item['design_id'] ?? null);
            $templateId = self::nullableId($item['template_id'] ?? null);
            $url = is_string($item['url'] ?? null) ? trim($item['url']) : null;
            $widgetKey = is_string($item['widget_key'] ?? null) ? trim($item['widget_key']) : null;
            $widgetSettings = is_array($item['widget_settings'] ?? null) ? $item['widget_settings'] : [];
            $title = is_string($item['title'] ?? null) ? trim($item['title']) : '';

            match ($type) {
                PlaylistItemType::Media => self::assertMedia($mediaId, $team, $index),
                PlaylistItemType::Design => self::assertDesign($designId, $team, $index),
                PlaylistItemType::Template => self::assertLegacyTemplate($templateId, $team, $index, $remainingTemplateSlots),
                PlaylistItemType::WebPage, PlaylistItemType::LiveStream => self::assertUrl($url, $index),
                PlaylistItemType::Widget => null,
            };

            if ($type === PlaylistItemType::Widget) {
                $widgetSettings = self::assertWidget($widgetKey, $widgetSettings, $index);
            }

            if ($title === '') {
                $title = $type->label();
            }

            $duration = max(1, min(86400, (int) ($item['duration_seconds'] ?? 15)));
            $transition = PlaylistTransition::tryFrom((string) ($item['transition'] ?? PlaylistTransition::Fade->value))
                ?? PlaylistTransition::Fade;

            $normalized[] = [
                'type' => $type->value,
                'title' => $title,
                'duration_seconds' => $duration,
                'transition' => $transition->value,
                'transition_ms' => max(0, min(5000, (int) ($item['transition_ms'] ?? 400))),
                'enabled' => (bool) ($item['enabled'] ?? true),
                'available_from' => self::nullableTimestamp($item['available_from'] ?? null),
                'available_until' => self::nullableTimestamp($item['available_until'] ?? null),
                'position' => $index + 1,
                'media_id' => $type === PlaylistItemType::Media ? $mediaId : null,
                'design_id' => $type === PlaylistItemType::Design ? $designId : null,
                'template_id' => $type === PlaylistItemType::Template ? $templateId : null,
                'url' => in_array($type, [PlaylistItemType::WebPage, PlaylistItemType::LiveStream], true) ? $url : null,
                'widget_key' => $type === PlaylistItemType::Widget ? $widgetKey : null,
                'widget_settings' => $type === PlaylistItemType::Widget ? $widgetSettings : null,
            ];
            $index++;
        }

        return $normalized;
    }

    /**
     * Sum duration for enabled items.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public static function totalDuration(array $items): int
    {
        $total = 0;

        foreach ($items as $item) {
            if (! (bool) ($item['enabled'] ?? true)) {
                continue;
            }

            $total += (int) ($item['duration_seconds'] ?? 0);
        }

        return $total;
    }

    protected static function nullableTimestamp(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    protected static function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    protected static function assertMedia(?int $mediaId, Team $team, int $index): void
    {
        if ($mediaId === null || Media::query()->forTeam($team)->whereKey($mediaId)->doesntExist()) {
            throw ValidationException::withMessages([
                "items.{$index}.media_id" => __('The selected media is not available to this team.'),
            ]);
        }
    }

    protected static function assertDesign(?int $designId, Team $team, int $index): void
    {
        if ($designId === null || Design::query()->forTeam($team)->published()->whereKey($designId)->doesntExist()) {
            throw ValidationException::withMessages([
                "items.{$index}.design_id" => __('Publish this design before adding it to a playlist.'),
            ]);
        }
    }

    /**
     * @param  list<int>  $remainingTemplateSlots
     */
    protected static function assertLegacyTemplate(?int $templateId, Team $team, int $index, array &$remainingTemplateSlots): void
    {
        $slot = $templateId === null ? false : array_search($templateId, $remainingTemplateSlots, true);

        if ($slot === false) {
            throw ValidationException::withMessages([
                "items.{$index}.type" => __('Templates cannot be added to playlists. Use the template to create a design, then add that design.'),
            ]);
        }

        unset($remainingTemplateSlots[$slot]);
        $remainingTemplateSlots = array_values($remainingTemplateSlots);

        $exists = Template::query()
            ->whereKey($templateId)
            ->where(function ($query) use ($team) {
                $query->where('team_id', $team->id)
                    ->orWhere(function ($platform) {
                        $platform->whereNull('team_id')
                            ->where('status', TemplateStatus::Published->value);
                    });
            })
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                "items.{$index}.template_id" => __('The selected template is not available to this team.'),
            ]);
        }
    }

    protected static function assertUrl(?string $url, int $index): void
    {
        if ($url === null || $url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages([
                "items.{$index}.url" => __('A valid URL is required.'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected static function assertWidget(?string $widgetKey, array $settings, int $index): array
    {
        $registry = app(WidgetRegistry::class);

        if ($widgetKey === null || $widgetKey === '' || ! $registry->has($widgetKey)) {
            throw ValidationException::withMessages([
                "items.{$index}.widget_key" => __('A registered widget is required.'),
            ]);
        }

        try {
            return $registry->normalizeSettings($widgetKey, $settings);
        } catch (ValidationException $exception) {
            $messages = [];

            foreach ($exception->errors() as $field => $errors) {
                $messages["items.{$index}.{$field}"] = $errors;
            }

            throw ValidationException::withMessages($messages);
        }
    }
}
