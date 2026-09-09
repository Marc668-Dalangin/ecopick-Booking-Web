# EcoPick Future Modules - Development Roadmap

## Overview

Phase 1 establishes the foundation with user authentication and basic platform structure. This document outlines the planned features for subsequent phases.

⚠️ **These features are not implemented yet.** This is a reference for planning and development.

---

## Phase 2: Booking & Matching System

### 2.1 Pickup Booking Workflow

**Seller Side:**
- Create pickup request with:
  - Material types and quantities
  - Photos of materials
  - Preferred pickup dates/times
  - Special instructions or notes
- Track request status in real-time
- View matched junkshops
- Accept or reject matched junkshops
- Reschedule or cancel bookings

**Junkshop Side:**
- View incoming pickup requests
- Filter by material type, location, quantity
- Accept or decline requests
- Schedule pickup appointment
- Add notes about the pickup

**EcoPick Admin Side:**
- Monitor all active bookings
- View matching algorithm performance
- Handle disputes or cancellations
- Generate booking reports

### 2.2 Request Status Flow

```
Seller Creates Request
        ↓
    PENDING_REQUEST
        ↓
EcoPick Matches Junkshops
        ↓
    MATCHED (junkshop assigned)
        ↓
Junkshop Accepts
        ↓
    ACCEPTED (pickup scheduled)
        ↓
Pickup Happens
        ↓
    FOR_PICKUP (in transit)
        ↓
    COMPLETED (assessment done, payment calculated)
```

### 2.3 Alternative Flows

**Declined Request:**
```
MATCHED → DECLINED → REMATCHED (or CANCELLED by seller)
```

**Cancellation:**
```
Any Status → CANCELLED (if seller or junkshop initiates before completion)
```

### 2.4 Database Schema Additions

Tables needed:
- `pickup_requests` - Main booking records
- `pickup_materials` - Details of materials in request
- `pickup_assignments` - Junkshop assignments
- `pickup_status_history` - Track status changes
- `pickup_notes` - Internal notes and comments

### 2.5 Estimated Implementation Time

- Backend: 2-3 weeks
- Frontend: 2 weeks
- Testing: 1 week
- **Total: 5-6 weeks**

---

## Phase 3: Material Pricing & Assessment

### 3.1 Material Buying Prices

**System Features:**
- Admin manages market-based buying prices
- Prices updated regularly (daily/weekly)
- Price history tracking
- Price variations by:
  - Material type and quality
  - Quantity
  - Condition/grade
  - Current market rates

**Display:**
- Public information page showing typical price ranges
- Seller sees estimated values before booking
- Junkshop applies actual assessment-based prices

### 3.2 Material Assessment

**Junkshop Assessment Process:**
- Weigh materials
- Assess condition/quality
- Apply discounts for:
  - Contamination
  - Damage
  - Unusable portions
- Record final assessment

**System:**
- Store assessment data
- Calculate final payment
- Generate assessment report
- Photo/evidence documentation

### 3.3 Recyclable Value Estimation

**For Sellers:**
- Estimated value based on:
  - Material type
  - Approximate quantity
  - Condition (as described)
- Clear disclaimer: "Final value determined by actual assessment"

**For Junkshops:**
- Selling value to buyers
- Profit margin calculation
- Market price comparison

### 3.4 Database Schema Additions

Tables needed:
- `material_prices` - Master price list
- `material_grades` - Quality grades and discounts
- `pickup_assessments` - Assessment results
- `price_history` - Historical prices for trending

### 3.5 Estimated Implementation Time

- Backend: 2-3 weeks
- Frontend: 1-2 weeks
- Testing: 1 week
- **Total: 4-6 weeks**

---

## Phase 4: Payments & Transactions

### 4.1 Payment Processing

**For Local Development (Phase 4):**
- Sandbox payment gateway (e.g., Stripe Test Mode)
- Multiple payment methods:
  - Bank transfer (manual)
  - Digital wallet (e.g., GCash, PayMaya - test mode)
  - Cash payment
  - Check payment

**Future Production:**
- Real payment integration
- Secure PCI-DSS compliant processing
- Multiple payment gateways

### 4.2 Payment Flow

```
Assessment Complete
    ↓
Calculate Total Payment
    ↓
Show Payment Method Options
    ↓
Process Payment
    ↓
Confirm Payment
    ↓
Transaction Complete
```

### 4.3 Fees & Commission

**EcoPick Service Fees:**
- Platform facilitation fee (% or fixed)
- Pickup scheduling fee (optional)

**Transaction Fees:**
- Payment processing fees
- Bank transfer fees
- Digital wallet fees

**Display:**
- Transparent fee breakdown in UI
- Calculated before payment
- Shown in transaction receipt

### 4.4 Payment Dashboard

**For Sellers:**
- Transaction history
- Payment status tracking
- Received payments
- Pending payments
- Failed transactions with retry option

**For Junkshops:**
- Collections received
- Payments made to sellers
- Commission calculations
- Monthly settlement statements

**For Admin:**
- Payment monitoring
- Dispute resolution
- Commission tracking
- Revenue analytics

### 4.5 Database Schema Additions

Tables needed:
- `payments` - Payment transactions
- `invoices` - Invoice records
- `commissions` - EcoPick commission tracking
- `payment_methods` - Saved payment methods
- `transaction_history` - Audit trail

### 4.6 Estimated Implementation Time

- Backend: 3-4 weeks
- Integration: 2 weeks
- Frontend: 2-3 weeks
- Testing & Security: 2 weeks
- **Total: 9-12 weeks**

---

## Phase 5: Location & Logistics

### 5.1 GPS/Map Integration

**Third-party Service:**
- Google Maps API (requires API key and payment)
- Alternative: Mapbox or OpenStreetMap

**Features:**
- Seller location capture
- Junkshop location display
- Distance calculation
- Estimated pickup time
- Pickup route optimization

**Permissions:**
- Require user location permission
- Store location history for trending
- Privacy considerations

### 5.2 Distance-Based Matching

**Matching Algorithm:**
- Filter junkshops by:
  - Distance from seller
  - Operating hours
  - Material types accepted
  - Current availability
- Suggest closest/best-match junkshops

### 5.3 Real-Time Tracking

**During Pickup:**
- Junkshop driver location
- Live tracking for seller
- ETA updates
- Route optimization

### 5.4 Pickup Verification

- Photo evidence at pickup
- GPS location confirmation
- Material verification
- Completion photo/verification

### 5.5 Estimated Implementation Time

- Map integration: 1-2 weeks
- Geocoding setup: 1 week
- Algorithm development: 2-3 weeks
- Testing: 1 week
- **Total: 5-7 weeks**

---

## Phase 6: Notifications & Communication

### 6.1 Notification Types

**Email Notifications:**
- Booking confirmation
- Assignment notification
- Pickup appointment reminder
- Assessment complete
- Payment sent
- Account updates

**In-App Notifications:**
- Real-time alerts
- Booking status changes
- Messages from junkshops/sellers
- System announcements

**SMS Notifications (Future):**
- Requires Twilio or similar service
- Appointment reminders
- Payment confirmations
- Account alerts

### 6.2 Notification Preferences

**User Controls:**
- Email opt-in/opt-out
- In-app notification settings
- Frequency preferences
- Notification categories

### 6.3 Messaging System

**Seller ↔ Junkshop Communication:**
- In-app messaging
- Message history
- Read receipts
- Photo sharing
- Timestamp tracking

### 6.4 Email Templates

- Styled HTML emails
- Responsive design
- Brand consistency
- Unsubscribe link
- Contact information

### 6.5 Database Schema Additions

Tables needed:
- `notifications` - Notification records
- `messages` - User messages
- `user_preferences` - Notification settings
- `email_logs` - Email tracking

### 6.6 Estimated Implementation Time

- Email setup: 1-2 weeks
- In-app notifications: 1-2 weeks
- Messaging system: 2 weeks
- Testing: 1 week
- **Total: 5-7 weeks**

---

## Phase 7: Admin Dashboard & Analytics

### 7.1 User Management

**Admin Functions:**
- View all seller accounts
- View all junkshop accounts
- Approve/reject junkshop registrations
- Deactivate accounts
- Reset passwords
- View user activity

### 7.2 Analytics & Reporting

**Metrics Tracked:**
- Total registrations
- Active sellers/junkshops
- Completed transactions
- Average transaction value
- Commission revenue
- Platform growth trends

**Reports:**
- Daily/weekly/monthly reports
- Junkshop performance metrics
- Seller satisfaction ratings
- Material type popularity
- Geographic distribution

### 7.3 Dispute Management

**Dispute Handling:**
- Complaint submission
- Dispute investigation
- Resolution options
- Refund processing
- Appeal process

**Admin Features:**
- View all disputes
- Communication with parties
- Resolution documentation
- Appeal management

### 7.4 System Settings

**Configurable:**
- Commission percentages
- Service fees
- Operating hours
- Material types
- Geographic service area
- Price thresholds

### 7.5 Estimated Implementation Time

- Dashboard structure: 1-2 weeks
- Analytics queries: 2 weeks
- Report generation: 1-2 weeks
- Dispute system: 2 weeks
- Testing: 1 week
- **Total: 7-8 weeks**

---

## Phase 8: Reviews & Ratings

### 8.1 Rating System

**After Completion:**
- Seller rates junkshop (1-5 stars)
- Junkshop rates seller (1-5 stars)
- Written reviews (optional)
- Photo evidence

**Categories:**
- Professionalism
- Fair assessment
- Communication
- Punctuality
- Safety/cleanliness

### 8.2 Reputation Management

**Seller Profile:**
- Average rating
- Review count
- Response rating
- Reliability score

**Junkshop Profile:**
- Average rating
- Review count
- Assessment fairness
- Professionalism score
- Buyer satisfaction

### 8.3 Review Display

- Public reviews on profile
- Helpful vote system
- Admin moderation
- Report inappropriate reviews

### 8.4 Database Schema Additions

Tables needed:
- `reviews` - Review records
- `ratings` - Rating scores
- `review_moderation` - Admin actions on reviews

### 8.5 Estimated Implementation Time

- Backend: 1-2 weeks
- Frontend: 1 week
- Moderation system: 1 week
- Testing: 1 week
- **Total: 4-5 weeks**

---

## Phase 9: Advanced Features

### 9.1 Scheduled Pickups

- Recurring pickups
- Scheduled collection days
- Automatic booking generation
- Volume discounts for scheduled pickups

### 9.2 Bulk Requests

- Large quantity discounts
- Multi-junkshop coordination
- Volume tracking
- Enterprise accounts

### 9.3 API Development

- Third-party integrations
- Junkshop inventory management
- Market price feeds
- Data exports

### 9.4 Mobile Application

- Native iOS app
- Native Android app
- Push notifications
- Offline capabilities

---

## Implementation Timeline Summary

| Phase | Feature | Duration | Priority |
|-------|---------|----------|----------|
| 1 | Auth & Landing | Complete | Complete |
| 2 | Booking System | 5-6 weeks | High |
| 3 | Pricing & Assessment | 4-6 weeks | High |
| 4 | Payments | 9-12 weeks | High |
| 5 | GPS & Logistics | 5-7 weeks | Medium |
| 6 | Notifications | 5-7 weeks | Medium |
| 7 | Admin Dashboard | 7-8 weeks | High |
| 8 | Reviews & Ratings | 4-5 weeks | Medium |
| 9 | Advanced Features | Ongoing | Low |

---

## Technology Stack for Future Phases

### Current (Phase 1)
- PHP 8.0+
- MySQL/MariaDB
- Bootstrap 5
- Vanilla JavaScript

### Phase 2-3
- Same stack
- No additional frameworks planned

### Phase 4 (Payments)
- **Stripe PHP SDK** or **PayMaya SDK**
- Payment processing libraries
- Security frameworks (PCI compliance)

### Phase 5 (Location)
- **Google Maps API** or **Mapbox**
- Geolocation libraries
- Routing algorithms

### Phase 6 (Notifications)
- **PHPMailer** or **SendGrid**
- **Twilio** (for SMS)
- WebSocket libraries (for real-time)

### Phase 9 (Mobile)
- Flutter or React Native (cross-platform)
- Firebase Cloud Messaging
- Native libraries

---

## Important Notes

1. **Local Development Only:**
   - Phases 4-9 require third-party services
   - Many services have free tier for testing
   - Payment processing requires PCI compliance for production

2. **Budget Considerations:**
   - API keys and services may require payment in production
   - Third-party service costs:
     - Google Maps: $0.01-0.07 per request
     - Twilio SMS: $0.0075 per SMS
     - Email service: $0-20+ per month depending on volume
     - Payment processing: 2-3% per transaction

3. **User Privacy:**
   - GPS data must be encrypted
   - Location history requires user consent
   - Data retention policies needed
   - GDPR/local data protection compliance

4. **Scaling Considerations:**
   - Database optimization needed as users grow
   - Caching layer (Redis) for performance
   - Load balancing for high traffic
   - CDN for static assets

---

## Contact & Support

For questions about implementation or roadmap:
- Contact EcoPick development team
- Review Phase 1 documentation
- Check Git commit history for implementation decisions

---

**Document Version:** 1.0  
**Last Updated:** 2026-08-29  
**Next Review:** After Phase 2 completion
