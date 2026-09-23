<?php

namespace App\Console\Commands;

use App\Models\Template;
use App\Services\Template\GenerateTemplateThumbnail;
use Illuminate\Console\Command;

class RegenerateTemplateThumbnailsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'templates:regenerate-thumbnails
                            {--platform : Only regenerate platform catalog templates}
                            {--team= : Only regenerate templates for a team ID}';

    /**
     * @var string
     */
    protected $description = 'Render and store JPEG previews for template gallery cards';

    public function handle(GenerateTemplateThumbnail $thumbnails): int
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->error('GD extension is required to generate template thumbnails.');

            return self::FAILURE;
        }

        $query = Template::query();

        if ($this->option('platform')) {
            $query->whereNull('team_id');
        }

        if ($this->option('team') !== null) {
            $query->where('team_id', (int) $this->option('team'));
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No templates matched the selected filters.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->orderBy('id')->chunkById(25, function ($templates) use ($thumbnails, $bar) {
            foreach ($templates as $template) {
                $thumbnails->handle($template);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Regenerated {$count} template thumbnail(s).");

        return self::SUCCESS;
    }
}
