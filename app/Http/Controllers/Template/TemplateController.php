<?php

namespace App\Http\Controllers\Template;

use App\Actions\Template\DuplicateTemplate;
use App\Actions\Template\InstantiateTemplate;
use App\Actions\Template\SaveTemplate;
use App\Actions\Widget\HydrateDocumentWidgets;
use App\Enums\TeamPermission;
use App\Enums\TemplateCategory;
use App\Enums\TemplateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Template\SaveTemplateRequest;
use App\Models\Design;
use App\Models\Template;
use App\Support\CatalogTemplateLibrary;
use App\Support\ContentApprovalPresenter;
use App\Support\DesignDocument;
use App\Widgets\WidgetRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemplateController extends Controller
{
    /**
     * Display the template library.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Template::class);

        $user = $request->user();
        $team = $user->currentTeam;
        $search = $request->string('search')->toString();
        $category = $request->string('category')->toString();
        $status = $request->string('status')->toString();
        $scope = $request->string('scope')->toString();
        $orientation = $request->string('orientation')->toString();
        $canManage = $user->hasTeamPermission($team, TeamPermission::UpdateTemplate);

        $templates = Template::query()
            ->visibleTo($user, $team)
            ->when(! $canManage, fn ($query) => $query->where('status', TemplateStatus::Published->value))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($scope === 'team', fn ($query) => $query->where('team_id', $team->id))
            ->when($scope === 'platform', fn ($query) => $query->whereNull('team_id'))
            ->when($orientation === 'landscape', fn ($query) => $query->whereColumn('width', '>', 'height'))
            ->when($orientation === 'portrait', fn ($query) => $query->whereColumn('height', '>', 'width'))
            ->when($orientation === 'square', fn ($query) => $query->whereColumn('width', '=', 'height'))
            ->latest()
            ->paginate(48)
            ->withQueryString()
            ->through(fn (Template $template) => $this->listPayload($template));

        $featuredKeys = CatalogTemplateLibrary::featuredKeys();
        $featured = Template::query()
            ->visibleTo($user, $team)
            ->whereNull('team_id')
            ->where('status', TemplateStatus::Published->value)
            ->whereIn('slug', $featuredKeys)
            ->get()
            ->sortBy(fn (Template $template) => array_search((string) $template->slug, $featuredKeys, true))
            ->values()
            ->map(fn (Template $template) => $this->listPayload($template));

        $portraitFeaturedKeys = CatalogTemplateLibrary::portraitFeaturedKeys();
        $portraitFeatured = Template::query()
            ->visibleTo($user, $team)
            ->whereNull('team_id')
            ->where('status', TemplateStatus::Published->value)
            ->whereIn('slug', $portraitFeaturedKeys)
            ->get()
            ->sortBy(fn (Template $template) => array_search((string) $template->slug, $portraitFeaturedKeys, true))
            ->values()
            ->map(fn (Template $template) => $this->listPayload($template));

        return Inertia::render('templates/index', [
            'templates' => $templates,
            'featured' => $featured,
            'portraitFeatured' => $portraitFeatured,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'status' => $status,
                'scope' => $scope,
                'orientation' => $orientation,
            ],
            'categories' => collect(TemplateCategory::cases())->map(fn (TemplateCategory $item) => [
                'value' => $item->value,
                'label' => $item->label(),
            ]),
            'statuses' => collect(TemplateStatus::cases())->map(fn (TemplateStatus $item) => [
                'value' => $item->value,
                'label' => $item->label(),
            ]),
            'permissions' => $user->toTemplatePermissions($team),
        ]);
    }

    /**
     * Store a new template.
     */
    public function store(SaveTemplateRequest $request, SaveTemplate $saveTemplate): RedirectResponse
    {
        $asPlatform = $request->boolean('platform');
        Gate::authorize($asPlatform ? 'createPlatform' : 'create', Template::class);

        $width = (int) ($request->validated('width') ?? 1920);
        $height = (int) ($request->validated('height') ?? 1080);

        $template = $saveTemplate->handle(
            $request->user(),
            $request->user()->currentTeam,
            [
                ...$request->validated(),
                'document' => $request->validated('document') ?? DesignDocument::blank($width, $height),
                'platform' => $asPlatform,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template created.')]);

        return redirect()->route('templates.edit', [$request->user()->currentTeam, $template]);
    }

    /**
     * Save a team design as a reusable template.
     */
    public function storeFromDesign(
        SaveTemplateRequest $request,
        string $current_team,
        Design $design,
        SaveTemplate $saveTemplate,
    ): RedirectResponse {
        $asPlatform = $request->boolean('platform');
        Gate::authorize($asPlatform ? 'createPlatform' : 'create', Template::class);
        abort_unless($design->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $template = $saveTemplate->handle(
            $request->user(),
            $request->user()->currentTeam,
            [
                ...$request->validated(),
                'document' => $request->validated('document') ?? $design->normalizedDocument(),
                'source_design_id' => $design->id,
                'platform' => $asPlatform,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template saved from design.')]);

        return redirect()->route('templates.edit', [$request->user()->currentTeam, $template]);
    }

    /**
     * Preview a template canvas.
     */
    public function show(Request $request, string $current_team, Template $template): Response
    {
        Gate::authorize('view', $template);
        $this->assertAccessible($request, $current_team, $template);

        return Inertia::render('templates/preview', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'document' => app(HydrateDocumentWidgets::class)->handle(
                    $request->user()->currentTeam,
                    $template->normalizedDocument(),
                    $request->string('timezone')->toString() ?: 'UTC',
                ),
            ],
        ]);
    }

    /**
     * Edit a template canvas.
     */
    public function edit(Request $request, string $current_team, Template $template): Response
    {
        Gate::authorize('view', $template);
        $this->assertAccessible($request, $current_team, $template);

        return Inertia::render('templates/edit', [
            'template' => [
                ...$this->listPayload($template),
                'document' => $template->normalizedDocument(),
            ],
            'elementTypes' => app(WidgetRegistry::class)->designerElementTypes($request->user()->currentTeam),
            'categories' => collect(TemplateCategory::cases())->map(fn (TemplateCategory $item) => [
                'value' => $item->value,
                'label' => $item->label(),
            ]),
            'statuses' => collect(TemplateStatus::cases())
                ->filter(fn (TemplateStatus $item) => in_array($item, [TemplateStatus::Draft, TemplateStatus::Archived], true))
                ->map(fn (TemplateStatus $item) => [
                    'value' => $item->value,
                    'label' => $item->label(),
                ])
                ->values(),
            'permissions' => $request->user()->toTemplatePermissions($request->user()->currentTeam),
            'approval' => ContentApprovalPresenter::for($request->user(), $template),
        ]);
    }

    /**
     * Update a template.
     */
    public function update(
        SaveTemplateRequest $request,
        string $current_team,
        Template $template,
        SaveTemplate $saveTemplate,
    ): RedirectResponse {
        Gate::authorize('update', $template);
        $this->assertAccessible($request, $current_team, $template);

        $saveTemplate->handle(
            $request->user(),
            $request->user()->currentTeam,
            $request->validated(),
            $template,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template saved.')]);

        return redirect()->route('templates.edit', [$request->user()->currentTeam, $template]);
    }

    /**
     * Duplicate a template.
     */
    public function duplicate(
        Request $request,
        string $current_team,
        Template $template,
        DuplicateTemplate $duplicateTemplate,
    ): RedirectResponse {
        Gate::authorize('create', Template::class);
        $this->assertAccessible($request, $current_team, $template);

        $copy = $duplicateTemplate->handle(
            $request->user(),
            $request->user()->currentTeam,
            $template,
            $request->boolean('platform'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template duplicated.')]);

        return redirect()->route('templates.edit', [$request->user()->currentTeam, $copy]);
    }

    /**
     * Create a team design from this template.
     */
    public function instantiate(
        Request $request,
        string $current_team,
        Template $template,
        InstantiateTemplate $instantiateTemplate,
    ): RedirectResponse {
        Gate::authorize('view', $template);
        Gate::authorize('create', Design::class);
        $this->assertAccessible($request, $current_team, $template);

        $design = $instantiateTemplate->handle(
            $request->user(),
            $request->user()->currentTeam,
            $template,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Design created from template.')]);

        return redirect()->route('designs.edit', [$request->user()->currentTeam, $design]);
    }

    /**
     * Stream the generated template thumbnail.
     */
    public function thumbnail(Request $request, string $current_team, Template $template): StreamedResponse
    {
        Gate::authorize('view', $template);
        $this->assertAccessible($request, $current_team, $template);
        abort_unless(filled($template->thumbnail_path), 404);
        abort_unless(Storage::disk($template->disk())->exists($template->thumbnail_path), 404);

        return Storage::disk($template->disk())->response(
            $template->thumbnail_path,
            'thumbnail.jpg',
            [
                'Content-Type' => 'image/jpeg',
                'Content-Disposition' => 'inline',
            ],
        );
    }

    /**
     * Soft-delete a template.
     */
    public function destroy(Request $request, string $current_team, Template $template): RedirectResponse
    {
        Gate::authorize('delete', $template);
        $this->assertAccessible($request, $current_team, $template);

        $template->deleteStoredFiles();
        $template->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template deleted.')]);

        return redirect()->route('templates.index', $request->user()->currentTeam);
    }

    /**
     * @return array<string, mixed>
     */
    protected function listPayload(Template $template): array
    {
        $teamSlug = request()->user()?->currentTeam?->slug;
        $hasThumbnail = filled($template->thumbnail_path);

        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'category' => $template->category->value,
            'category_label' => $template->category->label(),
            'status' => $template->status->value,
            'status_label' => $template->status->label(),
            'width' => $template->width,
            'height' => $template->height,
            'platform' => $template->isPlatform(),
            'has_thumbnail' => $hasThumbnail,
            'thumbnail_url' => $hasThumbnail && is_string($teamSlug)
                ? $template->previewUrl($teamSlug)
                : null,
            'document' => $template->isPlatform() || ! $hasThumbnail
                ? $template->normalizedDocument()
                : null,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Ensure the template is reachable in the current team context.
     */
    protected function assertAccessible(Request $request, string $currentTeam, Template $template): void
    {
        abort_unless($currentTeam === $request->user()->currentTeam->slug, 403);

        if ($template->isPlatform()) {
            return;
        }

        abort_unless($template->team_id === $request->user()->currentTeam->id, 403);
    }
}
