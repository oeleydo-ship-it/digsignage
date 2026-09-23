<?php

use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Teams\TeamMemberController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->middleware('impersonation.deny')->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware(['throttle:6,1', 'impersonation.deny'])
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::get('settings/api', [IntegrationController::class, 'edit'])->name('integrations.edit');
    Route::post('settings/api/tokens', [IntegrationController::class, 'storeToken'])->middleware(['throttle:6,1', 'impersonation.deny'])->name('integrations.tokens.store');
    Route::post('settings/api/tokens/{apiToken}/rotate', [IntegrationController::class, 'rotateToken'])->middleware(['throttle:6,1', 'impersonation.deny'])->name('integrations.tokens.rotate');
    Route::delete('settings/api/tokens/{apiToken}', [IntegrationController::class, 'destroyToken'])->middleware('impersonation.deny')->name('integrations.tokens.destroy');
    Route::post('settings/api/webhooks', [IntegrationController::class, 'storeWebhook'])->middleware(['throttle:6,1', 'impersonation.deny'])->name('integrations.webhooks.store');
    Route::post('settings/api/webhooks/{webhookEndpoint}/rotate', [IntegrationController::class, 'rotateWebhook'])->middleware(['throttle:6,1', 'impersonation.deny'])->name('integrations.webhooks.rotate');
    Route::patch('settings/api/webhooks/{webhookEndpoint}', [IntegrationController::class, 'updateWebhook'])->middleware('impersonation.deny')->name('integrations.webhooks.update');
    Route::delete('settings/api/webhooks/{webhookEndpoint}', [IntegrationController::class, 'destroyWebhook'])->middleware('impersonation.deny')->name('integrations.webhooks.destroy');

    Route::get('settings/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('settings/teams', [TeamController::class, 'store'])->name('teams.store');

    Route::middleware(EnsureTeamMembership::class)->group(function () {
        Route::get('settings/teams/{team}', [TeamController::class, 'edit'])->name('teams.edit');
        Route::patch('settings/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::delete('settings/teams/{team}', [TeamController::class, 'destroy'])->middleware('impersonation.deny')->name('teams.destroy');
        Route::post('settings/teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');
        Route::delete('settings/teams/{team}/leave', [TeamController::class, 'leave'])->name('teams.leave');

        Route::patch('settings/teams/{team}/members/{user}', [TeamMemberController::class, 'update'])->name('teams.members.update');
        Route::delete('settings/teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');

        Route::post('settings/teams/{team}/invitations', [TeamInvitationController::class, 'store'])->name('teams.invitations.store');
        Route::delete('settings/teams/{team}/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('teams.invitations.destroy');
    });
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
