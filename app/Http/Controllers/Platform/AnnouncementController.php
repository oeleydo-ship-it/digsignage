<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\RecordPlatformAudit;
use App\Enums\PlatformAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SaveAnnouncementRequest;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('platform/announcements/index', [
            'announcements' => Announcement::query()
                ->with('author:id,name')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Announcement $item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'body' => $item->body,
                    'published_at' => $item->published_at?->toIso8601String(),
                    'expires_at' => $item->expires_at?->toIso8601String(),
                    'author' => $item->author?->name,
                ]),
        ]);
    }

    public function store(SaveAnnouncementRequest $request, RecordPlatformAudit $audit): RedirectResponse
    {
        $announcement = Announcement::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'published_at' => $request->validated('published_at') ?? now(),
        ]);

        $audit->handle(
            PlatformAuditAction::AnnouncementPublished,
            $request->user(),
            'announcement',
            $announcement->id,
            null,
            ['title' => $announcement->title],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement published.')]);

        return back();
    }

    public function destroy(Request $request, Announcement $announcement, RecordPlatformAudit $audit): RedirectResponse
    {
        $audit->handle(
            PlatformAuditAction::AnnouncementDeleted,
            $request->user(),
            'announcement',
            $announcement->id,
            ['title' => $announcement->title],
        );

        $announcement->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement removed.')]);

        return back();
    }
}
