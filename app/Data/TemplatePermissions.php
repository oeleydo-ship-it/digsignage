<?php

namespace App\Data;

readonly class TemplatePermissions
{
    public function __construct(
        public bool $canViewTemplates,
        public bool $canCreateTemplate,
        public bool $canUpdateTemplate,
        public bool $canDeleteTemplate,
        public bool $canManagePlatformTemplates,
    ) {
        //
    }
}
