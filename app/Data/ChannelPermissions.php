<?php

namespace App\Data;

readonly class ChannelPermissions
{
    public function __construct(
        public bool $canViewChannels,
        public bool $canCreateChannel,
        public bool $canUpdateChannel,
        public bool $canDeleteChannel,
    ) {
        //
    }
}
