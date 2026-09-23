<?php

use App\Http\Controllers\Analytics\AnalyticsController;
use App\Http\Controllers\Approval\ContentApprovalController;
use App\Http\Controllers\Audit\AuditLogController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\Channel\ChannelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Design\DesignController;
use App\Http\Controllers\Emergency\EmergencyController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Integrations\ContentAppController;
use App\Http\Controllers\Media\MediaController;
use App\Http\Controllers\Media\MediaFileController;
use App\Http\Controllers\Media\MediaFolderController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Player\PlayerPlayController;
use App\Http\Controllers\Player\PlayerSetupController;
use App\Http\Controllers\Playlist\PlaylistController;
use App\Http\Controllers\ProofOfPlay\ProofOfPlayController;
use App\Http\Controllers\Queue\QueueAppointmentCheckInController;
use App\Http\Controllers\Queue\QueueKioskServeController;
use App\Http\Controllers\Queue\QueueVirtualController;
use App\Http\Controllers\Schedule\ScheduleController;
use App\Http\Controllers\Signage\DeviceCommandController;
use App\Http\Controllers\Signage\FallbackImageController;
use App\Http\Controllers\Signage\LocationController;
use App\Http\Controllers\Signage\MonitoringController;
use App\Http\Controllers\Signage\ScreenController;
use App\Http\Controllers\Signage\ScreenGroupController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Template\TemplateController;
use App\Http\Controllers\Widget\WidgetResolveController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('dashboard', HomeController::class)
    ->middleware(['auth', 'verified'])
    ->name('workspace');

Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::get('player/setup', PlayerSetupController::class)->name('player.setup');
Route::get('player', PlayerPlayController::class)->name('player.play');

Route::middleware('plan.feature:queue_management')->group(function () {
    Route::get('kiosk/{queueKiosk:token}', [QueueKioskServeController::class, 'show'])
        ->name('queue.kiosk.serve');
    Route::post('kiosk/{queueKiosk:token}/tickets', [QueueKioskServeController::class, 'issue'])
        ->middleware('throttle:queue-kiosk-issue')
        ->name('queue.kiosk.tickets.store');
    Route::post('kiosk/{queueKiosk:token}/appointments/check-in', [QueueKioskServeController::class, 'checkInAppointment'])
        ->middleware('throttle:queue-kiosk-issue')
        ->name('queue.kiosk.appointments.check-in');

    Route::get('appointment-check-in/{queueAppointment:reference}', [QueueAppointmentCheckInController::class, 'show'])
        ->name('queue.appointment.check-in.show');
    Route::post('appointment-check-in/{queueAppointment:reference}', [QueueAppointmentCheckInController::class, 'store'])
        ->middleware('throttle:queue-kiosk-issue')
        ->name('queue.appointment.check-in.store');

    Route::get('join/{team:slug}', [QueueVirtualController::class, 'show'])
        ->name('queue.virtual.show');
    Route::post('join/{team:slug}/tickets', [QueueVirtualController::class, 'store'])
        ->middleware('throttle:queue-kiosk-issue')
        ->name('queue.virtual.store');
    Route::get('queue-ticket/{queueTicket:public_token}', [QueueVirtualController::class, 'ticket'])
        ->name('queue.virtual.ticket');
    Route::post('queue-ticket/{queueTicket:public_token}/cancel', [QueueVirtualController::class, 'cancel'])
        ->middleware('throttle:queue-kiosk-issue')
        ->name('queue.virtual.cancel');
});

require __DIR__.'/platform.php';

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
        Route::patch('monitoring', [MonitoringController::class, 'update'])->name('monitoring.update');

        Route::get('player-fallback', [FallbackImageController::class, 'edit'])->name('player-fallback.edit');
        Route::post('player-fallback', [FallbackImageController::class, 'update'])->name('player-fallback.update');
        Route::delete('player-fallback', [FallbackImageController::class, 'destroy'])->name('player-fallback.destroy');

        Route::get('apps', [ContentAppController::class, 'edit'])->name('apps.edit');
        Route::patch('apps', [ContentAppController::class, 'update'])->middleware('impersonation.deny')->name('apps.update');

        Route::get('reports/proof-of-play', [ProofOfPlayController::class, 'index'])->name('proof-of-play.index');
        Route::get('reports/proof-of-play/export', [ProofOfPlayController::class, 'export'])->name('proof-of-play.export');
        Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/settings', [NotificationController::class, 'updateSettings'])->name('notifications.settings.update');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('notifications/{inAppNotification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');

        Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('billing/complete', [BillingController::class, 'complete'])->name('billing.complete');
        Route::post('billing/checkout', [BillingController::class, 'checkout'])->middleware('impersonation.deny')->name('billing.checkout');
        Route::post('billing/swap', [BillingController::class, 'swap'])->middleware('impersonation.deny')->name('billing.swap');
        Route::post('billing/cancel', [BillingController::class, 'cancel'])->middleware('impersonation.deny')->name('billing.cancel');
        Route::post('billing/resume', [BillingController::class, 'resume'])->middleware('impersonation.deny')->name('billing.resume');
        Route::post('billing/portal', [BillingController::class, 'portal'])->middleware('impersonation.deny')->name('billing.portal');
        Route::post('billing/coupon', [BillingController::class, 'coupon'])->middleware('impersonation.deny')->name('billing.coupon');

        Route::get('approvals', [ContentApprovalController::class, 'index'])->name('approvals.index');
        Route::post('approvals/{type}/{id}/submit', [ContentApprovalController::class, 'submit'])->name('approvals.submit');
        Route::post('approvals/{type}/{id}/approve', [ContentApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('approvals/{type}/{id}/reject', [ContentApprovalController::class, 'reject'])->name('approvals.reject');
        Route::post('approvals/{type}/{id}/publish', [ContentApprovalController::class, 'publish'])->name('approvals.publish');
        Route::post('approvals/{type}/{id}/archive', [ContentApprovalController::class, 'archive'])->name('approvals.archive');

        Route::get('locations', [LocationController::class, 'index'])->name('locations.index');
        Route::post('locations', [LocationController::class, 'store'])->name('locations.store');
        Route::patch('locations/{location}', [LocationController::class, 'update'])->name('locations.update');
        Route::delete('locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');

        Route::get('screens', [ScreenController::class, 'index'])->name('screens.index');
        Route::post('screens', [ScreenController::class, 'store'])->name('screens.store');
        Route::post('screens/pair', [ScreenController::class, 'pair'])
            ->middleware('throttle:10,1')
            ->name('screens.pair');
        Route::post('screens/{screen}/rotate-credentials', [ScreenController::class, 'rotateCredentials'])
            ->middleware(['throttle:10,1', 'impersonation.deny'])
            ->name('screens.rotate-credentials');
        Route::post('screens/bulk', [ScreenController::class, 'bulk'])->name('screens.bulk');
        Route::patch('screens/{screen}', [ScreenController::class, 'update'])->name('screens.update');
        Route::delete('screens/{screen}', [ScreenController::class, 'destroy'])->name('screens.destroy');
        Route::get('screens/{screen}/commands', [DeviceCommandController::class, 'index'])->name('screens.commands.index');
        Route::post('screens/{screen}/commands', [DeviceCommandController::class, 'store'])->name('screens.commands.store');
        Route::post('screens/{screen}/fallback-image', [FallbackImageController::class, 'updateScreen'])->name('screens.fallback-image.update');
        Route::delete('screens/{screen}/fallback-image', [FallbackImageController::class, 'destroyScreen'])->name('screens.fallback-image.destroy');

        Route::get('screen-groups', [ScreenGroupController::class, 'index'])->name('screen-groups.index');
        Route::post('screen-groups', [ScreenGroupController::class, 'store'])->name('screen-groups.store');
        Route::patch('screen-groups/{screenGroup}', [ScreenGroupController::class, 'update'])->name('screen-groups.update');
        Route::delete('screen-groups/{screenGroup}', [ScreenGroupController::class, 'destroy'])->name('screen-groups.destroy');
        Route::put('screen-groups/{screenGroup}/screens', [ScreenGroupController::class, 'syncScreens'])->name('screen-groups.screens.sync');

        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::post('media', [MediaController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('media.store');
        Route::post('media/external', [MediaController::class, 'storeExternal'])->name('media.external');
        Route::post('media/{media}/duplicate', [MediaController::class, 'duplicate'])->name('media.duplicate');
        Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
        Route::get('media/{media}/file', [MediaFileController::class, 'show'])->name('media.file');
        Route::get('media/{media}/thumbnail', [MediaFileController::class, 'thumbnail'])->name('media.thumbnail');

        Route::post('media-folders', [MediaFolderController::class, 'store'])->name('media-folders.store');
        Route::patch('media-folders/{mediaFolder}', [MediaFolderController::class, 'update'])->name('media-folders.update');
        Route::delete('media-folders/{mediaFolder}', [MediaFolderController::class, 'destroy'])->name('media-folders.destroy');

        Route::post('widgets/resolve', WidgetResolveController::class)
            ->middleware('throttle:60,1')
            ->name('widgets.resolve');

        Route::get('designs', [DesignController::class, 'index'])->name('designs.index');
        Route::post('designs', [DesignController::class, 'store'])->name('designs.store');
        Route::get('designs/{design}', [DesignController::class, 'show'])->name('designs.show');
        Route::get('designs/{design}/edit', [DesignController::class, 'edit'])->name('designs.edit');
        Route::patch('designs/{design}', [DesignController::class, 'update'])->name('designs.update');
        Route::post('designs/{design}/duplicate', [DesignController::class, 'duplicate'])->name('designs.duplicate');
        Route::post('designs/{design}/revisions/{revision}/restore', [DesignController::class, 'restore'])->name('designs.revisions.restore');
        Route::delete('designs/{design}', [DesignController::class, 'destroy'])->name('designs.destroy');

        Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::post('templates/from-design/{design}', [TemplateController::class, 'storeFromDesign'])->name('templates.from-design');
        Route::get('templates/{template}', [TemplateController::class, 'show'])->name('templates.show');
        Route::get('templates/{template}/thumbnail', [TemplateController::class, 'thumbnail'])->name('templates.thumbnail');
        Route::get('templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
        Route::patch('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
        Route::post('templates/{template}/duplicate', [TemplateController::class, 'duplicate'])->name('templates.duplicate');
        Route::post('templates/{template}/instantiate', [TemplateController::class, 'instantiate'])->name('templates.instantiate');
        Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');

        Route::get('playlists', [PlaylistController::class, 'index'])->name('playlists.index');
        Route::post('playlists', [PlaylistController::class, 'store'])->name('playlists.store');
        Route::get('playlists/{playlist}', [PlaylistController::class, 'show'])->name('playlists.show');
        Route::get('playlists/{playlist}/edit', [PlaylistController::class, 'edit'])->name('playlists.edit');
        Route::patch('playlists/{playlist}', [PlaylistController::class, 'update'])->name('playlists.update');
        Route::post('playlists/{playlist}/duplicate', [PlaylistController::class, 'duplicate'])->name('playlists.duplicate');
        Route::post('playlists/{playlist}/revisions/{revision}/restore', [PlaylistController::class, 'restore'])->name('playlists.revisions.restore');
        Route::delete('playlists/{playlist}', [PlaylistController::class, 'destroy'])->name('playlists.destroy');

        Route::get('channels', [ChannelController::class, 'index'])->name('channels.index');
        Route::post('channels', [ChannelController::class, 'store'])->name('channels.store');
        Route::get('channels/{channel}', [ChannelController::class, 'show'])->name('channels.show');
        Route::get('channels/{channel}/edit', [ChannelController::class, 'edit'])->name('channels.edit');
        Route::patch('channels/{channel}', [ChannelController::class, 'update'])->name('channels.update');
        Route::post('channels/{channel}/duplicate', [ChannelController::class, 'duplicate'])->name('channels.duplicate');
        Route::delete('channels/{channel}', [ChannelController::class, 'destroy'])->name('channels.destroy');

        Route::get('emergencies', [EmergencyController::class, 'index'])->name('emergencies.index');
        Route::post('emergencies', [EmergencyController::class, 'store'])->name('emergencies.store');
        Route::get('emergencies/{emergency}', [EmergencyController::class, 'show'])->name('emergencies.show');
        Route::patch('emergencies/{emergency}', [EmergencyController::class, 'update'])->name('emergencies.update');
        Route::post('emergencies/{emergency}/start', [EmergencyController::class, 'start'])->middleware('impersonation.deny')->name('emergencies.start');
        Route::post('emergencies/{emergency}/stop', [EmergencyController::class, 'stop'])->middleware('impersonation.deny')->name('emergencies.stop');
        Route::get('schedules', [ScheduleController::class, 'index'])->name('schedules.index');
        Route::post('schedules', [ScheduleController::class, 'store'])->name('schedules.store');
        Route::patch('schedules/{schedule}', [ScheduleController::class, 'update'])->name('schedules.update');
        Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');

        require __DIR__.'/queue.php';
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
