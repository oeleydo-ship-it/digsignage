<?php

namespace App\Actions\ProofOfPlay;

use App\Models\PlayerPlaybackEvent;
use App\Support\ProofOfPlayFilters;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportProofOfPlay
{
    public function __construct(protected QueryProofOfPlay $query) {}

    public function handle(ProofOfPlayFilters $filters): StreamedResponse
    {
        $filename = 'proof-of-play-'.$filters->from->toDateString().'-'.$filters->until->toDateString().'.csv';

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'screen',
                'location',
                'channel',
                'playlist',
                'content_id',
                'title',
                'started_at',
                'ended_at',
                'duration_ms',
                'status',
            ]);

            $this->query->filtered($filters)
                ->with($this->query->playbackRelations())
                ->orderBy('id')
                ->lazyById()
                ->each(function (PlayerPlaybackEvent $event) use ($handle): void {
                    $row = $this->query->serialize($event);

                    fputcsv($handle, [
                        $row['screen'],
                        $row['location'],
                        $row['channel'],
                        $row['playlist'],
                        $row['content_id'],
                        $row['title'],
                        $row['started_at'],
                        $row['ended_at'],
                        $row['duration_ms'],
                        $row['status'],
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
