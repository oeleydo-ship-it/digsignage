<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function index(): Response
    {
        $templates = Template::query()
            ->whereNull('team_id')
            ->orderBy('name')
            ->paginate(25)
            ->through(fn (Template $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'category' => $template->category->value,
                'status' => $template->status->value,
                'status_label' => $template->status->label(),
                'updated_at' => $template->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('platform/templates/index', [
            'templates' => $templates,
        ]);
    }
}
