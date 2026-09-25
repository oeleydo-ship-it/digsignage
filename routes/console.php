<?php

use App\Actions\Emergency\ActivateScheduledEmergencies;
use App\Actions\Emergency\ExpireEmergencies;
use App\Actions\Player\ExpireDeviceCommands;
use App\Actions\Queue\EvaluateApproachingQueueAppointments;
use App\Actions\Queue\EvaluateQueueAlerts;
use App\Actions\Signage\EvaluateScreenHealth;
use App\Models\DeviceRegistration;
use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::call(function () {
    DeviceRegistration::query()
        ->whereNull('screen_id')
        ->where('expires_at', '<', now())
        ->delete();
})->hourly()->description('Delete expired device registrations');

Schedule::call(function () {
    app(ExpireDeviceCommands::class)->handle();
})->everyMinute()->description('Expire undelivered player commands');

Schedule::call(function () {
    app(EvaluateScreenHealth::class)->handle();
})->everyMinute()->description('Evaluate screen health from last heartbeat');

Schedule::call(function () {
    app(ActivateScheduledEmergencies::class)->handle();
})->everyMinute()->description('Activate scheduled emergency broadcasts');

Schedule::call(function () {
    app(ExpireEmergencies::class)->handle();
})->everyMinute()->description('Expire emergency broadcasts past their end time');

Schedule::call(function () {
    app(EvaluateApproachingQueueAppointments::class)->handle();
})->everyMinute()->description('Queue approaching appointment notifications');

Schedule::call(function () {
    app(EvaluateQueueAlerts::class)->handle();
})->everyMinute()->description('Evaluate automated queue alerts');

Schedule::command('bookings:sync-microsoft')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->description('Pull Microsoft 365 room calendars into room bookings');
