<?php

use App\Http\Controllers\Platform\AnnouncementController;
use App\Http\Controllers\Platform\AuditController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\FeatureFlagController;
use App\Http\Controllers\Platform\HealthController;
use App\Http\Controllers\Platform\ImpersonationController;
use App\Http\Controllers\Platform\JobController;
use App\Http\Controllers\Platform\OrganizationController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\PlatformSettingsController;
use App\Http\Controllers\Platform\ReleaseController;
use App\Http\Controllers\Platform\ScreenController;
use App\Http\Controllers\Platform\StorageController;
use App\Http\Controllers\Platform\SubscriptionController;
use App\Http\Controllers\Platform\TemplateController;
use App\Http\Controllers\Platform\UsageController;
use App\Http\Controllers\Platform\UserController;
use App\Http\Middleware\EnsurePlatformAdmin;
use Illuminate\Support\Facades\Route;

Route::post('platform/impersonation/stop', [ImpersonationController::class, 'stop'])
    ->middleware(['auth'])
    ->name('platform.impersonation.stop');

Route::prefix('platform')
    ->name('platform.')
    ->middleware(['auth', 'verified', EnsurePlatformAdmin::class])
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('organizations/{team}', [OrganizationController::class, 'show'])->name('organizations.show');
        Route::patch('organizations/{team}', [OrganizationController::class, 'update'])->name('organizations.update');
        Route::post('organizations/{team}/suspend', [OrganizationController::class, 'suspend'])->name('organizations.suspend');
        Route::post('organizations/{team}/restore', [OrganizationController::class, 'restore'])->name('organizations.restore');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');

        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
        Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
        Route::patch('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        Route::get('screens', [ScreenController::class, 'index'])->name('screens.index');
        Route::get('usage', [UsageController::class, 'index'])->name('usage.index');

        Route::get('storage', [StorageController::class, 'index'])->name('storage.index');
        Route::post('storage', [StorageController::class, 'store'])->name('storage.store');
        Route::patch('storage/{storage_disk}', [StorageController::class, 'update'])->name('storage.update');
        Route::post('storage/{storage_disk}/test', [StorageController::class, 'test'])->name('storage.test');
        Route::delete('storage/{storage_disk}', [StorageController::class, 'destroy'])->name('storage.destroy');
        Route::patch('storage/organizations/{team}', [StorageController::class, 'assign'])->name('storage.assign');
        Route::get('health', [HealthController::class, 'index'])->name('health.index');

        Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
        Route::post('jobs/{uuid}/retry', [JobController::class, 'retry'])->name('jobs.retry');
        Route::delete('jobs/{uuid}', [JobController::class, 'destroy'])->name('jobs.destroy');

        Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::get('feature-flags', [FeatureFlagController::class, 'index'])->name('feature-flags.index');
        Route::patch('feature-flags/{feature_flag}', [FeatureFlagController::class, 'update'])->name('feature-flags.update');

        Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

        Route::get('audits', [AuditController::class, 'index'])->name('audits.index');

        Route::get('settings', [PlatformSettingsController::class, 'index'])->name('settings.index');
        Route::put('settings/general', [PlatformSettingsController::class, 'updateGeneral'])->name('settings.general');
        Route::post('settings/branding', [PlatformSettingsController::class, 'updateBranding'])->name('settings.branding');
        Route::put('settings/payments', [PlatformSettingsController::class, 'updatePayments'])->name('settings.payments');
        Route::post('settings/payments/test', [PlatformSettingsController::class, 'testPayments'])->middleware('throttle:10,1')->name('settings.payments.test');
        Route::put('settings/mail', [PlatformSettingsController::class, 'updateMail'])->name('settings.mail');
        Route::post('settings/mail/test', [PlatformSettingsController::class, 'testMail'])->middleware('throttle:5,1')->name('settings.mail.test');

        Route::get('updates', [ReleaseController::class, 'index'])->name('updates.index');
        Route::post('updates/upload', [ReleaseController::class, 'upload'])->middleware('throttle:10,1')->name('updates.upload');
        Route::post('updates/github', [ReleaseController::class, 'installFromGitHub'])->middleware('throttle:10,1')->name('updates.github');
        Route::post('updates/{release}/install', [ReleaseController::class, 'install'])->middleware('throttle:10,1')->name('updates.install');
        Route::post('updates/{release}/rollback', [ReleaseController::class, 'rollback'])->middleware('throttle:10,1')->name('updates.rollback');
        Route::delete('updates/{release}', [ReleaseController::class, 'destroy'])->name('updates.destroy');
        Route::get('updates/{release}/log', [ReleaseController::class, 'log'])->name('updates.log');
    });
