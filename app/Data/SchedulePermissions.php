<?php

namespace App\Data;

readonly class SchedulePermissions
{
    public function __construct(
        public bool $canViewSchedules,
        public bool $canCreateSchedule,
        public bool $canUpdateSchedule,
        public bool $canDeleteSchedule,
    ) {
        //
    }
}
