<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\RecordPlatformAudit;
use App\Enums\PlatformAuditAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

class JobController extends Controller
{
    public function index(): Response
    {
        $pending = Schema::hasTable('jobs')
            ? DB::table('jobs')
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (stdClass $job) => [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'attempts' => $job->attempts,
                    'available_at' => date('c', (int) $job->available_at),
                ])
            : collect();

        $failed = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (stdClass $job) => [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'connection' => $job->connection,
                    'failed_at' => $job->failed_at,
                    'exception' => mb_substr((string) $job->exception, 0, 400),
                ])
            : collect();

        return Inertia::render('platform/jobs/index', [
            'pending' => $pending,
            'failed' => $failed,
        ]);
    }

    public function retry(Request $request, string $uuid, RecordPlatformAudit $audit): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        $audit->handle(
            PlatformAuditAction::FailedJobRetried,
            $request->user(),
            'failed_job',
            null,
            null,
            ['uuid' => $uuid],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job queued for retry.')]);

        return back();
    }

    public function destroy(Request $request, string $uuid, RecordPlatformAudit $audit): RedirectResponse
    {
        DB::table('failed_jobs')->where('uuid', $uuid)->delete();

        $audit->handle(
            PlatformAuditAction::FailedJobDeleted,
            $request->user(),
            'failed_job',
            null,
            null,
            ['uuid' => $uuid],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Failed job discarded.')]);

        return back();
    }
}
