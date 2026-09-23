<?php

namespace App\Data;

readonly class SignagePermissions
{
    public function __construct(
        public bool $canViewLocations,
        public bool $canCreateLocation,
        public bool $canUpdateLocation,
        public bool $canDeleteLocation,
        public bool $canViewScreens,
        public bool $canCreateScreen,
        public bool $canUpdateScreen,
        public bool $canDeleteScreen,
        public bool $canPairScreen,
        public bool $canViewScreenGroups,
        public bool $canCreateScreenGroup,
        public bool $canUpdateScreenGroup,
        public bool $canDeleteScreenGroup,
    ) {
        //
    }
}
