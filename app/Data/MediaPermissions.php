<?php

namespace App\Data;

readonly class MediaPermissions
{
    public function __construct(
        public bool $canViewMedia,
        public bool $canCreateMedia,
        public bool $canUpdateMedia,
        public bool $canDeleteMedia,
    ) {
        //
    }
}
