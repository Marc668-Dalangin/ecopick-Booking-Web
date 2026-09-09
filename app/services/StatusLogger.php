<?php
/**
 * Booking status audit trail service.
 */

class StatusLogger
{
    public static function logChange(
        int $pickupRequestId,
        ?string $previousStatus,
        string $newStatus,
        string $responsibleParty,
        ?int $userId = null
    ): void {
        if ($pickupRequestId <= 0 || trim($newStatus) === '' || trim($responsibleParty) === '') {
            throw new InvalidArgumentException('A booking id, new status, and responsible party are required.');
        }

        Database::getInstance()->query(
            'INSERT INTO booking_status_history (pickup_request_id, previous_status, new_status, responsible_party, user_id) VALUES (:pickup_request_id, :previous_status, :new_status, :responsible_party, :user_id)',
            [
                'pickup_request_id' => $pickupRequestId,
                'previous_status' => $previousStatus !== null ? trim($previousStatus) : null,
                'new_status' => trim($newStatus),
                'responsible_party' => trim($responsibleParty),
                'user_id' => $userId,
            ]
        );

        try {
            NotificationService::notifyBookingStatus($pickupRequestId, $newStatus, $responsibleParty);
        } catch (Throwable $exception) {
            error_log('Booking notification error: ' . $exception->getMessage());
        }
    }
}