<?php

namespace App\Data;

readonly class EmergencyPermissions
{
    public function __construct(
        public bool $canViewEmergencies,
        public bool $canCreateEmergency,
        public bool $canUpdateEmergency,
        public bool $canStartEmergency,
        public bool $canStopEmergency,
    ) {
        //
    }
}
