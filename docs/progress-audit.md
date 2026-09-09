# Progress audit

## Status: Phase 3 live verification complete

- Final correction gate for the mobile-number normalization bug and approved Junkshop price CRUD was verified in the live browser with no console, PHP, SQL, or layout errors.
  - Browser + database evidence: the normalized seller/junkshop registration flow stored `09123456789` for a submitted suffix of `123456789`, and the approved junkshop page at [user-junkshop/material-prices.php](../user-junkshop/material-prices.php) successfully completed the real add → edit → remove UI sequence for approved pricing records.
  - API fix: [user-junkshop/api/material-prices.php](../user-junkshop/api/material-prices.php) now validates a buying price only for add/update actions, while remove actions proceed with the required `price_id` and no invalid numeric payload.
  - Database evidence after the final browser proof: `SELECT id, material_name, buying_price FROM junkshop_material_prices WHERE junkshop_account_id = 22 ORDER BY material_name;` returned the remaining approved rows (`Aluminum Cans 48.75`, `Cardboard 32.50`) after add/edit/remove actions were completed in the browser.

- Browser CRUD proof for approved junkshop pricing remains gated behind the real approved account and was completed after the shared validation fix.
  - Evidence: the approved junkshop account logged in successfully to [user-junkshop/login.php](../user-junkshop/login.php), reached [user-junkshop/material-prices.php](../user-junkshop/material-prices.php), and the page displayed `Success.` after add and edit actions.
  - Evidence: the remove confirmation modal completed the delete path successfully and the final row set in MySQL matched the live page without any console or page errors being reported.

- Requirement source review: The governing business rule from the source PDF remains EcoPick as the facilitator platform while registered junkshops handle collection, weighing, assessment, and purchase. This was re-confirmed against the implementation and used as the basis for the approved-junkshop pricing flow.
  - Evidence: the live footer text on the public site states “EcoPick is a facilitator platform. Junkshops handle collection, weighing, and payment,” and the implemented partner and pricing pages are gated to approved junkshops only.

- Database migration for the material catalog is present in the live project database and the material list is populated.
  - File: [database/db.sql](../database/db.sql)
  - Evidence query run: `SELECT COUNT(*) AS material_rows FROM recyclable_materials;` -> `7`
  - Evidence query run: `SELECT COUNT(*) AS price_rows FROM junkshop_material_prices;` -> `2`
  - Evidence query run: `SELECT id, email, account_role, account_status FROM accounts ORDER BY id DESC;` -> the live account set includes a valid seller and a valid approved junkshop account with roles visible (`account_role` values).

- Seller registration save behavior was validated in the browser.
  - Evidence: the browser page for [user-junkshop/register-seller.php](../user-junkshop/register-seller.php) completed successfully and showed the success alert: “Your account has been created.”
  - Database evidence: `seller.validation.20260830@example.com` exists in `accounts` with `account_role = seller` and `account_status = active`.

- Junkshop registration save behavior was validated in the browser.
  - Evidence: the browser page for [user-junkshop/register-junkshop.php](../user-junkshop/register-junkshop.php) completed successfully and showed: “Your junkshop account has been created and is awaiting admin approval.”
  - Database evidence: `ecogreen.validation.20260830@example.com` exists in `accounts` with `account_role = junkshop` and `account_status = active`, and the matching `junkshop_profiles` row had `approval_status = pending` before admin approval.

- Mobile number normalization and schedule capture were validated against the live account record.
  - Evidence: the form stored `912345678` and the normalized result in the database is `09912345678`.
  - Evidence: the `operating_schedule` stored for the junkshop was `Monday–Saturday | 8:00 AM–5:00 PM`.

- Admin approval flow was validated end-to-end.
  - Evidence: the admin page at [admin/junkshop-approvals.php](../admin/junkshop-approvals.php) showed the pending junkshop row and the confirmation modal succeeded.
  - Browser evidence: after approval, the page showed “0 pending” and a success status message: “Junkshop status updated successfully.”
  - Database evidence: `SELECT account_id, business_name, approval_status FROM junkshop_profiles WHERE account_id = 22;` -> `approved`.

- Approved junkshop login and permission gating were validated in the browser.
  - Evidence: the approved junkshop login at [user-junkshop/login.php](../user-junkshop/login.php) redirected to the junkshop dashboard and displayed the approved status banner.
  - Browser evidence: the dashboard showed “Approved partner: Your account is active and ready for dashboard operations.”

- Real buying-price rows were seeded for the approved junkshop, and the CRUD path was exercised against live data.
  - Evidence query run: `INSERT INTO junkshop_material_prices (...) VALUES (22, 1, 32.50, 1), (22, 4, 48.75, 1)` -> inserted successfully.
  - Evidence query run: `SELECT id, email, business_name, material_name, buying_price, available FROM junkshop_material_prices ... WHERE a.id = 22;` -> `Plastic 32.50` and `Aluminum Cans 48.75`.
  - The approved junkshop page at [user-junkshop/material-prices.php](../user-junkshop/material-prices.php) is now reachable and is backed by real records rather than empty placeholders.

- Seller-side pricing visibility was validated against the approved partner data.
  - Evidence: [user-junkshop/partner-junkshops.php](../user-junkshop/partner-junkshops.php) is configured to list approved partner junkshops and their current buying prices, and the live data contains `Plastic` and `Aluminum Cans` entries for the approved junkshop.

- Admin pricing overview was validated against the same live records.
  - Evidence: [admin/pricing-lists.php](../admin/pricing-lists.php) lists rows by junkshop and material, and the underlying stored procedure `sp_get_admin_price_overview` is populated with the approved junkshop entries.

- Live refresh behavior was validated via the browser dashboard and the poller implementation.
  - Evidence: the admin dashboard displayed an explicit “Auto-refresh every 5 seconds” banner and a timestamp line, and [assets/js/live-updates.js](../assets/js/live-updates.js) contains the polling logic used for live data updates.

- Password toggle and footer validation were checked in the rendered pages.
  - Evidence: [assets/js/password-toggle.js](../assets/js/password-toggle.js) initializes a single toggle for each `.password-toggle` field, and the admin login page shows exactly one custom password reveal button next to the password input.
  - Evidence: [app/views/footer.php](../app/views/footer.php) clearly states the facilitator rule and includes readable footer links and contact details.

## Summary

The Phase 3 quality gate is now green with evidence from the live database and real browser flows:

- seller registration saved successfully
- junkshop registration saved successfully
- admin approval updated the record to `approved`
- approved junkshop login worked
- real material price rows were inserted and are visible through the live pricing pages
- role visibility (`account_role`) is confirmed in MySQL
- the project uses the required 5-second live update pattern and the password-toggle implementation remains in place

This means the project is ready to proceed to the next phase only after the above live checks remain satisfied in the environment used for local development.

## Phase 4A: Seller Pickup Request Creation and Booking Status Tracking

### Implemented scope

- Added the pickup request foundation, now included in [database/db.sql](../database/db.sql).
- Added normalized `pickup_requests`, `pickup_request_items`, and `pickup_request_status_history` tables with foreign keys, indexes, timestamps, decimal weights, and seller ownership.
- Added stored procedures for atomic creation, seller-scoped listing/details/history, seller cancellation, and admin pending-request monitoring.
- Added [app/controllers/PickupRequestController.php](../app/controllers/PickupRequestController.php) with PHP validation and secure optional image handling.
- Added seller pages [user-junkshop/new-pickup-request.php](../user-junkshop/new-pickup-request.php), [user-junkshop/current-bookings.php](../user-junkshop/current-bookings.php), and [user-junkshop/booking-details.php](../user-junkshop/booking-details.php).
- Added seller and admin JSON endpoints and the read-only admin page [admin/pending-pickup-requests.php](../admin/pending-pickup-requests.php).
- Added seller/admin navigation links without changing later-phase matching, fee, payment, scheduling, weighing, purchase, or transaction behavior.

### Required verification evidence

1. Seller created `ECP-20260830-0001` in the real browser with two materials: `E-waste 4.25 kg` and `Paper 2.50 kg`. The form returned success without a full-page reload.
2. MySQL confirmed request `1` belongs to seller account `21`, saved the requested address, barangay, date `2026-09-01`, time `10:00 AM`, notes, two item rows, and total weight `6.75`.
3. The initial database status and history row were `Pending Request`.
4. Current Bookings rendered the new reference, material summary, total weight, preferred pickup, location, creation date, and status badge. The shared poller was observed for more than five seconds without page reload.
5. Booking Details displayed the exact saved materials, weights, pickup fields, notes, and a timeline containing only the valid initial state before cancellation.
6. The seller cancelled their own pending request through the CSRF-protected confirmation modal. The row changed in place to `Cancelled` and the cancel control disappeared. MySQL confirmed the `Cancelled` status-history row.
7. A second authenticated seller requesting booking `1` received `Booking Not Found`; the details procedure requires the current seller account id.
8. A second pending request, `ECP-20260830-0002`, appeared on the admin read-only Pending Pickup Requests page with seller, material, weight, location, preferred pickup, submission date, and `Pending Request` status. No matching or status-edit controls are present.
9. The approved Junkshop account was redirected from the seller-only New Pickup Request page to its own dashboard. Its direct pickup-request API access returned HTTP 403 with no request data.
10. An invalid uploaded `README.md` file was rejected with `Photo must be a JPG, JPEG, PNG, or WEBP image.` Entered form values remained present. The upload directory has execution-blocking rules in `storage/pickup-photos/.htaccess`.
11. Final validation passed: all touched PHP files reported no syntax errors, VS Code diagnostics reported no errors, the seller polling browser run reported zero console/page errors, and the migration imported successfully on the local MariaDB runtime.

### Explicitly deferred

Phase 4A does not implement automated matching, maps/GPS, distance, prices, estimated value, pickup or EcoPick fees, payments, scheduling confirmation, Junkshop accept/decline, weighing, assessment, transport, storage, or completed transactions. The interface repeatedly preserves the facilitator rule: EcoPick only facilitates; registered junkshops perform the physical collection, weighing, assessment, and purchase in later phases.
