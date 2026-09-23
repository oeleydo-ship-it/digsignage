<?php

namespace App\Actions\Queue;

use App\Models\QueueKiosk;

class DeleteQueueKiosk
{
    /**
     * Remove a kiosk. Issued tickets are unaffected.
     */
    public function handle(QueueKiosk $kiosk): void
    {
        $kiosk->delete();
    }
}
