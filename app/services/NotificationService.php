<?php
/** Durable in-app notifications. */
class NotificationService
{
    public static function notifyBookingStatus(int $pickupRequestId, ?string $newStatus, string $responsibleParty): void
    {
        $status = trim((string) $newStatus);
        if ($pickupRequestId <= 0 || $status === '') {
            return;
        }

        try {
            $db = Database::getInstance();
            $booking = $db->query(
                'SELECT pr.booking_reference, pr.seller_account_id, pr.junkshop_id, pr.barangay, pr.pickup_address, pr.confirmed_pickup_date, pr.confirmed_pickup_time, seller.email AS seller_email, seller.full_name AS seller_name, junkshop.email AS junkshop_email, junkshop.full_name AS junkshop_name, COALESCE(jp.business_name, junkshop.full_name) AS junkshop_display_name, COALESCE(t.final_seller_amount, pr.final_amount_paid, 0) AS final_seller_amount, COALESCE(t.final_recyclable_value, pr.final_recyclable_value, 0) AS final_recyclable_value FROM pickup_requests pr JOIN accounts seller ON seller.id = pr.seller_account_id LEFT JOIN accounts junkshop ON junkshop.id = pr.junkshop_id LEFT JOIN junkshop_profiles jp ON jp.account_id = pr.junkshop_id LEFT JOIN transactions t ON t.pickup_request_id = pr.id WHERE pr.id = :pickup_request_id ORDER BY t.id DESC LIMIT 1',
                ['pickup_request_id' => $pickupRequestId]
            )->fetch();

            if (!$booking) {
                return;
            }

            $notifications = self::buildNotifications($booking, $status, $responsibleParty);
            foreach ($notifications as $notification) {
                $db->query(
                    'INSERT INTO notifications (recipient_account_id, notification_type, title, message, link_url, related_pickup_request_id) VALUES (:recipient_account_id, :notification_type, :title, :message, :link_url, :pickup_request_id)',
                    [
                        'recipient_account_id' => $notification['account_id'],
                        'notification_type' => 'status_update',
                        'title' => $notification['title'],
                        'message' => $notification['message'],
                        'link_url' => APP_URL . '/user-junkshop/booking-details.php?id=' . $pickupRequestId,
                        'pickup_request_id' => $pickupRequestId,
                    ]
                );

                if (!empty($notification['email'])) {
                    MailerService::sendBookingStatus(
                        (string) $notification['email'],
                        (string) $notification['name'],
                        $pickupRequestId,
                        $status
                    );
                }
            }
        } catch (Throwable $e) {
            error_log($e->getMessage());
        }
    }

    private static function buildNotifications(array $booking, string $status, string $responsibleParty): array
    {
        $bookingReference = (string) ($booking['booking_reference'] ?? '');
        $sellerName = (string) ($booking['seller_name'] ?? 'Seller');
        $junkshopName = (string) ($booking['junkshop_display_name'] ?? $booking['junkshop_name'] ?? 'Junkshop');
        $date = self::formatDate($booking['confirmed_pickup_date'] ?? null);
        $time = self::formatTime($booking['confirmed_pickup_time'] ?? null);
        $notifications = [];

        if ($status === 'Pending Request') {
            $notifications[] = self::notification($booking, 'seller', 'Pickup request submitted', "Your pickup request {$bookingReference} has been submitted and is pending junkshop review.");
            $notifications[] = self::notification($booking, 'junkshop', 'New pickup request', 'New pickup request received from ' . $sellerName . ' for ' . self::location($booking) . '.');
        } elseif ($status === 'Accepted') {
            $notifications[] = self::notification($booking, 'seller', 'Pickup request accepted', "{$junkshopName} has accepted your pickup request {$bookingReference}.");
            $notifications[] = self::notification($booking, 'junkshop', 'Pickup request accepted', "You accepted the pickup request {$bookingReference} from {$sellerName}.");
        } elseif ($status === 'Scheduled') {
            $notifications[] = self::notification($booking, 'seller', 'Pickup scheduled', "Pickup scheduled! {$junkshopName} confirmed your pickup for {$date} at {$time}.");
            $notifications[] = self::notification($booking, 'junkshop', 'Pickup scheduled', "Pickup scheduled for request {$bookingReference} with {$sellerName} on {$date} at {$time}.");
        } elseif ($status === 'For Pickup') {
            $notifications[] = self::notification($booking, 'seller', 'Pickup in progress', "{$junkshopName} is en route / ready for your pickup request {$bookingReference}.");
            $notifications[] = self::notification($booking, 'junkshop', 'Pickup status updated', "Pickup status updated to 'For Pickup' for request {$bookingReference}.");
        } elseif ($status === 'Completed') {
            $finalAmount = number_format((float) ($booking['final_seller_amount'] ?? 0), 2);
            $recyclableValue = number_format((float) ($booking['final_recyclable_value'] ?? 0), 2);
            $notifications[] = self::notification($booking, 'seller', 'Transaction completed', "Transaction completed! Final net amount: ₱{$finalAmount}.");
            $notifications[] = self::notification($booking, 'junkshop', 'Transaction completed', "Transaction completed for {$sellerName}. Total Recyclable Value: ₱{$recyclableValue}.");
        } elseif ($status === 'Declined') {
            $notifications[] = self::notification($booking, 'seller', 'Pickup request declined', "Your pickup request {$bookingReference} was declined by {$junkshopName}.");
        } elseif ($status === 'Cancelled by Seller' || ($status === 'Cancelled' && strcasecmp($responsibleParty, 'Seller') === 0)) {
            $notifications[] = self::notification($booking, 'junkshop', 'Pickup request cancelled', "{$sellerName} cancelled pickup request {$bookingReference}.");
        }

        return array_values(array_filter($notifications, static fn (array $notification): bool => $notification['account_id'] > 0));
    }

    private static function notification(array $booking, string $role, string $title, string $message): array
    {
        $isSeller = $role === 'seller';
        return [
            'account_id' => (int) ($booking[$isSeller ? 'seller_account_id' : 'junkshop_id'] ?? 0),
            'email' => (string) ($booking[$isSeller ? 'seller_email' : 'junkshop_email'] ?? ''),
            'name' => (string) ($booking[$isSeller ? 'seller_name' : 'junkshop_name'] ?? ''),
            'title' => $title,
            'message' => $message,
        ];
    }

    private static function location(array $booking): string
    {
        $barangay = trim((string) ($booking['barangay'] ?? ''));
        $address = trim((string) ($booking['pickup_address'] ?? ''));
        return $barangay !== '' && $address !== '' ? "{$barangay}, {$address}" : ($barangay !== '' ? $barangay : $address);
    }

    private static function formatDate(?string $date): string
    {
        $timestamp = $date ? strtotime($date) : false;
        return $timestamp ? date('M d, Y', $timestamp) : 'the confirmed date';
    }

    private static function formatTime(?string $time): string
    {
        $timestamp = $time ? strtotime($time) : false;
        return $timestamp ? date('g:i A', $timestamp) : 'the confirmed time';
    }
}