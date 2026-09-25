<?php

namespace App\Console\Commands;

use App\Actions\Booking\SyncMicrosoftCalendars;
use Illuminate\Console\Command;

class SyncMicrosoftCalendarsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'bookings:sync-microsoft';

    /**
     * @var string
     */
    protected $description = 'Pull Microsoft 365 room calendars into room bookings';

    public function handle(SyncMicrosoftCalendars $sync): int
    {
        $summary = $sync->all();

        $this->info("Synced {$summary['connections']} Microsoft 365 connection(s); {$summary['failed']} failed.");

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
