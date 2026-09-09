<?php
/** Durable in-app notifications. */
class NotificationService
{
    public static function notifyBookingStatus(int $pickupRequestId, ?string $newStatus, string $responsibleParty): void
    {
        if ($pickupRequestId <= 0 || trim((string) $newStatus) === '') {
            return;
        }

        $db = Database::getInstance();
        $recipients = $db->query(
            'SELECT pr.seller_account_id, seller.email AS seller_email, seller.full_name AS seller_name, ja.junkshop_id, junkshop.email AS junkshop_email, junkshop.full_name AS junkshop_name FROM pickup_requests pr JOIN accounts seller ON seller.id = pr.seller_account_id LEFT JOIN junkshop_assignments ja ON ja.pickup_request_id = pr.id AND ja.status IN (\'Matched\', \'Accepted\') LEFT JOIN accounts junkshop ON junkshop.id = ja.junkshop_id WHERE pr.id = :pickup_request_id',
            ['pickup_request_id' => $pickupRequestId]
        )->fetchAll();

        $accountIds = [];
        foreach ($recipients as $recipient) {
            foreach (['seller_account_id', 'junkshop_id'] as $key) {
                $accountId = (int) ($recipient[$key] ?? 0);
                if ($accountId > 0) {
                    $accountIds[$accountId] = true;
                }
            }
        }

        $title = 'Booking status updated';
        $message = 'Your booking is now ' . trim((string) $newStatus) . '.';
        foreach (array_keys($accountIds) as $accountId) {
            $db->query(
                'INSERT INTO notifications (recipient_account_id, notification_type, title, message, link_url, related_pickup_request_id) VALUES (:recipient_account_id, :notification_type, :title, :message, :link_url, :pickup_request_id)',
                [
                    'recipient_account_id' => $accountId,
                    'notification_type' => 'status_update',
                    'title' => $title,
                    'message' => $message,
                    'link_url' => APP_URL . '/user-junkshop/booking-details.php?id=' . $pickupRequestId,
                    'pickup_request_id' => $pickupRequestId,
                ]
            );

            foreach ($recipients as $recipient) {
                $emailKey = $accountId === (int) ($recipient['seller_account_id'] ?? 0) ? 'seller_email' : 'junkshop_email';
                $nameKey = $accountId === (int) ($recipient['seller_account_id'] ?? 0) ? 'seller_name' : 'junkshop_name';
                if (!empty($recipient[$emailKey])) {
                    MailerService::sendBookingStatus(
                        (string) $recipient[$emailKey],
                        (string) ($recipient[$nameKey] ?? ''),
                        $pickupRequestId,
                        trim((string) $newStatus)
                    );
                    break;
                }
            }
        }
    }
}