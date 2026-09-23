<?php

namespace App\Data;

readonly class DesignPermissions
{
    public function __construct(
        public bool $canViewDesigns,
        public bool $canCreateDesign,
        public bool $canUpdateDesign,
        public bool $canDeleteDesign,
    ) {
        //
    }
}
