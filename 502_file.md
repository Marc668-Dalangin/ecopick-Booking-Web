# 502 Bad Gateway Application and Code-Level Audit

This document records a read-only audit of the five requested application-level 502 risk categories. No code changes or automatic fixes were applied.

## 1. PHP Session Lock Risks

### Finding 1.1: Shared session startup without immediate closure

- **File and line:** `app/core/Session.php:18`
- **Code pattern:**

```php
session_start();
```

- **Related bootstrap:** `app/bootstrap.php:81`

```php
Session::start();
```

- **Why this poses a 502 risk:** The shared session manager starts a PHP session for nearly every request. Session data remains locked until script shutdown unless `session_write_close()` is called. The bootstrap starts the session but does not close it immediately, so long database, email, or SMS operations can block concurrent requests using the same session.

### Finding 1.2: Synchronous SMS dispatch while the session-backed request is active

- **File and line:** `app/controllers/BookingLifecycleController.php:124-131`
- **Code pattern:**

```php
$smsResult = sendPhilSMS(
    (string) ($pickupRequest['seller_mobile'] ?? $pickupRequest['contact_number'] ?? ''),
    buildPickupSmsMessage(...),
    $this->db->getPDO()
);
```

- **Why this poses a 502 risk:** The external SMS request executes inline during a booking status transition. If the provider is slow, the PHP worker remains occupied while the user's session can remain locked. Concurrent requests from the same user may wait for the session lock and eventually hit the web server or reverse-proxy timeout.

### Finding 1.3: Registration OTP email sent while the session is active

- **File and line:** `app/controllers/RegistrationController.php:63`
- **Code pattern:**

```php
if (!MailerService::sendRegistrationOtp($data['email'], $data['full_name'], $otp)) {
```

- **Why this poses a 502 risk:** Registration email delivery is synchronous. A slow SMTP connection can keep the request and session lock open long enough to block other session requests or exceed the upstream timeout.

### Finding 1.4: OTP resend email sent while the session is active

- **File and line:** `user-junkshop/verify_otp.php:46`
- **Code pattern:**

```php
if (!MailerService::sendRegistrationOtp($pending['email'], $pending['full_name'], $otp)) {
```

- **Why this poses a 502 risk:** OTP resend performs synchronous email delivery while session data is still active. Repeated or slow SMTP requests can serialize requests for that session and contribute to upstream timeouts.

### Mitigating observation

Several AJAX endpoints explicitly close the session before expensive work, including:

- `admin/api/pickup-requests.php:8`
- `user-junkshop/api/get_matched_requests.php:49`
- `user-junkshop/api/junkshop-operations.php:59`

This protection is present on selected endpoints but is not universal.

## 2. Hanging External cURL / API Calls

### Finding 2.1: No explicit cURL connect timeout

- **File and line:** `includes/philsms_service.php:81-88`
- **Code pattern:**

```php
$curl = curl_init($endpoint);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
```

- **Why this poses a 502 risk:** The implementation has a 15-second total timeout, but no explicit `CURLOPT_CONNECTTIMEOUT`. DNS resolution or TCP connection establishment may consume the available request time unpredictably. A slow or unavailable PhilSMS connection can hold the PHP worker and, on session-authenticated paths, the session lock.

- **Severity qualification:** This is a conditional risk, not an unbounded cURL hang. `CURLOPT_TIMEOUT => 15` limits the complete request.

### Finding 2.2: cURL call is inline in a booking transition

- **File and line:** `app/controllers/BookingLifecycleController.php:124-131`
- **Code pattern:**

```php
$smsResult = sendPhilSMS(...);
```

- **Why this poses a 502 risk:** The cURL operation occurs before the booking response is returned. Multiple simultaneous status transitions can occupy PHP workers if the external SMS provider is slow or unavailable.

### Scope observation

The audit found cURL usage concentrated in `includes/philsms_service.php`. That service has a total timeout, so no clearly infinite external cURL call was detected.

## 3. High-Frequency or Unthrottled AJAX Polling

### Finding 3.1: One-second recurring timers

- **Files and lines:**
  - `user-junkshop/register-junkshop.php:561`
  - `user-junkshop/register-seller.php:450`
- **Code pattern:**

```javascript
cooldownInterval = setInterval(updateCooldown, 1000);
```

- **Why this poses a 502 risk:** These timers fire every second. However, they only update a local OTP countdown and do not perform network requests. They are therefore not a direct AJAX or 502 risk.

### Finding 3.2: Material refresh interval on partner-junkshops page

- **File and line:** `user-junkshop/partner-junkshops.php:711-714`
- **Code pattern:**

```javascript
setInterval(function () {
    activeJunkshops.forEach(function (junkshopId) {
        fetchLatestMaterials(junkshopId);
    });
}, 12000);
```

- **Why this was checked:** The interval performs network requests every 12 seconds. However, `fetchLatestMaterials()` checks `document.hidden` and prevents duplicate in-flight requests at `user-junkshop/partner-junkshops.php:666-667`. No material 502 risk was confirmed.

### Finding 3.3: Shared polling helper

- **File and lines:** `assets/js/live-updates.js:10-14`, `assets/js/live-updates.js:66-68`, `assets/js/live-updates.js:122`
- **Code pattern:**

```javascript
interval: Math.max(10000, Number(options.interval || 10000)),
```

```javascript
if (document.hidden || options.inFlight || (options.skipWhenEditing && shouldSkipRefresh())) {
    return;
}
```

```javascript
instance.timerId = window.setInterval(tick, normalized.interval);
```

- **Why this was checked:** The helper enforces a minimum 10-second interval and has visibility and in-flight guards. No high-frequency network polling defect was found here.

### Finding 3.4: Booking details polling

- **File and lines:** `user-junkshop/booking-details.php:338`, `user-junkshop/booking-details.php:358-359`
- **Code pattern:**

```javascript
if (!currentActiveBookingId || pollingInProgress || document.hidden) return;
```

```javascript
activeBookingInterval = setInterval(pollActiveBooking, 10000);
```

- **Why this was checked:** The poller runs every 10 seconds and prevents overlapping requests and hidden-tab requests. No direct 502 risk was confirmed.

### Overall finding

No AJAX polling interval below 10 seconds was found that also performs network I/O. The one-second intervals are local UI countdowns.

## 4. Slow or Unindexed Database Queries

### Finding 4.1: Unbounded pending-request matching loop with per-request work

- **File and lines:** `app/services/MatchingEngine.php:88-98`
- **Code pattern:**

```php
SELECT DISTINCT pr.id, pr.approximate_distance_km
FROM pickup_requests pr
JOIN accounts a ON a.id = pr.seller_account_id
JOIN pickup_request_items pri ON pri.pickup_request_id = pr.id AND pri.is_removed = 0
WHERE pr.current_status = 'Pending Request'
  AND a.account_status = 'active'
ORDER BY pr.created_at ASC
```

```php
foreach ($requests as $request) {
    $match = self::evaluateMatches($junkshopId, (int) $request['id']);
    ...
    self::saveMatch((int) $request['id'], $match);
}
```

- **Why this poses a 502 risk:** All pending requests are loaded, then matching work is performed for every candidate. `evaluateMatches()` and `saveMatch()` issue additional database operations per candidate. As request volume grows, one material-price update can become a long N+1-style operation and exceed PHP or proxy timeouts.

### Finding 4.2: Unbounded admin pending-request query

- **File and lines:** `app/controllers/PickupRequestController.php:475-490`
- **Code pattern:**

```sql
WHERE pr.current_status IN ('Pending Request', 'Pending', 'Matched')
GROUP BY pr.id ORDER BY pr.created_at ASC
```

```php
)->fetchAll();
```

- **Why this poses a 502 risk:** There is no `LIMIT` or pagination. The query performs joins, grouping, and `GROUP_CONCAT`; large request and material tables can cause expensive scans, sorting, memory usage, and response rendering.

### Finding 4.3: Entire unbounded result set rendered by the admin page

- **File and lines:** `admin/pending-pickup-requests.php:7`, `admin/pending-pickup-requests.php:12`
- **Code pattern:**

```php
$requests = (new PickupRequestController())->listAdminPendingRequests();
```

```php
<?php foreach ($requests as $request): ?>
```

- **Why this poses a 502 risk:** The complete query result is loaded and rendered in one request. Memory usage and response time grow linearly with the number of pending requests.

### Finding 4.4: Fee payment listing lacks pagination and a created-at index

- **File and lines:** `app/controllers/AdminFeatureController.php:498-506`
- **Code pattern:**

```sql
FROM junkshop_fee_payments fp
LEFT JOIN junkshop_profiles jp ON jp.account_id = fp.junkshop_id
LEFT JOIN accounts a ON a.id = fp.junkshop_id
ORDER BY fp.created_at DESC, fp.id DESC
```

```php
)->fetchAll();
```

- **Schema evidence:** `database/db.sql:696-697`

```sql
INDEX idx_junkshop_status (junkshop_id, status),
INDEX idx_reference_number (reference_number)
```

- **Why this poses a 502 risk:** The schema has indexes for account/status and reference lookups, but no index beginning with `created_at`. The admin listing has no `LIMIT`, so it may require a full scan and filesort as payment history grows.

### Finding 4.5: Renewal notification query and synchronous email loop

- **File and lines:** `app/services/NotificationService.php:78-89`
- **Code pattern:**

```php
$junkshops = $db->query(...)->fetchAll();

foreach ($junkshops as $junkshop) {
    ...
    $mailResult = MailerService::sendRenewalNotice(...);
}
```

- **Why this poses a 502 risk:** Due accounts are loaded without a result limit and emails are sent synchronously one at a time. A large number of due accounts or slow SMTP responses can exceed execution or upstream timeout limits.

### Index qualification

`pickup_requests` already has `idx_pickup_status_created (current_status, created_at)` in `database/db.sql:182`, which supports several status-filtered queries. The possible missing composite index involving `current_status` and `admin_viewed_report` should be confirmed with `EXPLAIN` and production row counts before being classified as a confirmed bottleneck.

## 5. PHP Script Execution Risks

### Finding 5.1: Unbounded matching loop

- **File and lines:** `app/services/MatchingEngine.php:96-104`
- **Code pattern:**

```php
foreach ($requests as $request) {
    $match = self::evaluateMatches($junkshopId, (int) $request['id']);
    ...
    self::saveMatch((int) $request['id'], $match);
}
```

- **Why this poses a 502 risk:** The loop is bounded only by the number of pending requests, which is unbounded. Each iteration may execute several SQL statements and trigger related notification work. Under load, this can exceed PHP execution limits or the reverse-proxy timeout.

### Finding 5.2: Full-database backup loop

- **File and lines:** `admin-private-dnstl/backup_db.php:90-101`
- **Code pattern:**

```php
while (($row = $rows->fetch(PDO::FETCH_NUM)) !== false) {
    ...
    $valueBatch[] = '(' . implode(', ', $values) . ')';
    if (count($valueBatch) >= 50) {
        ...
    }
}
```

- **Why this poses a 502 risk:** The loop reads every row from every table and builds a complete SQL backup in memory. A large database can exceed PHP memory or execution limits and terminate the web request before a response is returned.

### Finding 5.3: Synchronous renewal-email loop

- **File and lines:** `app/services/NotificationService.php:78-89`
- **Code pattern:**

```php
foreach ($junkshops as $junkshop) {
    ...
    MailerService::sendRenewalNotice(...);
}
```

- **Why this poses a 502 risk:** Email delivery is performed synchronously inside a potentially unbounded loop. SMTP latency multiplied by the number of recipients can exceed the PHP execution limit or upstream proxy timeout.

### Finding 5.4: Migration verification loop reviewed, but not considered a credible 502 source

- **File and lines:** `scripts/verify_migrations.php:86-94`
- **Code pattern:**

```php
while ($row = $metadata->fetch(PDO::FETCH_ASSOC)) {
    $existingColumns[] = $row['Field'];
}
```

- **Risk assessment:** This loop is bounded by the number of columns in a table and is not a credible 502 cause by itself.

### Finding 5.5: No recursive PHP function or clearly infinite loop found

The audit did not identify a recursive PHP call chain or a clearly infinite `while`/`do` loop in the application code.

## Final Assessment

The strongest application-level 502 candidates are:

1. Synchronous PhilSMS and SMTP operations on session-backed requests.
2. The unbounded matching loop with per-request database work.
3. Unbounded admin `fetchAll()` queries and full-page rendering.
4. The fee-payment listing’s unindexed `created_at` ordering.
5. The full-database backup loop and synchronous renewal-email loop.

No unbounded cURL call or sub-10-second AJAX network poller was confirmed. Runtime confirmation should use PHP-FPM/request timing, reverse-proxy timeout logs, database `EXPLAIN`, row counts, and external-provider latency measurements.
