<?php

namespace App\Enums;

enum TeamPermission: string
{
    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';

    case ViewLocations = 'location:view';
    case CreateLocation = 'location:create';
    case UpdateLocation = 'location:update';
    case DeleteLocation = 'location:delete';

    case ViewScreens = 'screen:view';
    case CreateScreen = 'screen:create';
    case UpdateScreen = 'screen:update';
    case DeleteScreen = 'screen:delete';
    case PairScreen = 'screen:pair';

    case ViewScreenGroups = 'screen-group:view';
    case CreateScreenGroup = 'screen-group:create';
    case UpdateScreenGroup = 'screen-group:update';
    case DeleteScreenGroup = 'screen-group:delete';

    case ViewMedia = 'media:view';
    case CreateMedia = 'media:create';
    case UpdateMedia = 'media:update';
    case DeleteMedia = 'media:delete';

    case ViewDesigns = 'design:view';
    case CreateDesign = 'design:create';
    case UpdateDesign = 'design:update';
    case DeleteDesign = 'design:delete';

    case ViewTemplates = 'template:view';
    case CreateTemplate = 'template:create';
    case UpdateTemplate = 'template:update';
    case DeleteTemplate = 'template:delete';

    case ViewPlaylists = 'playlist:view';
    case CreatePlaylist = 'playlist:create';
    case UpdatePlaylist = 'playlist:update';
    case DeletePlaylist = 'playlist:delete';

    case ViewChannels = 'channel:view';
    case CreateChannel = 'channel:create';
    case UpdateChannel = 'channel:update';
    case DeleteChannel = 'channel:delete';

    case ViewSchedules = 'schedule:view';
    case CreateSchedule = 'schedule:create';
    case UpdateSchedule = 'schedule:update';
    case DeleteSchedule = 'schedule:delete';

    case SubmitContent = 'content:submit';
    case ApproveContent = 'content:approve';
    case PublishContent = 'content:publish';
    case ArchiveContent = 'content:archive';

    case ViewAuditLogs = 'audit:view';
    case ManageBilling = 'billing:manage';

    case ViewEmergencies = 'emergency:view';
    case CreateEmergency = 'emergency:create';
    case UpdateEmergency = 'emergency:update';
    case StartEmergency = 'emergency:start';
    case StopEmergency = 'emergency:stop';

    case ViewQueue = 'queue:view';
    case ManageQueue = 'queue:manage';
    case CallQueue = 'queue:call';
    case TransferQueue = 'queue:transfer';
    case CompleteQueue = 'queue:complete';
    case CancelQueue = 'queue:cancel';
    case ManageQueueServices = 'services:manage';
    case ManageQueueCounters = 'counters:manage';
    case ManageQueueKiosks = 'kiosks:manage';
    case ManageQueueAppointments = 'appointments:manage';
    case ViewQueueReports = 'queue-reports:view';
    case ManageQueueSettings = 'queue-settings:manage';

    case ViewBookings = 'booking:view';
    case CreateBooking = 'booking:create';
    case ManageBookings = 'booking:manage';
    case ManageRooms = 'rooms:manage';
}
