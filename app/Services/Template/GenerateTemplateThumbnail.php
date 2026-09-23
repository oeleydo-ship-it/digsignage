<?php

namespace App\Services\Template;

use App\Models\Template;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateTemplateThumbnail
{
    public function __construct(protected RenderTemplateCanvas $renderer) {}

    /**
     * Render a JPEG preview of the template canvas when GD is available.
     */
    public function handle(Template $template): void
    {
        $canvas = $this->renderer->render($template->normalizedDocument());

        if ($canvas === null) {
            return;
        }

        ob_start();
        imagejpeg($canvas, null, 85);
        $binary = ob_get_clean();
        imagedestroy($canvas);

        if (! is_string($binary) || $binary === '') {
            return;
        }

        $directory = ($template->team_id ?? 'platform').'/templates';
        $path = $directory.'/'.$template->id.'-'.Str::uuid().'.jpg';
        $disk = Storage::disk((string) config('media.disk'));
        $disk->makeDirectory($directory);

        if (filled($template->thumbnail_path)) {
            $disk->delete($template->thumbnail_path);
        }

        $disk->put($path, $binary);
        $template->forceFill(['thumbnail_path' => $path])->save();
    }
}
