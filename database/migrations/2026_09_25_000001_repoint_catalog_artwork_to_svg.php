<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The catalog's shared stock JPEGs were replaced by per-template SVG artwork.
 *
 * Catalog templates are only installed once, so rows created before the
 * switch — and any designs or revisions copied from them — still point at the
 * removed JPEG files. Rewrite those references to the matching SVG. Paths
 * without an SVG counterpart are left alone; the player falls back to a
 * placeholder for them.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = ['templates', 'designs', 'design_revisions'];

    public function up(): void
    {
        $this->rewrite('jpg', 'svg');
    }

    public function down(): void
    {
        // The JPEGs no longer ship with the app, so pointing back at them
        // would only break images. Leave the SVG references in place.
    }

    private function rewrite(string $from, string $to): void
    {
        // JSON columns escape forward slashes, so match both spellings.
        $pattern = '#(\\\\?/images\\\\?/catalog\\\\?/)([a-z0-9-]+)\.'.$from.'#';

        foreach ($this->tables as $table) {
            DB::table($table)
                ->select(['id', 'document'])
                ->where('document', 'like', '%catalog%')
                ->where('document', 'like', '%.'.$from.'%')
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($table, $pattern, $to): void {
                    foreach ($rows as $row) {
                        $document = (string) $row->document;
                        $updated = preg_replace_callback(
                            $pattern,
                            fn (array $match): string => is_file(public_path('images/catalog/'.$match[2].'.'.$to))
                                ? $match[1].$match[2].'.'.$to
                                : $match[0],
                            $document,
                        );

                        if (is_string($updated) && $updated !== $document) {
                            DB::table($table)->where('id', $row->id)->update(['document' => $updated]);
                        }
                    }
                });
        }
    }
};
