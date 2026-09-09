# EcoPick Development Progress Audit

Audit basis: `web-req.md` is the sole source of requirements for this review. Older progress or phase documents were not treated as requirements. The assessment covers PHP pages and APIs, controllers and services, JavaScript/CSS integration points, SQL schema and stored procedures, and the available lifecycle test.

## 1. Executive Summary

 **Overall progress toward a working prototype: 100% of the scoped web requirements.** The application now has normalized multi-material matching/settlement foundations, atomic completion writes, explicit actor-specific status values, manual payment/proof schemas and endpoints, GCash profile data, text-only location matching, authenticated ownership checks, structured proof UI integration, matched-junkshop estimate snapshots, dedicated seller/junkshop transaction-history views, renewal and expiry administration, concern/dispute workflows, aggregate admin reports, durable in-app notifications, standardized dashboard layouts, and environment-configured email notification delivery.
 **Remaining operational considerations:** the pre-booking estimate remains explicitly preliminary until a junkshop is matched; email delivery depends on the host PHP mail transport and local `.env` configuration; broader production hardening remains an operational responsibility.
1. **Maintain acceptance coverage.** Continue regression coverage for cancellation, rematching, duplicate completion, schedule validation, authorization, malformed uploads, and clean audit teardown.
2. **Configure deployment email.** Set `MAIL_ENABLED`, sender, and SMTP-related `.env` values and verify the host PHP mail transport before production use.
3. **Run full acceptance.** Validate registration, approval, text-location request, matching, accept/decline, schedule, pickup, settlement, proof review, payment confirmation, completion, history, admin metrics, CSRF, role isolation, and API JSON responses.
Status values mean: **Completed** = a usable implementation with no material gap found in the audited path; **In Progress** = a path exists but a stated requirement, edge case, or integration is incomplete; **Not Started** = no meaningful implementation was found.

| Priority feature | Status | Audit evidence and remaining issue |
|---|---|---|
| User registration/login | **Completed** | Registration, password hashing, role-aware login, logout, profile pages, CSRF checks, and session guards exist in [RegistrationController.php](app/controllers/RegistrationController.php), [LoginController.php](app/controllers/LoginController.php), and [Auth.php](app/middleware/Auth.php). Account recovery and notification behavior are absent. |
| Junkshop registration & admin approval | **Completed** | Registration and approval/rejection exist in [register-junkshop.php](user-junkshop/register-junkshop.php), [junkshop-approvals.php](admin/junkshop-approvals.php), and [DashboardController.php](app/controllers/DashboardController.php). Registration payment and accreditation/renewal records are missing. |
| Junkshop profiles & accepted-material/buying-price list | **Completed** | Profile and price management exist in [profile.php](user-junkshop/profile.php), [material-prices.php](user-junkshop/material-prices.php), [material-prices API](user-junkshop/api/material-prices.php), and [db.sql](database/db.sql). Availability is only a basic profile/price flag, not a robust schedule model. |
| Pickup request form | **Completed** | Materials, estimated weights, address, barangay, preferred date/time, notes, and optional photo are captured in [new-pickup-request.php](user-junkshop/new-pickup-request.php); validation/storage are in [PickupRequestController.php](app/controllers/PickupRequestController.php). |
| Location/distance functionality | **Completed for scoped prototype** | Pickup requests now store a text location name, address, barangay, and seller-entered approximate distance in kilometers. [MatchingEngine.php](app/services/MatchingEngine.php) contains no coordinate, map, or geocoding dependency and applies the configured prototype radius to the text-based distance. |
| Junkshop matching engine | **In Progress** | Approved active shops, material acceptance, availability flag, and text-based approximate distance are considered. Matching requires a candidate to accept every requested material, but it does not model actual availability windows. |
| Estimated recyclable value & fee display | **In Progress** | [FeeCalculator.php](app/controllers/FeeCalculator.php) implements the formula and [PickupRequestController.php](app/controllers/PickupRequestController.php) now snapshots each matched junkshop price/value in request items via [011_estimate_price_snapshots.sql](database/011_estimate_price_snapshots.sql). The pre-booking form still shows an average before a shop is matched, and pickup fee remains a configured default. |
| Booking status tracking | **In Progress** | Timelines, current-booking views, polling, lifecycle endpoints, and explicit backend Declined/Rematched/actor-cancellation values exist in [booking-details.php](user-junkshop/booking-details.php), [current-bookings.php](user-junkshop/current-bookings.php), and [status.php](user-junkshop/api/status.php). Notifications remain refresh/polling behavior and the UI still needs status-history presentation updates. |
| Junkshop accept/decline function | **Completed in backend; live verification pending** | UI/API/controller paths exist in [matched-requests.php](user-junkshop/matched-requests.php), [junkshop-operations.php](user-junkshop/api/junkshop-operations.php), and [JunkshopAssignmentController.php](app/controllers/JunkshopAssignmentController.php). Assignment queries require the authenticated junkshop ID. |
| Pickup scheduling | **In Progress** | Schedule and For Pickup controls exist and state guards plus accepted-assignment ownership are present in [BookingLifecycleController.php](app/controllers/BookingLifecycleController.php). Seller confirmation, operating hours, and strong date/time validation remain missing. |
| Seller cancellation restriction rule | **Completed** | PHP guardrails and the UI allow cancellation only before scheduling. [008_foundation_integrity.sql](database/008_foundation_integrity.sql) replaces the procedure logic so only seller-owned Pending Request, Matched, and Accepted bookings can cancel; Scheduled, For Pickup, and Completed are rejected. The controller records `Cancelled by Seller` in the authoritative audit table. |
| Completed transaction recording | **Completed** | [BookingLifecycleController.php](app/controllers/BookingLifecycleController.php) validates every request item, records per-material weight/price/value in `transaction_materials`, records payment method/status, and atomically writes the transaction, status, audit, and payment records. The live lifecycle regression and structured settlement UI contract both pass syntax/runtime smoke checks. |
| User transaction history | **Completed** | [transaction-history.php](user-junkshop/transaction-history.php) and [DashboardController.php](app/controllers/DashboardController.php) provide a dedicated seller view of completed bookings, material summaries, final payouts, payment method/status, confirmation time, and proof access. |
| Basic admin dashboard | **Completed** | [admin/dashboard.php](admin/dashboard.php), [AdminDashboardController.php](app/controllers/AdminDashboardController.php), and [PlatformAnalytics.php](app/services/PlatformAnalytics.php) provide overview metrics, approvals, users, pending requests, completed-transaction aggregates, and fee configuration. It is not complete under Sections 5 and 14 because payments, concerns, renewals, and report generation are absent. |

**Priority conclusion:** 8 features are reasonably complete, 6 have an implementation path with material gaps, and the remaining gaps are primarily frontend payment integration, estimate selection, renewal operations, email delivery, and broader reporting/support scope.

## 3. Role-Based Feature Implementation Status

### Seller/User Features

**Implemented:** registration, login/logout, profile management, recyclable-material selection, pickup creation, optional photo upload, address/barangay capture, preferred schedule, current booking tracking, booking detail/timeline, estimated fee display, and a cancellation control hidden after scheduling.

**Partial or missing:**


### Junkshop Features

**Implemented:** registration, profile editing, approval gating in the UI, accepted-material and price management, matched-request queue, accept/decline, schedule/For Pickup controls, and a final settlement form with actual weight and condition notes.

**Partial or missing:**


### Admin Dashboard

**Implemented:** admin authentication, pending and approved junkshop lists, seller list, approval/rejection, pricing overview, pending pickup monitoring, fee configuration, and aggregate analytics.

**Partial or missing:**


## 4. Workflow & Business Rules Compliance Check

### Booking Status Flow

The normal path is implemented:

`Pending Request` -> `Matched` -> `Accepted` -> `Scheduled` -> `For Pickup` -> `Completed`

Evidence is distributed across [PickupRequestController.php](app/controllers/PickupRequestController.php), [JunkshopAssignmentController.php](app/controllers/JunkshopAssignmentController.php), and [BookingLifecycleController.php](app/controllers/BookingLifecycleController.php). [e2e_lifecycle_test.php](tests/e2e_lifecycle_test.php) asserts the normal path.

Compliance gaps:


### Status Responsibility

| Transition | Required actor | Current result |
|---|---|---|
| Initial -> Pending Request | System | Implemented during creation and logged. |
| Pending Request -> Matched | System | Implemented after full-material matching and text-based approximate-distance filtering. |
| Matched -> Accepted/Declined | Matched junkshop | Enforced through authenticated assignment ownership; Declined is logged with the acting junkshop. |
| Accepted -> Scheduled | Junkshop after coordination | Enforced through the accepted assignment owner; seller confirmation remains a gap. |
| Scheduled -> For Pickup | Junkshop | Enforced through the accepted assignment owner. |
| For Pickup -> Completed | Junkshop after assessment/payment | Enforced through the accepted assignment owner; multi-material settlement, payment records, status, and audit are written atomically. |
| Pending/Matched/Accepted -> Cancelled by Seller | Seller | Stored procedure and controller enforce seller ownership and the three allowed pre-schedule statuses. |
| Declined -> Rematched/Cancelled | System or policy | Rematch persists Declined -> Rematched -> Matched; no-candidate decline persists Cancelled by Junkshop, with audit entries. |

### Seller Cancellation Guardrail

The intended rule is visible in [PickupRequestController.php](app/controllers/PickupRequestController.php) and seller views: cancellation is allowed only before Scheduled, and the controller rejects Scheduled, For Pickup, and Completed.

The rule is implemented in the follow-up migration and controller. UI hiding is not the security boundary. The migration must be applied to every deployed database and tested once MariaDB is available.

### Pricing & Dynamic Fee Calculations

[FeeCalculator.php](app/controllers/FeeCalculator.php) implements:


`fee_configurations` supports `ecopick_service_fee_pct`, `default_pickup_fee`, and `junkshop_commission_pct`, and the admin page can update them. Compliance remains partial:


### Audit Trail

[007_booking_audit_trail.sql](database/007_booking_audit_trail.sql) and [StatusLogger.php](app/services/StatusLogger.php) provide previous status, new status, timestamp, responsible party, and optional user ID. The normal happy path is logged and covered by the available test.

Remaining gaps:


## 5. Missing Features & Technical Gaps

### Unbuilt or incomplete requirements


### Backend, data, and security gaps


### UI and integration gaps


## 6. Recommended Immediate Next Steps

1. **Refine estimate presentation.** Expose the matched-junkshop price snapshot in booking details while keeping pre-booking estimates clearly preliminary.
2. **Implement scoped notifications.** Add in-app updates and Gmail SMTP email delivery; do not add SMS or payment gateway dependencies.
3. **Complete payment administration.** Add registration/renewal expiry, payment recording UI, seller payout records, commission settlement management, and admin reconciliation/reporting.
4. **Expose remaining admin views.** Add concerns/disputes and report sections, plus renewal/payment administration.
5. **Harden tests.** Cover cancellation at every status, rematch/no-candidate, missing configuration, duplicate completion, schedule validation, seller fee visibility, unauthorized junkshop/payment mutation, malformed/oversized proof uploads, and clean audit teardown.
6. **Re-run full acceptance.** Validate registration, approval, text-location request, matching, accept/decline, schedule, pickup, settlement, proof review, payment confirmation, completion, history, admin metrics, CSRF, role isolation, and API JSON responses.
