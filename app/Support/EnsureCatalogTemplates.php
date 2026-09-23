<?php

namespace App\Support;

use App\Enums\TemplateStatus;
use App\Models\Template;

final class EnsureCatalogTemplates
{
    /**
     * Install missing ready-made layouts without changing user-created or
     * previously edited catalog templates. This stays database-only so the
     * first gallery request never depends on remote photos or GD rendering.
     */
    public function handle(): void
    {
        $definitions = CatalogTemplateLibrary::definitions();
        $existing = Template::query()
            ->withTrashed()
            ->whereNull('team_id')
            ->where(function ($query) use ($definitions): void {
                $query->whereIn('slug', array_column($definitions, 'key'))
                    ->orWhereIn('name', array_column($definitions, 'name'));
            })
            ->get(['slug', 'name']);

        $slugs = $existing->pluck('slug')->filter()->all();
        $names = $existing->pluck('name')->all();

        foreach ($definitions as $definition) {
            if (in_array($definition['key'], $slugs, true) || in_array($definition['name'], $names, true)) {
                continue;
            }

            $document = $definition['document'];
            Template::query()->withTrashed()->createOrFirst(
                ['slug' => $definition['key']],
                [
                    'team_id' => null,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'category' => $definition['category'],
                    'status' => TemplateStatus::Published,
                    'width' => $document['width'],
                    'height' => $document['height'],
                    'document' => $document,
                    'published_at' => now(),
                ],
            );
        }
    }
}
