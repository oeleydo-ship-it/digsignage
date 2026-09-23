<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\Team;
use App\Support\AuditLogQuery;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportAuditLogs
{
    /**
     * @param  array{action: string, resource_type: string, user_id: string, search: string, from: string, until: string}  $filters
     */
    public function handle(Team $team, array $filters): StreamedResponse
    {
        $filename = 'audit-logs-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($team, $filters) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'timestamp',
                'user',
                'email',
                'action',
                'resource',
                'resource_id',
                'before',
                'after',
                'ip',
                'user_agent',
            ]);

            AuditLogQuery::filtered($team, $filters)
                ->orderBy('id')
                ->lazyById()
                ->each(function (AuditLog $log) use ($handle): void {
                    fputcsv($handle, [
                        $log->created_at?->toIso8601String(),
                        $log->user?->name,
                        $log->user?->email,
                        $log->action->value,
                        $log->resource_type,
                        $log->resource_id,
                        $log->before !== null ? json_encode($log->before) : null,
                        $log->after !== null ? json_encode($log->after) : null,
                        $log->ip_address,
                        $log->user_agent,
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
