<?php

namespace App\Data;

readonly class QueuePermissions
{
    public function __construct(
        public bool $canViewQueue,
        public bool $canManageQueue,
        public bool $canCallQueue,
        public bool $canTransferQueue,
        public bool $canCompleteQueue,
        public bool $canCancelQueue,
        public bool $canManageServices,
        public bool $canManageCounters,
        public bool $canManageKiosks,
        public bool $canManageAppointments,
        public bool $canViewReports,
        public bool $canManageSettings,
    ) {
        //
    }
}
