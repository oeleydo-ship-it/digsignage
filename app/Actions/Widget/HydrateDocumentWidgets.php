<?php

namespace App\Actions\Widget;

use App\Models\Team;
use App\Widgets\WidgetRegistry;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class HydrateDocumentWidgets
{
    public function __construct(
        protected ResolveWidgetData $resolveWidgetData,
        protected WidgetRegistry $widgetRegistry,
    ) {}

    /**
     * Attach resolved widget payloads to design/template document elements.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function handle(Team $team, array $document, string $timezone = 'UTC', ?DateTimeInterface $at = null): array
    {
        $elements = [];
        $queueElements = [];

        foreach ($document['elements'] ?? [] as $index => $element) {
            if (! is_array($element)) {
                continue;
            }

            $key = $this->widgetRegistry->canonicalKey((string) ($element['type'] ?? ''));

            if (str_starts_with($key, 'queue_') && $this->widgetRegistry->has($key)) {
                $queueElements[$index] = [
                    'key' => $key,
                    'props' => is_array($element['props'] ?? null) ? $element['props'] : [],
                ];
            }
        }

        // Read every queue widget in a board from one database snapshot.
        // Otherwise a call-next commit between elements can produce a blank
        // now-serving panel alongside statistics from the new ticket state.
        $queueWidgets = $queueElements === [] ? [] : DB::transaction(function () use ($team, $queueElements, $timezone, $at): array {
            $resolved = [];

            foreach ($queueElements as $index => $queueElement) {
                $resolved[$index] = $this->resolveWidgetData->handle(
                    $team,
                    $queueElement['key'],
                    $queueElement['props'],
                    $timezone,
                    $at,
                );
            }

            return $resolved;
        });

        foreach ($document['elements'] ?? [] as $index => $element) {
            if (! is_array($element)) {
                continue;
            }

            $type = (string) ($element['type'] ?? '');
            $key = $this->widgetRegistry->canonicalKey($type);
            $props = is_array($element['props'] ?? null) ? $element['props'] : [];

            if ($this->widgetRegistry->has($key)) {
                $element['widget'] = $queueWidgets[$index] ?? $this->resolveWidgetData->handle(
                    $team,
                    $key,
                    $props,
                    $timezone,
                    $at,
                );
            }

            $elements[] = $element;
        }

        $document['elements'] = $elements;

        return $document;
    }
}
