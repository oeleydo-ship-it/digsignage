<?php

namespace App\Http\Controllers\Audit;

use App\Actions\Audit\ExportAuditLogs;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLogQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /**
     * Searchable organization audit trail.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AuditLog::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $filters = AuditLogQuery::filters($request);
        $logs = AuditLogQuery::paginate($team, $filters);

        return Inertia::render('audit-logs/index', [
            'filters' => $filters,
            'logs' => $logs->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'resource_type' => $log->resource_type,
                'resource_id' => $log->resource_id,
                'before' => $log->before,
                'after' => $log->after,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'user_name' => $log->user?->name,
                'user_email' => $log->user?->email,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
            'actions' => AuditLogQuery::actionOptions(),
            'users' => $team->members()
                ->orderBy('name')
                ->get(['users.id', 'users.name'])
                ->map(fn (User $user) => [
                    'value' => (string) $user->id,
                    'label' => $user->name,
                ]),
        ]);
    }

    /**
     * Download the filtered audit trail as CSV.
     */
    public function export(Request $request, ExportAuditLogs $export): StreamedResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        return $export->handle($team, AuditLogQuery::filters($request));
    }
}
