ECOPICK WEBSITE SYSTEM REQUIREMENTS
IT Development Requirement / Website Specification
A Feasibility Study on Establishing EcoPick: An On-Demand Recyclable Materials Collection
and Booking Website in Lipa City
Prepared for: IT Development Team
1. PROJECT OVERVIEW
EcoPick is a web-based platform that connects households and other recyclable-material sellers
with registered junkshops in Lipa City. The website will facilitate the submission, matching,
scheduling, monitoring, and recording of recyclable-material pickup requests.
Important Business Rule: EcoPick is only the platform/facilitator. EcoPick will not purchase,
physically collect, transport, or store recyclable materials. Registered junkshops are responsible
for the actual collection, weighing, assessment, and purchase of recyclable materials.
2. MAIN USER ROLES
• Seller/User – household or other recyclable-material seller who submits pickup requests and
monitors transactions.
• Registered Junkshop – partner junkshop that maintains its profile and buying prices, receives
matched requests, accepts/declines bookings, performs collection, and records completed
transactions.
• EcoPick Administrator – manages users, junkshops, bookings, pricing information, payments,
fees, commissions, concerns, and reports.
3. SELLER/ USER WEBSITE REQUIREMENTS
• User registration, login, logout, and account/profile management.
• Selection/identification of recyclable materials to be sold.
• Viewing of registered and approved EcoPick partner junkshops.
• Viewing of recyclable materials accepted by each junkshop and its corresponding buying prices.
• Creation of a pickup request containing material type, estimated quantity/weight, pickup
location, preferred date/time, photo when applicable, and other required information.
• GPS or location-based functionality, or entered pickup location, to determine approximate
distance from suitable junkshops.
• Display of applicable estimated pickup/collection fee before booking confirmation.
• Calculation/display of estimated recyclable value based on estimated quantity/weight and
the selected junkshop’s posted buying price.
• Display of applicable EcoPick service fee before confirmation.
• Display of estimated net amount after applicable pickup/collection and EcoPick service fees.
• Booking status tracking and notifications/updates where technically feasible.
• Transaction/booking history.
• Waste segregation information and reminders.
4. JUNKSHOP WEBSITE REQUIREMENTS
• Junkshop registration and submission of required business/contact information.
• Registration fee/payment record.
• Junkshop profile containing business name, contact information, location, operating schedule,
and other relevant information.
• List of recyclable materials accepted.
• Buying price list for accepted recyclable materials, including prices per kilogram where applicable.
• Ability to update buying prices.
• View pickup requests matched to the junkshop.
• Accept or decline matched requests.
• View seller pickup location and approximate distance.
• Coordinate and confirm pickup schedule with the seller.
• Update booking status based on the actual progress of the transaction.
• Record actual weight, materials accepted, final recyclable value, pickup/collection fee, and
final amount paid to the seller.
• View registration, renewal, completed transaction, and commission records.
• Renew partnership upon expiration.
5. ADMINISTRATOR DASHBOARD REQUIREMENTS
• Manage registered users and user accounts.
• Review and manage junkshop registration information.
• Approve and maintain registered/accredited junkshops.
• Manage junkshop profiles and pricing lists.
• Monitor pickup requests and booking statuses.
• Monitor completed transactions.
• Calculate and record applicable transaction commissions and service fees.
• Monitor registration and renewal payments.
• Monitor outstanding payments.
• Manage reported concerns or disputes.
• Generate transaction and business reports.
• Configure applicable fees, commissions, registration fees, and renewal fees without hardcoding final rates.
6. BOOKING STATUS FLOW
The booking status shall reflect the actual progress of each pickup request. The system shall
automatically assign the initial Pending Request status when a seller submits a request and may
assign Matched when a suitable junkshop is identified. The junkshop shall be responsible for
updating the operational statuses after matching.
Pending Request → Matched → Accepted → Scheduled → For Pickup → Completed
Pending Request → Matched → Declined → Rematched or Cancelled
Pending Request / Matched / Accepted → Cancelled by Seller
7. STATUS RESPONSIBILITY MATRIX
Status Responsible Party Description
Pending Request System Automatically assigned when
the seller submits a pickup
request.
Matched System Assigned after suitable
registered junkshop(s) are
identified using matching
criteria.
Accepted Junkshop Updated by the matched
junkshop when it agrees to
handle the request.
Declined Junkshop Updated by the junkshop
when it does not accept the
request.
Rematched System Used when a declined
request is successfully
matched with another
suitable junkshop.
Scheduled Junkshop Updated after the junkshop and
seller confirm the pickup date
and time.
For Pickup Junkshop Updated when the scheduled
pickup is ready to be performed
or collection has commenced.
Completed Junkshop Updated after collection, actual
weighing/assessment, final
valuation, fee recording, and
seller payment settlement.
Cancelled by Seller Seller Seller may cancel only before
the booking reaches
Scheduled.
Cancelled by Junkshop Junkshop May be used when the
junkshop cannot proceed,
subject to applicable
cancellation policy.
8. DETAILED BOOKING PROCESS
Submit a Request – Pending Request: The seller logs in and submits the material type,
estimated quantity/weight, photo when applicable, pickup location, and preferred schedule. The
system assigns Pending Request.
Matching – Matched: The system identifies suitable registered junkshops based on recyclable
materials accepted, pickup location, approximate distance, availability, and other applicable
criteria. The seller may view relevant junkshop information, buying prices, and estimated
applicable fees.
Junkshop Decision – Accepted or Declined: The matched junkshop reviews the request and
updates the booking to Accepted or Declined. If declined, the system may rematch the request
with another suitable junkshop or allow the seller to cancel.
Schedule Confirmation – Scheduled: After accepting the request, the junkshop coordinates the
pickup date and time with the seller. Once confirmed, the junkshop updates the status to
Scheduled.
Pickup – For Pickup: When the scheduled pickup is ready to be performed or collection has
commenced, the junkshop updates the status to For Pickup.
Actual Weighing and Assessment: The junkshop collects the materials and determines actual
weight, accepted materials, condition, and applicable buying price(s).
Fee Calculation and Final Settlement:
After the actual weighing and assessment, the system automatically calculates the final recyclable value
based on the actual quantity/weight and the applicable buying price posted by the selected registered
junkshop. The system also automatically calculates the applicable EcoPick service fee and/or transaction
commission based on the administrator-configured rate or percentage. The calculated EcoPick fee or
commission is visible only to the junkshop and EcoPick administrator and is not displayed to the seller.
The system records the applicable pickup/collection fee and determines the seller's final amount based on
the approved transaction computation.
Completion – Completed: After collection, weighing/assessment, final valuation, applicable fee
recording, and payment settlement, the junkshop updates the booking to Completed.
9. SELLER CANCELLATION RULE
The seller may cancel a booking only while the booking is in Pending Request, Matched, or
Accepted status. Once the booking reaches Scheduled status, the seller shall no longer be
permitted to cancel the booking through the system. Any cancellation or rescheduling request
after scheduling shall be coordinated with the concerned junkshop and may be subject to
EcoPick’s applicable policies.
The IT team should implement the cancellation restriction as a system rule so that the cancellation
option is unavailable or disabled once the booking reaches Scheduled, For Pickup, or Completed
status.
10. PRICING AND FEE LOGIC
Exact rates and percentages will be provided by the business/researchers. The system should
allow administrators to configure or update monetary rates rather than hard-code final values.
• Junkshop buying price = posted by each registered junkshop for the recyclable
materials it accepts.
• Estimated recyclable value = estimated quantity/weight × selected junkshop buying price.
• Pickup/collection fee = applicable fee charged by the junkshop for the actual collection
service; distance may be one factor in determining the fee.
• EcoPick service fee = fee charged by EcoPick for the use of the platform; it shall be
automatically calculated based on the applicable rate or percentage configured by the
administrator. The fee shall be automatically deducted from the seller's estimated recyclable
value in determining the estimated amount to receive.
• Estimated net amount = estimated recyclable value − applicable pickup/collection fee −
EcoPick service fee. The resulting amount represents the estimated amount to be received
by the seller.
• Final recyclable value = determined by the junkshop after actual weighing and assessment.
• Transaction commission = applicable commission charged to registered junkshops based on
the agreed percentage of completed recyclable-material transactions.
• Registration fee and partnership renewal fee = recorded as applicable junkshop
partnership payments.
• Mode of Payment = the system shall support applicable payment methods, such as Cash
and GCash, for the seller's receipt of the estimated/final net amount and for the settlement
of applicable EcoPick fees or commissions. The selected payment method and payment
status shall be recorded in the system.
11. LOCATION AND MATCHING
The platform should use GPS/location-based functionality or an entered pickup location to
determine approximate distance between the seller and junkshops.
• Recyclable materials accepted by the junkshop.
• Pickup location and approximate distance.
• Junkshop availability.
• Other applicable matching criteria defined by EcoPick.
The IT team should advise whether GPS/map functionality requires a third-party API or
service, including any usage limits, recurring costs, privacy considerations, and technical limitations.
12. TRANSACTION RECORDS
For completed transactions, the system should be able to record:
• Seller/user
• Junkshop
• Pickup date and time
• Pickup location
• Materials accepted
• Actual weight
• Applicable buying price(s)
• Final recyclable value
• Pickup/collection fee
• EcoPick service fee
• Final amount paid to seller
• Applicable transaction commission
• Transaction/booking status
13. SUGGESTED MAIN WEBSITE PAGES
1. Home
2. About EcoPick
3. How It Works
4. Junkshop Listings
5. Recyclable Materials / Buying Prices
6. Book a Pickup
7. Waste Segregation Guide
8. Login
9. User Registration
10. Junkshop Registration
11. User Dashboard
12. Junkshop Dashboard
13. Admin Dashboard
14. Booking Details / Tracking
15. Transaction History
16. Contact / Support
14. SUGGESTED DASHBOARD SECTIONS
Seller/User Dashboard:
• Profile
• New Pickup Booking
• Current Bookings
• My Bookings
• Booking Status
• Transaction History
• Notifications/Update
s
• Payment & Payout
Preference:
- Cash
- GCash
• Estimated
Transaction Amount
• Final Transaction
Amount
Junkshop Dashboard:
• Junkshop Profile
• Accepted Materials & Buying Prices
• Matched Requests
• Schedule/Pickups
• Completed Transactions
• Commission Records
• Seller Payment Records
• EcoPick Fees/ Commission
• Payment To EcoPIck
- Cash
- GCash
• Registration/ Renewal Records
• Notifications
For EcoPick's share:
EcoPick Fee: ₱XX
Payment Method: Cash / GCash
Payment Status: Unpaid / Paid / Confirmed
Admin Dashboard:
• Dashboard Overview
• Users
• Junkshops
• Approvals/Accreditation
• Pricing Lists
• Bookings
• Completed Transactions
• Fees & Commissions
• Seller Payments (Booking Fee)
• Junkshop Payments to EcoPick
• Registration/Renewal Payments
• Concerns/Disputes
• Reports
15. IMPORATANT BUSINESS RULES
• Only registered and approved/accredited junkshops should be presented as EcoPick
partner junkshops.
• EcoPick is a platform/facilitator and will not purchase, physically collect, transport, or
store recyclable materials.
• Junkshops are responsible for actual collection, weighing, assessment, and
purchase of recyclable materials.
• Estimated recyclable value is not the final value; final value depends on actual accepted
materials, actual weight, condition, and applicable junkshop buying prices.
• The pickup/collection fee belongs to the junkshop as compensation for its collection service.
• The EcoPick service fee is revenue of EcoPick.
• Transaction commission applies to completed recyclable-material transactions based on
the agreed percentage.
• A declined booking may be rematched with another suitable junkshop or cancelled.
• Seller cancellation is allowed only before the booking reaches Scheduled status.
• Exact fee rates, commission percentages, registration fees, renewal fees, and other monetary
values must be configurable rather than hard-coded because final rates are subject to the
feasibility study/business decision.
• Junkshops are responsible for updating Accepted, Declined, Scheduled, For Pickup, and
Completed statuses based on actual transaction progress.
• The system is responsible for the initial Pending Request status and may handle
matching/rematching status according to the implemented matching logic.
16. PRIORITY FEATURES FOR THE INITIAL WORKING PROTOTYPE
• User registration/login
• Junkshop registration and admin approval
• Junkshop profiles and accepted-material/buying-price list
• Pickup request form
• Location/distance functionality
• Junkshop matching
• Estimated recyclable value and fee display
• Booking status tracking
• Junkshop accept/decline function
• Pickup scheduling
• Seller cancellation restriction
• Completed transaction recording
• User transaction history
• Basic admin dashboard
17. TECHNICAL REQUIREMENTS AND ITEMS TO CONFIRM WITH IT
• Recommended technology stack and hosting.
• Whether GPS/map functionality can be implemented and which map service is recommended.
• How junkshop matching will be implemented.
• How fee and commission calculations will be configured.
• Whether online payment can be integrated and what payment provider is recommended.
• How notifications/status updates will be implemented.
• Required database tables and user roles.
• Security and privacy requirements.
• Estimated development timeline and scope for the prototype.
• Any features that should be simplified or changed because of technical limitations.
• Which requested functions require third-party services or APIs, including maps/GPS,
notifications, email/SMS, and payment processing.
• Any third-party service limitations, usage limits, subscription or recurring costs,
and implementation dependencies.
• Recommended approach for audit logs or history of booking status changes, particularly
which user changed the status and when.
18. BOOKING STATUS BOOKING TRAIL
For accountability and transaction monitoring, the system should, where technically feasible,
maintain a record of booking status changes. Each status change should record the previous
status, new status, date/time, and responsible user/account. This is particularly important because
the junkshop is responsible for updating the operational statuses.
Previous Status New Status Responsible Party Recommended
Record
Pending Request Matched System Date/time of
matching and
matched junkshop
Matched Accepted / Declined Junkshop Junkshop account
and date/time
Accepted Scheduled Junkshop Confirmed
schedule and
date/time
Scheduled For Pickup Junkshop Date/time status was
updated
For Pickup Completed Junkshop Actual weight, final
value, fees, seller
payment, date/time
Pending/Matched/Accepted Cancelled by Seller Seller Cancellation
date/time
19. SCOPE CLARIFICATION FOR THE IT TEAM
This document defines the required business functions and workflow for the EcoPick feasibilitystudy prototype. The IT team may recommend the appropriate technology stack, database
structure, hosting, security approach, map/GPS service, notification method, payment integration,
and interface design based on technical feasibility. Any proposed change to a core business rule
or workflow should be discussed with the researchers before implementation.
The researchers will provide the final fee rates, commission percentages, registration/renewal
fees, and other business-specific monetary values once these are finalized. These values should
be implemented as configurable settings.
20. FINAL REQUIREMENT CONFIRMATION
The IT team is requested to review this document and confirm which requirements can be
implemented within the agreed prototype scope, identify any technical limitations or third-party
dependencies, and recommend necessary adjustments before development begins.