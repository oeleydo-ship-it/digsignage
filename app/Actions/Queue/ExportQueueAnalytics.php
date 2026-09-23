<?php

namespace App\Actions\Queue;

use App\Models\QueueTicket;
use App\Support\QueueAnalyticsFilters;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportQueueAnalytics
{
    public function __construct(protected QueryQueueAnalytics $query) {}

    public function handle(QueueAnalyticsFilters $filters): StreamedResponse
    {
        $filename = 'queue-analytics-'.$filters->from->toDateString().'-'.$filters->until->toDateString().'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'ticket', 'issued_at', 'status', 'location', 'service', 'counter',
                'employee', 'waiting_seconds', 'service_seconds',
            ]);

            $this->query->filtered($filters)
                ->with(['service:id,name', 'location:id,name', 'counter:id,name', 'assignedUser:id,name'])
                ->orderBy('id')
                ->lazyById()
                ->each(function (QueueTicket $ticket) use ($handle): void {
                    $row = $this->query->serialize($ticket);
                    fputcsv($handle, [
                        $row['number'],
                        $row['created_at'],
                        $row['status'],
                        $row['location'],
                        $row['service'],
                        $row['counter'],
                        $row['employee'],
                        $row['waiting_duration_seconds'],
                        $row['serving_duration_seconds'],
                    ]);
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
