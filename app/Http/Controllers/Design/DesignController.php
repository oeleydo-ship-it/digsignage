<?php

namespace App\Http\Controllers\Design;

use App\Actions\Design\DuplicateDesign;
use App\Actions\Design\RestoreDesignRevision;
use App\Actions\Design\SaveDesign;
use App\Actions\Widget\HydrateDocumentWidgets;
use App\Enums\DesignStatus;
use App\Enums\TemplateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Design\SaveDesignRequest;
use App\Models\Design;
use App\Models\DesignRevision;
use App\Models\Media;
use App\Models\Template;
use App\Support\CatalogTemplateLibrary;
use App\Support\ContentApprovalPresenter;
use App\Support\DesignDocument;
use App\Support\EnsureCatalogTemplates;
use App\Widgets\WidgetRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DesignController extends Controller
{
    /**
     * Display a listing of designs.
     */
    public function index(Request $request, EnsureCatalogTemplates $catalog): Response
    {
        Gate::authorize('viewAny', Design::class);

        $team = $request->user()->currentTeam;
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        $designs = Design::query()
            ->forTeam($team)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Design $design) => $this->listPayload($design));

        $featuredKeys = CatalogTemplateLibrary::featuredKeys();
        $starterTemplates = [];

        if (Gate::allows('viewAny', Template::class)) {
            $catalog->handle();
            $starterTemplates = Template::query()
                ->visibleTo($request->user(), $team)
                ->whereNull('team_id')
                ->where('status', TemplateStatus::Published->value)
                ->whereIn('slug', $featuredKeys)
                ->get()
                ->sortBy(fn (Template $template) => array_search((string) $template->slug, $featuredKeys, true))
                ->values()
                ->map(fn (Template $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'category_label' => $template->category->label(),
                    'platform' => true,
                    'has_thumbnail' => filled($template->thumbnail_path),
                    'width' => $template->width,
                    'height' => $template->height,
                ]);
        }

        return Inertia::render('designs/index', [
            'designs' => $designs,
            'filters' => ['search' => $search, 'status' => $status],
            'statuses' => collect(DesignStatus::cases())->map(fn (DesignStatus $item) => [
                'value' => $item->value,
                'label' => $item->label(),
            ]),
            'presets' => [
                ['label' => '1920 × 1080', 'width' => 1920, 'height' => 1080],
                ['label' => '1080 × 1920', 'width' => 1080, 'height' => 1920],
                ['label' => '3840 × 2160', 'width' => 3840, 'height' => 2160],
                ['label' => '1366 × 768', 'width' => 1366, 'height' => 768],
            ],
            'starterTemplates' => $starterTemplates,
            'permissions' => $request->user()->toDesignPermissions($team),
        ]);
    }

    /**
     * Store a new design and open the editor.
     */
    public function store(SaveDesignRequest $request, SaveDesign $saveDesign): RedirectResponse
    {
        Gate::authorize('create', Design::class);

        $width = (int) ($request->validated('width') ?? 1920);
        $height = (int) ($request->validated('height') ?? 1080);
        $document = DesignDocument::blank($width, $height);

        $design = $saveDesign->handle(
            $request->user(),
            $request->user()->currentTeam,
            [
                ...$request->validated(),
                'document' => $document,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Design created.')]);

        return redirect()->route('designs.edit', [$request->user()->currentTeam, $design]);
    }

    /**
     * Show the canvas designer.
     */
    public function edit(Request $request, string $current_team, Design $design): Response
    {
        Gate::authorize('view', $design);
        abort_unless($design->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        return Inertia::render('designs/edit', $this->editorPayload($request, $design));
    }

    /**
     * Show a read-only preview of the design.
     */
    public function show(Request $request, string $current_team, Design $design): Response
    {
        Gate::authorize('view', $design);
        abort_unless($design->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        return Inertia::render('designs/preview', [
            'design' => [
                'id' => $design->id,
                'name' => $design->name,
                'document' => app(HydrateDocumentWidgets::class)->handle(
                    $request->user()->currentTeam,
                    $design->normalizedDocument(),
                    $request->string('timezone')->toString() ?: 'UTC',
                ),
            ],
        ]);
    }

    /**
     * Autosave canvas and metadata.
     */
    public function update(
        SaveDesignRequest $request,
        string $current_team,
        Design $design,
        SaveDesign $saveDesign,
    ): RedirectResponse {
        Gate::authorize('update', $design);
        abort_unless($design->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $saveDesign->handle($request->user(), $request->user()->currentTeam, $request->validated(), $design);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Design saved.')]);

        return redirect()->route('designs.edit', [$request->user()->currentTeam, $design]);
    }

    /**
     * Duplicate a design.
     */
    public function duplicate(
        Request $request,
        string $current_team,
        Design $design,
        DuplicateDesign $duplicateDesign,
    ): RedirectResponse {
        Gate::authorize('create', Design::class);
        abort_unless($design->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $copy = $duplicateDesign->handle($request->user(), $design);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Design duplicated.')]);

        return redirect()->route('designs.edit', [$request->user()->currentTeam, $copy]);
    }

    /**
     * Restore a previous revision.
     */
    public function restore(
        Request $request,
        string $current_team,
        Design $design,
        DesignRevision $revision,
        RestoreDesignRevision $restoreRevision,
    ): RedirectResponse {
        Gate::authorize('update', $design);
        abort_unless($design->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $restoreRevision->handle($request->user(), $design, $revision);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Revision restored.')]);

        return back();
    }

    /**
     * Soft-delete a design.
     */
    public function destroy(Request $request, string $current_team, Design $design): RedirectResponse
    {
        Gate::authorize('delete', $design);
        abort_unless($design->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $design->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Design deleted.')]);

        return redirect()->route('designs.index', $request->user()->currentTeam);
    }

    /**
     * @return array<string, mixed>
     */
    protected function listPayload(Design $design): array
    {
        return [
            'id' => $design->id,
            'name' => $design->name,
            'description' => $design->description,
            'status' => $design->status->value,
            'status_label' => $design->status->label(),
            'width' => $design->width,
            'height' => $design->height,
            'version' => $design->version,
            'updated_at' => $design->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function editorPayload(Request $request, Design $design): array
    {
        return [
            'design' => [
                ...$this->listPayload($design),
                'document' => $design->normalizedDocument(),
            ],
            'revisions' => $design->revisions()
                ->limit(20)
                ->get(['id', 'version', 'created_at'])
                ->map(fn (DesignRevision $revision) => [
                    'id' => $revision->id,
                    'version' => $revision->version,
                    'created_at' => $revision->created_at?->toIso8601String(),
                ]),
            'elementTypes' => app(WidgetRegistry::class)->designerElementTypes($request->user()->currentTeam),
            'media' => Gate::allows('viewAny', Media::class)
                ? Media::query()
                    ->forTeam($request->user()->currentTeam)
                    ->notArchived()
                    ->whereIn('type', ['image', 'video'])
                    ->orderBy('name')
                    ->limit(100)
                    ->get()
                    ->map(fn (Media $media) => [
                        'id' => $media->id,
                        'name' => $media->name,
                        'type' => $media->type->value,
                        'width' => $media->width,
                        'height' => $media->height,
                        'has_file' => filled($media->storage_path),
                        'external_url' => $media->external_url,
                    ])
                : [],
            'permissions' => $request->user()->toDesignPermissions($request->user()->currentTeam),
            'approval' => ContentApprovalPresenter::for($request->user(), $design),
        ];
    }
}
