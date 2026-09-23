<?php

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\DocumentationController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\PlaylistController;
use App\Http\Controllers\Api\V1\ProofOfPlayController;
use App\Http\Controllers\Api\V1\QueueController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\ScreenController;
use App\Http\Controllers\Api\V1\ScreenGroupController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Player\DeviceRegistrationController;
use App\Http\Controllers\Player\PlayerAssetController;
use App\Http\Controllers\Player\PlayerCommandController;
use App\Http\Controllers\Player\PlayerSessionController;
use Illuminate\Support\Facades\Route;

Route::get('v1/openapi.json', [DocumentationController::class, 'openapi'])->name('api.v1.openapi');

Route::prefix('v1')->middleware(['partner.token', 'throttle:partner-api'])->group(function () {
    Route::get('/', [DocumentationController::class, 'index'])->name('api.v1.root');

    Route::middleware('partner.scope:screens:read')->group(function () {
        Route::get('screens', [ScreenController::class, 'index'])->name('api.v1.screens.index');
        Route::get('screens/{screen}', [ScreenController::class, 'show'])->name('api.v1.screens.show')->whereNumber('screen');
    });
    Route::middleware('partner.scope:screens:write')->group(function () {
        Route::post('screens', [ScreenController::class, 'store'])->name('api.v1.screens.store');
        Route::patch('screens/{screen}', [ScreenController::class, 'update'])->name('api.v1.screens.update')->whereNumber('screen');
        Route::delete('screens/{screen}', [ScreenController::class, 'destroy'])->name('api.v1.screens.destroy')->whereNumber('screen');
    });

    Route::middleware('partner.scope:screen-groups:read')->group(function () {
        Route::get('screen-groups', [ScreenGroupController::class, 'index'])->name('api.v1.screen-groups.index');
        Route::get('screen-groups/{screen_group}', [ScreenGroupController::class, 'show'])->name('api.v1.screen-groups.show')->whereNumber('screen_group');
    });
    Route::middleware('partner.scope:screen-groups:write')->group(function () {
        Route::post('screen-groups', [ScreenGroupController::class, 'store'])->name('api.v1.screen-groups.store');
        Route::patch('screen-groups/{screen_group}', [ScreenGroupController::class, 'update'])->name('api.v1.screen-groups.update')->whereNumber('screen_group');
        Route::put('screen-groups/{screen_group}/screens', [ScreenGroupController::class, 'sync'])->name('api.v1.screen-groups.sync')->whereNumber('screen_group');
        Route::delete('screen-groups/{screen_group}', [ScreenGroupController::class, 'destroy'])->name('api.v1.screen-groups.destroy')->whereNumber('screen_group');
    });

    Route::middleware('partner.scope:locations:read')->group(function () {
        Route::get('locations', [LocationController::class, 'index'])->name('api.v1.locations.index');
        Route::get('locations/{location}', [LocationController::class, 'show'])->name('api.v1.locations.show')->whereNumber('location');
    });
    Route::middleware('partner.scope:locations:write')->group(function () {
        Route::post('locations', [LocationController::class, 'store'])->name('api.v1.locations.store');
        Route::patch('locations/{location}', [LocationController::class, 'update'])->name('api.v1.locations.update')->whereNumber('location');
        Route::delete('locations/{location}', [LocationController::class, 'destroy'])->name('api.v1.locations.destroy')->whereNumber('location');
    });

    Route::middleware('partner.scope:media:read')->group(function () {
        Route::get('media', [MediaController::class, 'index'])->name('api.v1.media.index');
        Route::get('media/{media}', [MediaController::class, 'show'])->name('api.v1.media.show')->whereNumber('media');
    });
    Route::middleware('partner.scope:media:write')->group(function () {
        Route::post('media', [MediaController::class, 'store'])->name('api.v1.media.store');
        Route::patch('media/{media}', [MediaController::class, 'update'])->name('api.v1.media.update')->whereNumber('media');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('api.v1.media.destroy')->whereNumber('media');
    });

    Route::middleware('partner.scope:templates:read')->group(function () {
        Route::get('templates', [TemplateController::class, 'index'])->name('api.v1.templates.index');
        Route::get('templates/{template}', [TemplateController::class, 'show'])->name('api.v1.templates.show')->whereNumber('template');
    });

    Route::middleware('partner.scope:playlists:read')->group(function () {
        Route::get('playlists', [PlaylistController::class, 'index'])->name('api.v1.playlists.index');
        Route::get('playlists/{playlist}', [PlaylistController::class, 'show'])->name('api.v1.playlists.show')->whereNumber('playlist');
    });
    Route::middleware('partner.scope:playlists:write')->group(function () {
        Route::post('playlists', [PlaylistController::class, 'store'])->name('api.v1.playlists.store');
        Route::patch('playlists/{playlist}', [PlaylistController::class, 'update'])->name('api.v1.playlists.update')->whereNumber('playlist');
        Route::delete('playlists/{playlist}', [PlaylistController::class, 'destroy'])->name('api.v1.playlists.destroy')->whereNumber('playlist');
    });

    Route::middleware('partner.scope:channels:read')->group(function () {
        Route::get('channels', [ChannelController::class, 'index'])->name('api.v1.channels.index');
        Route::get('channels/{channel}', [ChannelController::class, 'show'])->name('api.v1.channels.show')->whereNumber('channel');
    });
    Route::middleware('partner.scope:channels:write')->group(function () {
        Route::post('channels', [ChannelController::class, 'store'])->name('api.v1.channels.store');
        Route::patch('channels/{channel}', [ChannelController::class, 'update'])->name('api.v1.channels.update')->whereNumber('channel');
        Route::delete('channels/{channel}', [ChannelController::class, 'destroy'])->name('api.v1.channels.destroy')->whereNumber('channel');
    });

    Route::middleware('partner.scope:schedules:read')->group(function () {
        Route::get('schedules', [ScheduleController::class, 'index'])->name('api.v1.schedules.index');
        Route::get('schedules/{schedule}', [ScheduleController::class, 'show'])->name('api.v1.schedules.show')->whereNumber('schedule');
    });
    Route::middleware('partner.scope:schedules:write')->group(function () {
        Route::post('schedules', [ScheduleController::class, 'store'])->name('api.v1.schedules.store');
        Route::patch('schedules/{schedule}', [ScheduleController::class, 'update'])->name('api.v1.schedules.update')->whereNumber('schedule');
        Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('api.v1.schedules.destroy')->whereNumber('schedule');
    });

    Route::get('analytics', [AnalyticsController::class, 'index'])
        ->middleware('partner.scope:analytics:read')
        ->name('api.v1.analytics.index');

    Route::get('proof-of-play', [ProofOfPlayController::class, 'index'])
        ->middleware('partner.scope:proof-of-play:read')
        ->name('api.v1.proof-of-play.index');

    Route::middleware(['plan.feature:queue_management', 'queue.api.log'])->group(function () {
        Route::middleware('partner.scope:queue:read')->group(function () {
            Route::get('queue/services', [QueueController::class, 'services'])->name('api.v1.queue.services');
            Route::get('queue/tickets/{ticket}', [QueueController::class, 'showTicket'])->name('api.v1.queue.tickets.show')->whereNumber('ticket');
            Route::get('queue/status', [QueueController::class, 'status'])->name('api.v1.queue.status');
        });

        Route::middleware('partner.scope:queue:write')->group(function () {
            Route::post('queue/tickets', [QueueController::class, 'storeTicket'])->name('api.v1.queue.tickets.store');
            Route::post('queue/tickets/{ticket}/cancel', [QueueController::class, 'cancelTicket'])->name('api.v1.queue.tickets.cancel')->whereNumber('ticket');
            Route::post('counters/{counter}/next', [QueueController::class, 'next'])->name('api.v1.counters.next')->whereNumber('counter');
            Route::post('tickets/{ticket}/recall', [QueueController::class, 'recall'])->name('api.v1.tickets.recall')->whereNumber('ticket');
            Route::post('tickets/{ticket}/complete', [QueueController::class, 'complete'])->name('api.v1.tickets.complete')->whereNumber('ticket');
            Route::post('tickets/{ticket}/transfer', [QueueController::class, 'transfer'])->name('api.v1.tickets.transfer')->whereNumber('ticket');
        });
    });
});

Route::prefix('player/v1')->group(function () {
    Route::post('registrations', [DeviceRegistrationController::class, 'store'])
        ->middleware('throttle:player-registration-start')
        ->name('player.registrations.store');

    Route::get('registrations/{code}', [DeviceRegistrationController::class, 'show'])
        ->middleware('throttle:player-registration-status')
        ->name('player.registrations.show');

    Route::middleware('player.device')->group(function () {
        Route::get('session', [PlayerSessionController::class, 'show'])->name('player.session');
        Route::get('manifest', [PlayerSessionController::class, 'manifest'])->name('player.manifest');
        Route::post('heartbeat', [PlayerSessionController::class, 'heartbeat'])
            ->middleware('throttle:player-heartbeat')
            ->name('player.heartbeat');
        Route::post('playback', [PlayerSessionController::class, 'playback'])
            ->middleware('throttle:player-playback')
            ->name('player.playback');
        Route::post('telemetry', [PlayerSessionController::class, 'telemetry'])
            ->middleware('throttle:player-telemetry')
            ->name('player.telemetry');
        Route::get('commands', [PlayerCommandController::class, 'index'])->name('player.commands');
        Route::post('broadcasting/auth', [PlayerCommandController::class, 'broadcastingAuth'])->name('player.broadcasting.auth');
        Route::post('commands/{command}/ack', [PlayerCommandController::class, 'acknowledge'])->name('player.commands.ack');
        Route::post('commands/{command}/complete', [PlayerCommandController::class, 'complete'])->name('player.commands.complete');
        Route::get('assets/media/{media}', [PlayerAssetController::class, 'media'])->name('player.assets.media');
        Route::get('assets/design/{design}', [PlayerAssetController::class, 'design'])->name('player.assets.design');
    });
});
