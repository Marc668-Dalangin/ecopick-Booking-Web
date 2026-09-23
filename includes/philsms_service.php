<?php

require_once __DIR__ . '/../config/philsms.php';

/**
 * Send an SMS through PhilSMS and return the transmission result.
 *
 * @return array{success: bool, status: string, message: string, recipient: string, http_code: int, curl_error: string, raw_response: string}
 */
function formatPhilippineMobileNumber(string $rawNumber): ?string
{
    $digits = preg_replace('/\D+/', '', trim($rawNumber));
    if (!is_string($digits)) {
        return null;
    }

    if (preg_match('/^639\d{9}$/', $digits)) {
        return $digits;
    }
    if (preg_match('/^63(9\d{9})$/', $digits, $matches)) {
        return '63' . $matches[1];
    }
    if (preg_match('/^09(\d{9})$/', $digits, $matches)) {
        return '639' . $matches[1];
    }
    if (preg_match('/^(\d{9})$/', $digits, $matches)) {
        return '639' . $matches[1];
    }
    if (preg_match('/^(9\d{9})$/', $digits, $matches)) {
        return '63' . $matches[1];
    }

    return null;
}

function buildPickupSmsMessage(string $bookingReference, string $collectorName, string $junkshopBusinessName, float $netAmount, string $collectorContactNumber): string
{
    $bookingReference = trim(preg_replace('/\s+/', ' ', $bookingReference) ?: '');
    $collectorName = trim(preg_replace('/\s+/', ' ', $collectorName) ?: '');
    $junkshopBusinessName = trim(preg_replace('/\s+/', ' ', $junkshopBusinessName) ?: '');
    $collectorContactNumber = trim($collectorContactNumber);

    return "EcoPick: Your booking {$bookingReference} is now For Pickup. Your recyclable materials will be collected by {$collectorName} from {$junkshopBusinessName}.\n"
        . 'Estimated Amount to receive: ₱' . number_format($netAmount, 2, '.', '') . ".\n"
        . "Note: The estimated amount may change. The final amount will be determined after the actual assessment and weighing of your recyclable materials.\n"
        . "Please have your materials ready for collection. For further inquiries regarding your pickup, please contact the collector, {$collectorName}, at {$collectorContactNumber}.\n"
        . 'Thank you for using EcoPick!';
}

function sendPhilSMS(string $mobileNumber, string $messageText, PDO $pdo): array
{
    $recipient = formatPhilippineMobileNumber($mobileNumber);
    if ($recipient === null) {
        $digits = preg_replace('/\D+/', '', trim($mobileNumber));
        return ['success' => false, 'status' => 'Failed', 'message' => 'The seller mobile number is not a valid Philippine mobile number.', 'recipient' => is_string($digits) ? $digits : ''];
    }

    $settings = $pdo->query('SELECT sms_enabled, philsms_api_token, philsms_endpoint, philsms_sender_id FROM fee_settings ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
    $smsEnabled = isset($settings['sms_enabled']) ? (int) $settings['sms_enabled'] : 1;
    if ($smsEnabled === 0) {
        return ['success' => true, 'status' => 'Disabled', 'message' => 'SMS dispatch disabled by Admin', 'recipient' => $recipient, 'http_code' => 0, 'curl_error' => '', 'raw_response' => '', 'bypassed' => true];
    }

    $apiToken = trim((string) ($settings['philsms_api_token'] ?? '')) ?: PHILSMS_API_TOKEN;
    $endpoint = trim((string) ($settings['philsms_endpoint'] ?? '')) ?: PHILSMS_ENDPOINT;
    $senderId = trim((string) ($settings['philsms_sender_id'] ?? '')) ?: PHILSMS_SENDER_ID;
    if (strcasecmp($senderId, 'ECOPICK') === 0) {
        $senderId = PHILSMS_SENDER_ID;
    }
    if (rtrim($endpoint, '/') === 'https://dashboard.philsms.com/api/v3') {
        $endpoint = 'https://dashboard.philsms.com/api/v3/sms/send';
    }

    if ($apiToken === '') {
        return ['success' => false, 'status' => 'Failed', 'message' => 'PhilSMS API token is not configured.', 'recipient' => $recipient, 'http_code' => 0, 'curl_error' => '', 'raw_response' => '', 'bypassed' => false];
    }
    if (!function_exists('curl_init')) {
        return ['success' => false, 'status' => 'Failed', 'message' => 'PHP cURL is not available.', 'recipient' => $recipient, 'http_code' => 0, 'curl_error' => '', 'raw_response' => '', 'bypassed' => false];
    }

    $curl = curl_init($endpoint);
    if ($curl === false) {
        return ['success' => false, 'status' => 'Failed', 'message' => 'Unable to initialize the PhilSMS cURL client.', 'recipient' => $recipient, 'http_code' => 0, 'curl_error' => 'curl_init failed', 'raw_response' => ''];
    }
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiToken, 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['recipient' => $recipient, 'sender_id' => $senderId, 'type' => 'plain', 'message' => $messageText], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);

    try {
        $responseBody = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    } catch (Throwable $exception) {
        $responseBody = false;
        $curlError = $exception->getMessage();
        $httpCode = 0;
    } finally {
        curl_close($curl);
    }

    $rawResponse = is_string($responseBody) ? trim($responseBody) : '';
    $response = $rawResponse !== '' ? json_decode($rawResponse, true) : null;
    $apiMessage = is_array($response) ? (string) ($response['message'] ?? $response['error'] ?? $response['detail'] ?? '') : '';
    $success = $responseBody !== false && $httpCode >= 200 && $httpCode < 300;
    if ($responseBody !== false && $httpCode >= 200 && $httpCode < 300 && is_array($response) && array_key_exists('success', $response)) {
        $success = (bool) $response['success'];
    }
    $responseStatus = is_array($response) ? strtolower((string) ($response['status'] ?? '')) : '';
    if (in_array($responseStatus, ['error', 'failed', 'failure'], true)) {
        $success = false;
    }
    if (!$success) {
        $details = [];
        if ($httpCode > 0) {
            $details[] = 'HTTP ' . $httpCode;
        }
        if ($curlError !== '') {
            $details[] = 'cURL: ' . $curlError;
        }
        if ($apiMessage !== '') {
            $details[] = $apiMessage;
        }
        if ($rawResponse !== '') {
            $details[] = 'Response: ' . $rawResponse;
        }
        $message = $details !== [] ? implode(' | ', $details) : 'PhilSMS rejected the SMS request.';
    } else {
        $message = 'SMS sent successfully.';
    }

    return ['success' => $success, 'status' => $success ? 'Sent' : 'Failed', 'message' => $message, 'recipient' => $recipient, 'http_code' => $httpCode, 'curl_error' => $curlError, 'raw_response' => $rawResponse, 'response' => $response, 'bypassed' => false];
}
