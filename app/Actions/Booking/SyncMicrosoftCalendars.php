<?php

namespace App\Actions\Booking;

use App\Enums\PlanFeature;
use App\Enums\RoomBookingSource;
use App\Enums\RoomBookingStatus;
use App\Models\CalendarConnection;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Services\Calendar\MicrosoftGraphClient;
use App\Services\Calendar\MicrosoftGraphException;
use App\Support\TeamQuota;
use Carbon\CarbonImmutable;

/**
 * Pull room calendars from Microsoft 365 into local bookings.
 *
 * Meetings booked in Outlook appear on room screens and block the booking
 * form; meetings moved or cancelled in Outlook are updated here too. The
 * window covers yesterday through the next two weeks.
 */
class SyncMicrosoftCalendars
{
    public const DAYS_AHEAD = 14;

    public function __construct(protected RefreshRoomScreens $refreshScreens) {}

    /**
     * Sync every linked room on a connection.
     *
     * @return array{rooms: int, created: int, updated: int, cancelled: int}
     */
    public function handle(CalendarConnection $connection): array
    {
        $totals = ['rooms' => 0, 'created' => 0, 'updated' => 0, 'cancelled' => 0];

        $connection->loadMissing('team');

        if (! $connection->is_active
            || ! $connection->isConfigured()
            || ! app(TeamQuota::class)->allowsFeature($connection->team, PlanFeature::RoomBooking)) {
            return $totals;
        }

        $rooms = MeetingRoom::query()
            ->where('calendar_connection_id', $connection->id)
            ->whereNotNull('external_calendar_id')
            ->where('is_active', true)
            ->get();

        try {
            foreach ($rooms as $room) {
                $room->setRelation('calendarConnection', $connection);
                $result = $this->syncRoom($room);
                $totals['rooms']++;
                $totals['created'] += $result['created'];
                $totals['updated'] += $result['updated'];
                $totals['cancelled'] += $result['cancelled'];
            }
        } catch (MicrosoftGraphException $exception) {
            $connection->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 500)])->save();

            throw $exception;
        }

        $connection->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();

        if ($totals['created'] + $totals['updated'] + $totals['cancelled'] > 0) {
            $this->refreshScreens->handle($connection->team_id);
        }

        return $totals;
    }

    /**
     * @return array{created: int, updated: int, cancelled: int}
     */
    public function syncRoom(MeetingRoom $room): array
    {
        $from = CarbonImmutable::now()->subDay()->startOfDay();
        $to = CarbonImmutable::now()->addDays(self::DAYS_AHEAD)->endOfDay();
        $counts = ['created' => 0, 'updated' => 0, 'cancelled' => 0];

        $events = MicrosoftGraphClient::for($room->calendarConnection)
            ->calendarView((string) $room->external_calendar_id, $from, $to);

        $seen = [];

        foreach ($events as $event) {
            if ($event['cancelled'] || $event['free']) {
                continue;
            }

            $seen[] = $event['id'];
            $booking = RoomBooking::query()
                ->where('meeting_room_id', $room->id)
                ->where('external_id', $event['id'])
                ->first();

            if ($booking === null) {
                RoomBooking::query()->create([
                    'team_id' => $room->team_id,
                    'meeting_room_id' => $room->id,
                    'title' => $event['subject'],
                    'organizer_name' => $event['organizer_name'],
                    'organizer_email' => $event['organizer_email'],
                    'starts_at' => $event['starts_at'],
                    'ends_at' => $event['ends_at'],
                    'status' => RoomBookingStatus::Confirmed,
                    'source' => RoomBookingSource::Microsoft365,
                    'external_id' => $event['id'],
                    'external_change_key' => $event['change_key'],
                ]);
                $counts['created']++;

                continue;
            }

            if ($booking->external_change_key !== null && $booking->external_change_key === $event['change_key']) {
                continue;
            }

            $changed = ! $booking->starts_at->equalTo($event['starts_at'])
                || ! $booking->ends_at->equalTo($event['ends_at'])
                || $booking->title !== $event['subject'];

            $booking->forceFill([
                'title' => $event['subject'],
                'starts_at' => $event['starts_at'],
                'ends_at' => $event['ends_at'],
                'external_change_key' => $event['change_key'],
                'sync_error' => null,
            ]);

            // An Outlook meeting that was declined here but still exists
            // there stays declined; everything else follows Outlook.
            if ($booking->status === RoomBookingStatus::Cancelled && $booking->source === RoomBookingSource::Microsoft365) {
                $booking->status = RoomBookingStatus::Confirmed;
                $booking->cancelled_at = null;
                $changed = true;
            }

            $booking->save();

            if ($changed) {
                $counts['updated']++;
            }
        }

        // Anything we mirrored that Outlook no longer has was cancelled there.
        $counts['cancelled'] = RoomBooking::query()
            ->where('meeting_room_id', $room->id)
            ->whereNotNull('external_id')
            ->whereNotIn('external_id', $seen === [] ? [''] : $seen)
            ->blocking()
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->update([
                'status' => RoomBookingStatus::Cancelled->value,
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);

        return $counts;
    }

    /**
     * Sync every active connection, recording failures instead of stopping.
     *
     * @return array{connections: int, failed: int}
     */
    public function all(): array
    {
        $summary = ['connections' => 0, 'failed' => 0];

        CalendarConnection::query()
            ->where('provider', CalendarConnection::MICROSOFT_365)
            ->where('is_active', true)
            ->get()
            ->each(function (CalendarConnection $connection) use (&$summary): void {
                $summary['connections']++;

                try {
                    $this->handle($connection);
                } catch (MicrosoftGraphException) {
                    $summary['failed']++;
                }
            });

        return $summary;
    }
}
