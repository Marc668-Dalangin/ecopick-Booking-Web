# EcoPick - Phase 1 Complete

> A platform connecting recyclable-material sellers in Lipa City with registered partner junkshops.

## 📋 Project Overview

**EcoPick** is a marketplace facilitator platform that connects:
- **Sellers** - Individuals with recyclable materials to sell
- **Junkshops** - Registered businesses that collect and assess materials
- **Administrators** - EcoPick team managing the platform

⚠️ **Important:** EcoPick is a **facilitator only**. Registered junkshops handle all collection, weighing, assessment, and payment. EcoPick does not buy, collect, transport, or store materials.

## 🎯 Phase 1 Status: ✅ COMPLETE

Phase 1 focuses on:
- ✅ Polished, responsive public landing page
- ✅ Fully working authentication system
- ✅ User registration (Seller and Junkshop)
- ✅ Role-based login and redirects
- ✅ Database schema and stored procedures
- ✅ Admin login and dashboard
- ✅ Organized project structure
- ✅ Security best practices (CSRF, password hashing, etc.)

### What's NOT Included in Phase 1

- ❌ Booking system
- ❌ Matching algorithm
- ❌ Pickup workflow
- ❌ Payment processing
- ❌ GPS/Maps
- ❌ Notifications
- ❌ Reviews/Ratings
- ❌ Full admin dashboard

See `docs/future-modules.md` for detailed roadmap.

## 🚀 Quick Start

### Prerequisites

- **XAMPP** (Apache + MySQL + PHP 8.0+)
- **Web Browser** (Chrome, Firefox, Edge, Safari)
- **Text Editor** (VS Code recommended)

### Installation (5 minutes)

1. **Extract Project**
   ```
   Extract to: C:\xampp\htdocs\booking-website-lipacity\
   ```

2. **Start XAMPP**
   - Open XAMPP Control Panel
   - Click "Start" for Apache and MySQL

3. **Import Database**
   - Open: `http://localhost/phpmyadmin`
   - Go to Import tab
   - Select and import: `database/db.sql`, then `database/sp_add.sql`
   - Click Import
   - ✅ Database ready!

4. **Access Application**
   - Public site: `http://localhost/booking-website-lipacity`
   - Admin login: `http://localhost/booking-website-lipacity/admin/login.php`

**Full setup guide:** See `docs/local-setup.md`

## 🔑 Default Accounts

### Admin Account
- **Email:** `admin@ecopick.local`
- **Password:** `AdminEcoPick123`

### Test Accounts
Create test accounts through the registration form:
- Seller: `http://localhost/booking-website-lipacity/user-junkshop/register-seller.php`
- Junkshop: `http://localhost/booking-website-lipacity/user-junkshop/register-junkshop.php`

## 📁 Project Structure

```
booking-website-lipacity/
├── admin/                    # Admin panel
│   ├── controllers/
│   ├── views/
│   ├── login.php            # Admin login page
│   └── dashboard.php        # Admin dashboard
│
├── user-junkshop/           # Public pages
│   ├── login.php            # Login page
│   ├── register.php         # Registration type selection
│   ├── register-seller.php  # Seller registration
│   ├── register-junkshop.php# Junkshop registration
│   ├── dashboard.php        # User dashboard
│   └── logout.php           # Logout handler
│
├── app/                     # Application core (protected)
│   ├── bootstrap.php        # App initialization
│   ├── config/             # Configuration files
│   │   ├── Database.php
│   │   └── Constants.php
│   ├── core/               # Core classes
│   │   ├── Database.php    # PDO wrapper
│   │   └── Session.php     # Session manager
│   ├── controllers/        # Business logic
│   │   ├── LoginController.php
│   │   └── RegistrationController.php
│   ├── middleware/         # Security
│   │   ├── Auth.php        # Authentication
│   │   └── CSRF.php        # CSRF protection
│   ├── repositories/       # Data access (for future)
│   ├── services/           # Services (for future)
│   ├── helpers/            # Utility functions
│   │   ├── Validator.php
│   │   └── UI.php
│   └── views/              # Shared templates
│       ├── header.php      # Navigation and head
│       └── footer.php      # Footer
│
├── assets/                 # Public assets
│   ├── css/
│   │   └── style.css       # Custom styles
│   ├── js/
│   │   └── main.js         # Custom scripts
│   ├── images/             # Images (for future)
│   └── vendor/             # Third-party libraries
│       └── README.md
│
├── database/               # Database files (protected)
│   ├── db.sql              # Consolidated schema and seed data
│   └── sp_add.sql          # Consolidated procedures and triggers
│
├── docs/                   # Documentation (protected)
│   ├── local-setup.md      # Setup instructions
│   └── future-modules.md   # Roadmap
│
├── index.php               # Landing page
├── .htaccess               # Apache configuration
└── README.md               # This file
```

## 🔐 Security Features

Phase 1 implements:

- ✅ **Password Hashing** - PHP `password_hash()` and `password_verify()`
- ✅ **CSRF Protection** - Token-based CSRF prevention
- ✅ **Session Management** - Session regeneration after login
- ✅ **Input Validation** - Both client and server-side
- ✅ **Output Escaping** - `htmlspecialchars()` for all user output
- ✅ **Prepared Statements** - PDO parameterized queries
- ✅ **Protected Directories** - `.htaccess` prevents direct access to sensitive files
- ✅ **Security Headers** - X-Frame-Options, X-Content-Type-Options, etc.

See `app/middleware/CSRF.php` and `app/middleware/Auth.php` for implementation details.

## 🛠️ Technology Stack

### Backend
- **PHP** 8.0+
- **MySQL** 5.7+ / MariaDB 10.3+
- **PDO** for database access
- **Stored Procedures** for all database operations

### Frontend
- **HTML5**
- **CSS3**
- **Vanilla JavaScript** (no frameworks)
- **Bootstrap 5** - Responsive framework
- **Bootstrap Icons** - Icon library

### Other
- **Apache** web server
- **mod_rewrite** for URL routing
- **.htaccess** for security and configuration

## 📊 Database Schema

### Tables
- `roles` - User roles (seller, junkshop, admin)
- `accounts` - User accounts with authentication
- `seller_profiles` - Seller-specific info
- `junkshop_profiles` - Junkshop-specific info

### Stored Procedures
- `sp_register_seller` - Register seller account
- `sp_register_junkshop` - Register junkshop account
- `sp_get_login_user_by_email` - Authentication
- `sp_get_account_by_id` - Get user details
- `sp_create_local_admin` - Create admin account

## 🎨 Design Features

### Landing Page Sections
1. **Sticky Navigation Bar** - Responsive nav with login/register
2. **Hero Section** - Clear value proposition
3. **How It Works** - 6-step process explanation
4. **Recyclable Materials** - 6 material categories
5. **Partner Junkshops** - Sample verified partners
6. **Waste Segregation Tips** - Education content
7. **Call-to-Action** - Registration prompts
8. **Contact Section** - Support information
9. **Professional Footer** - Links and info

### Design Principles
- ✅ Clean and modern
- ✅ Eco-friendly but professional
- ✅ Mobile-responsive (tested on 320px+)
- ✅ Accessible (ARIA labels, semantic HTML)
- ✅ Color palette: Green/teal with warm accent
- ✅ Consistent spacing and typography

## 🔄 User Workflows

### Seller Registration Flow
1. Visit registration page
2. Choose "Register as Seller"
3. Fill form (name, email, address, password)
4. Agree to terms
5. Account created (active immediately)
6. Can login and access dashboard

### Junkshop Registration Flow
1. Visit registration page
2. Choose "Register Your Junkshop"
3. Fill detailed form (business info, permit, etc.)
4. Agree to terms
5. Account created (status: pending)
6. Must wait for admin approval
7. Receives approval email
8. Can then access dashboard

### Login Flow
1. Visit login page
2. Enter email and password
3. System verifies credentials
4. Checks account status
5. For junkshops: checks approval status
6. Sets secure session
7. Redirects to dashboard

### Logout Flow
1. Click logout button
2. Session destroyed
3. Redirect to login page

## 📱 Responsive Design

Tested breakpoints:
- **Mobile** (320px - 576px)
- **Tablet** (576px - 992px)
- **Desktop** (992px+)

Features:
- Hamburger menu on mobile
- Stacked layout on small screens
- Touch-friendly button sizes
- Readable font sizes at all sizes

## ✅ Testing Checklist

### Phase 1 Verification
- [x] Database imports without errors
- [x] Landing page displays correctly
- [x] Navigation links work
- [x] Seller registration works
- [x] Junkshop registration works
- [x] Duplicate email handling
- [x] Empty field validation
- [x] Password show/hide toggle
- [x] Login with valid credentials
- [x] Login with invalid credentials
- [x] Role-based redirects
- [x] Admin login works
- [x] Logout works
- [x] Dashboard access control
- [x] Mobile responsiveness
- [x] No console errors
- [x] No broken links
- [x] No broken images
- [x] CSRF protection active
- [x] Passwords hashed in database

## 📝 Documentation

- **`docs/local-setup.md`** - Complete setup instructions
  - XAMPP configuration
  - Database import steps
  - Virtual host setup
  - Troubleshooting guide

- **`docs/future-modules.md`** - Development roadmap
  - Phase 2-9 features
  - Implementation timeline
  - Third-party services needed
  - Budget considerations

- **`database/db.sql`** - Consolidated database schema and seed data
- **`database/sp_add.sql`** - Consolidated stored procedures and triggers
  - Schema explanation
  - Stored procedures
  - Default credentials
  - Backup/restore

## 🐛 Known Limitations

These are intentional Phase 1 limitations:

1. **No Booking System** - Form-only demo of process
2. **No Matching** - Junkshops listed as samples
3. **No Payment** - Future implementation
4. **No GPS/Maps** - Future implementation
5. **No Email/SMS** - Future implementation
6. **No Mobile App** - Web-only for Phase 1
7. **Basic Admin Dashboard** - Full features in Phase 2
8. **No Analytics** - Coming in Phase 7
9. **No API** - Coming in Phase 9
10. **Localhost Only** - Not for production use

## 🚀 Next Steps (Phase 2)

See `docs/future-modules.md` for detailed roadmap. Next phase includes:
- Booking system with status workflow
- Junkshop matching algorithm
- Admin approval workflow
- Notification system
- Material pricing system

## 📞 Support

For setup or development questions:

1. **Read documentation first**
   - `docs/local-setup.md` - Setup issues
   - `docs/local-setup.md` - Database issues
   - `assets/vendor/README.md` - Bootstrap/vendor issues

2. **Check browser console**
   - Press F12 in browser
   - Look for JavaScript errors
   - Check Network tab for failed requests

3. **Check server logs**
   - PHP errors: `C:\xampp\php\logs\php_error.log`
   - Apache errors: `C:\xampp\apache\logs\error.log`

4. **Common issues in setup guide**
   - See "Troubleshooting" section in `docs/local-setup.md`

## 📄 License

This project is developed for EcoPick (Lipa City). All code is original and created for this platform.

## 🙏 Credits

**Phase 1 Developer:** GitHub Copilot with human guidance

**Technology:**
- Bootstrap 5 by The Bootstrap Team
- Bootstrap Icons by The Bootstrap Team
- PHP by PHP Foundation
- MySQL by Oracle

## 📅 Version Information

- **Version:** 1.0
- **Phase:** 1 (Complete)
- **Release Date:** 2026-08-29
- **Status:** Production Ready (Local Use Only)

---

**Last Updated:** 2026-08-29

For the most current documentation, see the `docs/` folder.
