<?php

namespace App\Support;

use App\Models\Channel;
use App\Models\Location;
use App\Models\Media;
use App\Models\Playlist;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\QueueTicketEvent;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\ScreenGroup;
use App\Models\Template;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class PartnerApi
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    public static function paginated(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => array_values($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function item(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    public static function perPage(int $perPage = 25): int
    {
        return min(max($perPage, 1), 100);
    }

    /**
     * @return array<string, mixed>
     */
    public static function queueService(QueueService $service): array
    {
        $service->loadMissing('location:id,name');
        $waiting = (int) ($service->getAttribute('waiting_count') ?? $service->tickets()
            ->where('status', 'waiting')
            ->count());

        return [
            'id' => $service->id,
            'location_id' => $service->location_id,
            'location' => $service->location?->name,
            'name' => $service->name,
            'code' => $service->code,
            'description' => $service->description,
            'ticket_prefix' => $service->ticket_prefix,
            'next_ticket_number' => $service->nextTicketNumber(),
            'queue_strategy' => $service->queue_strategy->value,
            'average_service_duration_seconds' => $service->average_service_duration_seconds,
            'max_queue_capacity' => $service->max_queue_capacity,
            'waiting_count' => $waiting,
            'estimated_wait_seconds' => $waiting * $service->average_service_duration_seconds,
            'is_active' => $service->is_active,
            'created_at' => $service->created_at?->toIso8601String(),
            'updated_at' => $service->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function queueTicket(QueueTicket $ticket, bool $withEvents = false): array
    {
        $ticket->loadMissing([
            'service:id,name,code',
            'queuePriority:id,name,code,color',
            'counter:id,name,code',
            'assignedUser:id,name',
        ]);

        if ($withEvents) {
            $ticket->loadMissing('events.user:id,name');
        }

        $data = [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'status' => $ticket->status->value,
            'queue_service_id' => $ticket->queue_service_id,
            'service' => $ticket->service->name,
            'location_id' => $ticket->location_id,
            'counter_id' => $ticket->counter_id,
            'counter' => $ticket->counter?->name,
            'assigned_user_id' => $ticket->assigned_user_id,
            'assigned_user' => $ticket->assignedUser?->name,
            'queue_priority_id' => $ticket->queue_priority_id,
            'priority' => $ticket->priority,
            'priority_name' => $ticket->queuePriority?->name,
            'queue_position' => $ticket->queue_position,
            'source' => $ticket->source->value,
            'customer' => [
                'name' => $ticket->customer_name,
                'phone' => $ticket->customer_phone,
                'email' => $ticket->customer_email,
            ],
            'waiting_duration_seconds' => $ticket->waitingDurationSeconds(),
            'serving_duration_seconds' => $ticket->servingDurationSeconds(),
            'called_at' => $ticket->called_at?->toIso8601String(),
            'service_started_at' => $ticket->service_started_at?->toIso8601String(),
            'completed_at' => $ticket->completed_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];

        if ($withEvents) {
            $data['events'] = $ticket->events
                ->sortBy('id')
                ->values()
                ->map(fn (QueueTicketEvent $event): array => [
                    'id' => $event->id,
                    'type' => $event->type->value,
                    'user_id' => $event->user_id,
                    'user' => $event->user?->name,
                    'payload' => $event->payload,
                    'created_at' => $event->created_at?->toIso8601String(),
                ])
                ->all();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function screen(Screen $screen): array
    {
        $screen->loadMissing(['location:id,name', 'groups:id,name', 'currentChannel:id,name']);

        return [
            'id' => $screen->id,
            'name' => $screen->name,
            'description' => $screen->description,
            'status' => $screen->status->value,
            'orientation' => $screen->orientation->value,
            'resolution_width' => $screen->resolution_width,
            'resolution_height' => $screen->resolution_height,
            'timezone' => $screen->timezone,
            'location_id' => $screen->location_id,
            'location' => $screen->location?->name,
            'current_channel_id' => $screen->current_channel_id,
            'group_ids' => $screen->groups->pluck('id')->all(),
            'last_seen_at' => $screen->last_seen_at?->toIso8601String(),
            'app_version' => $screen->app_version,
            'created_at' => $screen->created_at?->toIso8601String(),
            'updated_at' => $screen->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function location(Location $location): array
    {
        return [
            'id' => $location->id,
            'parent_id' => $location->parent_id,
            'type' => $location->type->value,
            'name' => $location->name,
            'description' => $location->description,
            'address' => $location->address,
            'timezone' => $location->timezone,
            'path' => $location->path,
            'depth' => $location->depth,
            'tags' => $location->tags ?? [],
            'created_at' => $location->created_at?->toIso8601String(),
            'updated_at' => $location->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function screenGroup(ScreenGroup $group): array
    {
        $group->loadMissing('screens:id,name');
        $group->loadCount('screens');

        return [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'screens_count' => $group->screens_count,
            'screen_ids' => $group->screens->pluck('id')->all(),
            'created_at' => $group->created_at?->toIso8601String(),
            'updated_at' => $group->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function media(Media $media): array
    {
        return [
            'id' => $media->id,
            'folder_id' => $media->folder_id,
            'type' => $media->type->value,
            'source' => $media->source->value,
            'name' => $media->name,
            'filename' => $media->filename,
            'mime_type' => $media->mime_type,
            'file_size' => $media->file_size,
            'duration' => $media->duration,
            'width' => $media->width,
            'height' => $media->height,
            'processing_status' => $media->processing_status->value,
            'external_url' => $media->external_url,
            'created_at' => $media->created_at?->toIso8601String(),
            'updated_at' => $media->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function template(Template $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'category' => $template->category->value,
            'status' => $template->status->value,
            'width' => $template->width,
            'height' => $template->height,
            'is_platform' => $template->isPlatform(),
            'published_at' => $template->published_at?->toIso8601String(),
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function playlist(Playlist $playlist): array
    {
        $playlist->loadCount('items');

        return [
            'id' => $playlist->id,
            'name' => $playlist->name,
            'description' => $playlist->description,
            'status' => $playlist->status->value,
            'loop' => $playlist->loop,
            'version' => $playlist->version,
            'duration_seconds' => $playlist->duration_seconds,
            'items_count' => $playlist->items_count,
            'published_at' => $playlist->published_at?->toIso8601String(),
            'created_at' => $playlist->created_at?->toIso8601String(),
            'updated_at' => $playlist->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function channel(Channel $channel): array
    {
        $channel->loadMissing('playlist:id,name');
        $channel->loadCount(['zones', 'screens']);

        return [
            'id' => $channel->id,
            'name' => $channel->name,
            'description' => $channel->description,
            'type' => $channel->type->value,
            'status' => $channel->status->value,
            'playlist_id' => $channel->playlist_id,
            'live_protocol' => $channel->live_protocol?->value,
            'live_url' => $channel->live_url,
            'width' => $channel->width,
            'height' => $channel->height,
            'version' => $channel->version,
            'zones_count' => $channel->zones_count,
            'screens_count' => $channel->screens_count,
            'published_at' => $channel->published_at?->toIso8601String(),
            'created_at' => $channel->created_at?->toIso8601String(),
            'updated_at' => $channel->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function schedule(Schedule $schedule): array
    {
        $schedule->loadMissing('targets');

        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'description' => $schedule->description,
            'content_type' => $schedule->content_type->value,
            'channel_id' => $schedule->channel_id,
            'playlist_id' => $schedule->playlist_id,
            'timezone' => $schedule->timezone,
            'starts_on' => $schedule->starts_on->toDateString(),
            'ends_on' => $schedule->ends_on?->toDateString(),
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'recurrence' => $schedule->recurrence->value,
            'weekdays' => $schedule->weekdays,
            'priority' => $schedule->priority,
            'is_enabled' => $schedule->is_enabled,
            'targets' => $schedule->targets->map(fn ($target) => [
                'target_type' => $target->target_type->value,
                'screen_id' => $target->screen_id,
                'screen_group_id' => $target->screen_group_id,
                'location_id' => $target->location_id,
            ])->all(),
            'created_at' => $schedule->created_at?->toIso8601String(),
            'updated_at' => $schedule->updated_at?->toIso8601String(),
        ];
    }
}
