<?php

namespace App\Data;

readonly class BillingPermissions
{
    public function __construct(
        public bool $canManageBilling,
    ) {
        //
    }
}
