<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class ContentWorkflow
{
    /**
     * Status values that editors may choose without going through review.
     *
     * @var list<string>
     */
    public const EditorStatuses = ['draft', 'archived', 'scheduled'];

    public static function isPending(Model $model): bool
    {
        return self::statusValue($model) === 'pending_approval';
    }

    public static function statusValue(Model $model): string
    {
        $status = $model->getAttribute('status');

        if (is_object($status) && property_exists($status, 'value')) {
            return (string) $status->value;
        }

        return is_string($status) ? $status : 'draft';
    }

    public static function title(Model $model): string
    {
        $name = $model->getAttribute('name');

        return is_string($name) && $name !== '' ? $name : class_basename($model);
    }

    public static function version(Model $model): int
    {
        $version = $model->getAttribute('version');

        return is_numeric($version) ? (int) $version : 1;
    }

    public static function typeLabel(Model $model): string
    {
        return match ($model->getMorphClass()) {
            'playlist' => 'Playlist',
            'design' => 'Design',
            'template' => 'Template',
            'channel' => 'Channel',
            default => class_basename($model),
        };
    }

    /**
     * Prevent jumping to review/publish from the editor, and lock pending items.
     */
    public static function assertEditable(Model $model): void
    {
        if (self::isPending($model)) {
            throw ValidationException::withMessages([
                'status' => __('This item is waiting for review and cannot be edited.'),
            ]);
        }
    }

    /**
     * Normalize a status coming from a content editor.
     */
    public static function editorStatus(Model $model, mixed $requested, bool $contentChanged): string
    {
        self::assertEditable($model);

        $current = self::statusValue($model);
        $next = is_string($requested) && $requested !== '' ? $requested : $current;

        if ($contentChanged && in_array($current, ['approved', 'published'], true)) {
            return 'draft';
        }

        if (in_array($next, self::EditorStatuses, true)) {
            return $next;
        }

        return $current !== '' ? $current : 'draft';
    }

    /**
     * Playlist and channel editors may publish without a separate approval hop.
     */
    public static function allowsDirectPublish(Model $model): bool
    {
        return in_array($model->getMorphClass(), ['playlist', 'channel', 'design'], true);
    }

    /**
     * Statuses the publish action may leave from.
     *
     * @return list<string>
     */
    public static function publishFromStatuses(Model $model): array
    {
        if (self::allowsDirectPublish($model)) {
            return ['draft', 'approved', 'rejected', 'scheduled', 'published'];
        }

        return ['approved'];
    }
}
