<?php

use App\Http\Controllers\Booking\MeetingRoomController;
use App\Http\Controllers\Booking\MicrosoftCalendarController;
use App\Http\Controllers\Booking\RoomBookingController;
use Illuminate\Support\Facades\Route;

/*
| Room booking management, loaded inside the {current_team} route group.
*/

Route::middleware('plan.feature:room_booking')->group(function () {
    Route::get('bookings', [RoomBookingController::class, 'index'])->name('bookings.index');
    Route::post('bookings', [RoomBookingController::class, 'store'])->name('bookings.store');
    Route::patch('bookings/{roomBooking}', [RoomBookingController::class, 'update'])->name('bookings.update');
    Route::post('bookings/{roomBooking}/cancel', [RoomBookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('bookings/{roomBooking}/approve', [RoomBookingController::class, 'approve'])->name('bookings.approve');
    Route::post('bookings/{roomBooking}/decline', [RoomBookingController::class, 'decline'])->name('bookings.decline');

    Route::get('rooms', [MeetingRoomController::class, 'index'])->name('rooms.index');
    Route::post('rooms', [MeetingRoomController::class, 'store'])->name('rooms.store');
    Route::patch('rooms/{meetingRoom}', [MeetingRoomController::class, 'update'])->name('rooms.update');
    Route::delete('rooms/{meetingRoom}', [MeetingRoomController::class, 'destroy'])->name('rooms.destroy');
    Route::post('rooms/{meetingRoom}/rotate-link', [MeetingRoomController::class, 'rotateLink'])->name('rooms.rotate-link');

    Route::prefix('integrations/microsoft-365')->name('bookings.microsoft.')->group(function () {
        Route::get('/', [MicrosoftCalendarController::class, 'show'])->name('show');
        Route::put('/', [MicrosoftCalendarController::class, 'update'])->name('update');
        Route::delete('/', [MicrosoftCalendarController::class, 'destroy'])->name('destroy');
        Route::post('test', [MicrosoftCalendarController::class, 'test'])->middleware('throttle:10,1')->name('test');
        Route::get('rooms', [MicrosoftCalendarController::class, 'rooms'])->middleware('throttle:20,1')->name('rooms');
        Route::post('import', [MicrosoftCalendarController::class, 'import'])->name('import');
        Route::post('sync', [MicrosoftCalendarController::class, 'sync'])->middleware('throttle:10,1')->name('sync');
    });
});
