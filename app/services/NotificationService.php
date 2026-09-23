<?php
/** Durable in-app notifications. */
class NotificationService
{
    public static function dispatchIfDue(int $throttleSeconds = 60): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $lockPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'renewal-notifier.lock';
        $lockHandle = @fopen($lockPath, 'c+');
        if ($lockHandle === false || !@flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if (is_resource($lockHandle)) {
                fclose($lockHandle);
            }
            return;
        }

        try {
            $lastRun = (int) trim((string) stream_get_contents($lockHandle));
            if ($lastRun > 0 && (time() - $lastRun) < $throttleSeconds) {
                return;
            }

            ftruncate($lockHandle, 0);
            rewind($lockHandle);
            fwrite($lockHandle, (string) time());
            fflush($lockHandle);
            self::sendRenewalReminders();
        } catch (Throwable $exception) {
            error_log('Automatic renewal notification dispatch error: ' . $exception->getMessage());
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    public static function sendRenewalReminders(): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'details' => []];
        $db = Database::getInstance();
        try {
            $noticeDays = (int) $db->query(
                "SELECT expiration_notice_lead_days
                 FROM fee_settings
                 WHERE id = 1
                 LIMIT 1"
            )->fetchColumn();
            $noticeDays = $noticeDays >= 1 && $noticeDays <= 30 ? $noticeDays : 1;
            $noticeHours = $noticeDays * 24;
            $junkshops = $db->query(
                "SELECT a.id AS account_id, a.email, a.full_name, jp.business_name,
                        jp.partnership_expires_at, jp.last_expiration_notice_sent
                 FROM accounts a
                 JOIN junkshop_profiles jp ON jp.account_id = a.id
                 JOIN roles r ON r.id = a.role_id
                 WHERE r.name = 'junkshop' AND jp.approval_status = 'approved'
                   AND a.email IS NOT NULL AND a.email <> ''
                   AND jp.partnership_expires_at > CURRENT_TIMESTAMP
                   AND jp.partnership_expires_at <= DATE_ADD(CURRENT_TIMESTAMP, INTERVAL {$noticeHours} HOUR)
                   AND (jp.last_expiration_notice_sent IS NULL
                        OR jp.last_expiration_notice_sent < DATE_SUB(jp.partnership_expires_at, INTERVAL {$noticeHours} HOUR))"
            )->fetchAll();
        } catch (Throwable $exception) {
            $results['failed']++;
            $results['details'][] = 'Renewal notification query failed: ' . $exception->getMessage();
            error_log('Renewal notification query error: ' . $exception->getMessage());
            return $results;
        }

        foreach ($junkshops as $junkshop) {
            $expiry = (string) $junkshop['partnership_expires_at'];
            $accountId = (int) $junkshop['account_id'];
            try {
                try {
                    $db->query(
                        'INSERT INTO renewal_notification_log (junkshop_account_id, partnership_expires_at)
                         VALUES (:account_id, :expires_at)
                         ON DUPLICATE KEY UPDATE sent_at = sent_at',
                        ['account_id' => $accountId, 'expires_at' => $expiry]
                    );
                } catch (Throwable $claimException) {
                    $results['details'][] = 'Tracking claim failed for ' . $junkshop['email'] . '; email delivery will continue: ' . $claimException->getMessage();
                    error_log('Renewal notification claim error: ' . $claimException->getMessage());
                }
                $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $expiry, new DateTimeZone(APP_TIMEZONE));
                $displayExpiry = $date ? $date->format('F j, Y g:i A T') : $expiry;
                $mailResult = MailerService::sendRenewalNotice(
                    (string) $junkshop['email'],
                    (string) ($junkshop['full_name'] ?: $junkshop['business_name']),
                    $displayExpiry
                );
                if (!$mailResult['sent']) {
                    $errorMessage = (string) ($mailResult['error'] ?? 'Unknown SMTP delivery error.');
                    $results['failed']++;
                    $results['details'][] = 'Failed sending notice to ' . $junkshop['email'] . ' (' . $junkshop['business_name'] . '): ' . $errorMessage;
                    try {
                        $db->query(
                            'DELETE FROM renewal_notification_log
                             WHERE junkshop_account_id = :account_id AND partnership_expires_at = :expires_at',
                            ['account_id' => $accountId, 'expires_at' => $expiry]
                        );
                    } catch (Throwable $logException) {
                        error_log('Renewal notification claim cleanup error: ' . $logException->getMessage());
                    }
                    continue;
                }

                $results['sent']++;
                $results['details'][] = 'Sent notice to ' . $junkshop['email'] . ' (' . $junkshop['business_name'] . ').';

                try {
                    $db->query(
                        'UPDATE junkshop_profiles
                         SET last_expiration_notice_sent = CURRENT_TIMESTAMP
                         WHERE account_id = :account_id AND partnership_expires_at = :expires_at',
                        ['account_id' => $accountId, 'expires_at' => $expiry]
                    );
                } catch (Throwable $trackingException) {
                    $results['details'][] = 'Email sent, but notice timestamp was not saved for ' . $junkshop['email'] . ': ' . $trackingException->getMessage();
                    error_log('Renewal notification timestamp error: ' . $trackingException->getMessage());
                }

                $message = "Your EcoPick subscription expires on {$displayExpiry}. Please log in and renew before this date.";
                try {
                    $db->query(
                        'INSERT INTO notifications (recipient_account_id, notification_type, title, message, link_url) VALUES (:account_id, :type, :title, :message, :link_url)',
                        [
                            'account_id' => $accountId,
                            'type' => 'renewal_notice',
                            'title' => 'Partnership renewal reminder',
                            'message' => $message,
                            'link_url' => APP_URL . '/user-junkshop/renewal.php',
                        ]
                    );
                } catch (Throwable $notificationException) {
                    $results['details'][] = 'Email sent, but in-app notification was not saved for ' . $junkshop['email'] . ': ' . $notificationException->getMessage();
                    error_log('Renewal in-app notification error: ' . $notificationException->getMessage());
                }
            } catch (Throwable $exception) {
                $results['failed']++;
                $results['details'][] = 'Renewal notice error for ' . ($junkshop['email'] ?? 'unknown recipient') . ': ' . $exception->getMessage();
                error_log('Renewal reminder error: ' . $exception->getMessage());
            }
        }

        return $results;
    }

    public static function notifyBookingStatus(int $pickupRequestId, ?string $newStatus, string $responsibleParty): void
    {
        $status = trim((string) $newStatus);
        if ($pickupRequestId <= 0 || $status === '') {
            return;
        }

        try {
            $db = Database::getInstance();
            $booking = $db->query(
                'SELECT pr.booking_reference, pr.seller_account_id, pr.junkshop_id, pr.pickup_address, pr.confirmed_pickup_date, pr.confirmed_pickup_time, seller.email AS seller_email, seller.full_name AS seller_name, junkshop.email AS junkshop_email, junkshop.full_name AS junkshop_name, COALESCE(jp.business_name, junkshop.full_name) AS junkshop_display_name, COALESCE(t.final_seller_amount, pr.final_amount_paid, 0) AS final_seller_amount, COALESCE(t.final_recyclable_value, pr.final_recyclable_value, 0) AS final_recyclable_value FROM pickup_requests pr JOIN accounts seller ON seller.id = pr.seller_account_id LEFT JOIN accounts junkshop ON junkshop.id = pr.junkshop_id LEFT JOIN junkshop_profiles jp ON jp.account_id = pr.junkshop_id LEFT JOIN transactions t ON t.pickup_request_id = pr.id WHERE pr.id = :pickup_request_id ORDER BY t.id DESC LIMIT 1',
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
        $address = trim((string) ($booking['pickup_address'] ?? ''));
        return $address;
    }

    private static function formatDate(?string $date): string
    {
        if (!$date) {
            return 'the confirmed date';
        }

        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone(APP_TIMEZONE));
        return $parsedDate ? $parsedDate->format('M d, Y') : 'the confirmed date';
    }

    private static function formatTime(?string $time): string
    {
        if (!$time) {
            return 'the confirmed time';
        }

        $timezone = new DateTimeZone(APP_TIMEZONE);
        $parsedTime = DateTimeImmutable::createFromFormat('!H:i:s', $time, $timezone)
            ?: DateTimeImmutable::createFromFormat('!H:i', $time, $timezone)
            ?: DateTimeImmutable::createFromFormat('!g:i A', strtoupper($time), $timezone);

        return $parsedTime ? $parsedTime->format('g:i A') : 'the confirmed time';
    }
}