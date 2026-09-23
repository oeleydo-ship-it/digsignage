<?php

namespace App\Http\Controllers\Platform;

use App\Enums\PlatformAuditAction;
use App\Http\Controllers\Controller;
use App\Models\PlatformAudit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $action = $request->string('action')->toString();
        $search = $request->string('search')->toString();

        $logs = PlatformAudit::query()
            ->with('actor:id,name,email')
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('resource_type', 'like', '%'.$search.'%')
                    ->orWhere('ip_address', 'like', '%'.$search.'%');
            }))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (PlatformAudit $log) => [
                'id' => $log->id,
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'resource_type' => $log->resource_type,
                'resource_id' => $log->resource_id,
                'before' => $log->before,
                'after' => $log->after,
                'ip_address' => $log->ip_address,
                'actor_name' => $log->actor?->name,
                'actor_email' => $log->actor?->email,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('platform/audits/index', [
            'filters' => ['action' => $action, 'search' => $search],
            'logs' => $logs,
            'actions' => collect(PlatformAuditAction::cases())->map(fn (PlatformAuditAction $item) => [
                'value' => $item->value,
                'label' => $item->label(),
            ]),
        ]);
    }
}
