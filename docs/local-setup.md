# EcoPick Local Setup Guide

## Environment

This is a **localhost-only development environment** for EcoPick Phase 1.


## Prerequisites

1. **XAMPP** (or equivalent Apache + MySQL + PHP stack)
   - Download from: https://www.apachefriends.org
   - Version: 8.0+ recommended

2. **phpMyAdmin** (usually included with XAMPP)

3. **Text Editor or IDE**
   - VS Code recommended

## Installation Steps

### Step 1: Extract Project Files

Extract the EcoPick project to your XAMPP root:

```
C:\xampp\htdocs\booking-website-lipacity\
```

Ensure you have the following structure:

```
booking-website-lipacity/
├── admin/
├── user-junkshop/
├── app/
├── assets/
├── database/
├── docs/
├── index.php
└── .htaccess
```

### Step 2: Start XAMPP Services

1. Open XAMPP Control Panel
2. Start **Apache** module
3. Start **MySQL** module

### Step 3: Create Database

#### Option A: Using phpMyAdmin (Recommended)

1. Open browser: `http://localhost/phpmyadmin`
2. Click **"Import"** in top menu
3. Click **"Choose File"** button
4. Select: `database/db.sql`, then import `database/sp_add.sql`
5. Click **"Go"** or **"Import"** button
6. Database and tables will be created automatically

#### Option B: Using MySQL Command Line

```bash
mysql -u root -p < database/db.sql
mysql -u root -p < database/sp_add.sql
```

(Press Enter when prompted for password - default is empty in XAMPP)

### Step 4: Verify Database Setup

1. Go to `http://localhost/phpmyadmin`
2. Look for `ecopickdb` database in the left panel
3. Click on it to expand and verify these tables:
   - `roles`
   - `accounts`
   - `seller_profiles`
   - `junkshop_profiles`

All tables should be present and the `accounts` table should have 1 default admin account.

## Local URLs

### Without Virtual Hosts (Direct Access)

```
Public Site: http://localhost/booking-website-lipacity
Admin Login: http://localhost/booking-website-lipacity/admin/login.php
```

### With Virtual Hosts (Optional - Advanced)

If you want to use the planned URLs:

#### Edit Windows Hosts File

1. Open Notepad as Administrator
2. Open file: `C:\Windows\System32\drivers\etc\hosts`
3. Add these lines at the end:

```
127.0.0.1 ecopick-lipa.localhost
127.0.0.1 ecopick-admin.localhost
```

4. Save and close

#### Configure Apache Virtual Hosts

1. Open: `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
2. Add at the end:

```apache
<VirtualHost *:80>
    ServerName ecopick-lipa.localhost
    ServerAlias www.ecopick-lipa.localhost
    DocumentRoot "C:\xampp\htdocs\booking-website-lipacity"
    
    <Directory "C:\xampp\htdocs\booking-website-lipacity">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost *:80>
    ServerName ecopick-admin.localhost
    DocumentRoot "C:\xampp\htdocs\booking-website-lipacity"
    
    <Directory "C:\xampp\htdocs\booking-website-lipacity">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

3. Save file
4. Restart Apache from XAMPP Control Panel

Then access via:
- `http://ecopick-lipa.localhost`
- `http://ecopick-admin.localhost`

## Database Configuration

If using a custom MySQL setup:

1. Edit: `app/config/Database.php`
2. Update connection details:

```php
define('DB_HOST', 'localhost');    // MySQL host
define('DB_USER', 'root');         // MySQL user
define('DB_PASS', '');             // MySQL password
define('DB_NAME', 'ecopickdb');
```

## Default Accounts

### Admin Account

**Email:** `admin@ecopick.local`
**Password:** `AdminEcoPick123`

⚠️ **IMPORTANT:** Change these credentials immediately if using this on a shared/public server.

### Test Seller Account

Can be created through the public registration form:
- Navigate to: `http://localhost/booking-website-lipacity/user-junkshop/register.php`
- Choose "Register as Seller"
- Fill in the form and submit
- You can immediately login with this account

### Test Junkshop Account

Can be created through the public registration form:
- Navigate to: `http://localhost/booking-website-lipacity/user-junkshop/register.php`
- Choose "Register Your Junkshop"
- Fill in the form and submit
- Account will be in "pending" status
- Admin must approve it before you can fully use it

To approve a junkshop:
1. Login as admin: `http://localhost/booking-website-lipacity/admin/login.php`
2. Full admin dashboard coming in Phase 2
3. For now, you can manually update in phpMyAdmin:
   - Go to `junkshop_profiles` table
   - Change `approval_status` from 'pending' to 'approved'

## Testing the System

### Quick Test Workflow

1. **Test Public Site**
   - Open: `http://localhost/booking-website-lipacity`
   - Verify all sections load correctly
   - Check responsive design on mobile

2. **Test Seller Registration**
   - Go to: `/user-junkshop/register.php`
   - Click "Register as Seller"
   - Fill form with test data
   - Submit and verify success message
   - Login with new account

3. **Test Junkshop Registration**
   - Go to: `/user-junkshop/register.php`
   - Click "Register Your Junkshop"
   - Fill form with test data
   - Submit and verify pending message

4. **Test Admin Login**
   - Go to: `/admin/login.php`
   - Login with admin@ecopick.local / AdminEcoPick123
   - Verify admin dashboard loads

5. **Test Logout**
   - From any dashboard, click Logout
   - Verify redirect to login page

## File Permissions

Ensure these directories are writable (if needed for future phases):

```bash
chmod 755 database/
chmod 755 app/
chmod 755 assets/
```

On Windows with XAMPP, this is usually not needed.

## Troubleshooting

### Database Connection Error

**Problem:** "Database connection failed"

**Solution:**
1. Check MySQL service is running in XAMPP Control Panel
2. Verify database name in `app/config/Database.php`
3. Verify database credentials (user, password)
4. Check `ecopickdb` database exists in phpMyAdmin

### PHP Warnings/Errors

**Problem:** Errors displaying on page instead of error log

**Solution:**
- This is normal for development. In `app/bootstrap.php`, errors are logged to file instead of display in production.
- Check PHP error log: `C:\xampp\php\logs\php_error.log`

### 404 Errors on Pages

**Problem:** Getting 404 errors

**Solution:**
1. Verify mod_rewrite is enabled in Apache
2. Check `.htaccess` file exists in project root
3. Verify file paths are correct
4. Access page using direct URL (e.g., `index.php?page=...`)

### Can't Access Admin Login

**Problem:** Admin login page doesn't load

**Solution:**
1. Use direct URL: `http://localhost/booking-website-lipacity/admin/login.php`
2. Clear browser cache
3. Check file permissions on `admin/login.php`

### Form Submission Not Working

**Problem:** Form doesn't submit

**Solution:**
1. Check browser console for JavaScript errors (F12)
2. Verify form has valid CSRF token
3. Check server error log: `C:\xampp\apache\logs\error.log`
4. Ensure all required fields are filled

## Security Notes

⚠️ **LOCAL DEVELOPMENT ONLY**

- This setup is not suitable for production
- Default credentials must be changed
- No SSL/HTTPS configured
- Database credentials are visible in source code
- No rate limiting or DDoS protection
- Session security features are basic

For production deployment:
- Use environment variables for credentials
- Implement HTTPS/SSL
- Add rate limiting and security headers
- Use a password manager for admin credentials
- Implement database backups
- Set up proper access controls
- Enable firewall rules

## Next Steps

After Phase 1 is working:

### Phase 2 Features
- User management dashboard for admins
- Junkshop approval workflow
- Booking system
- Payment processing (local sandbox)
- Notifications

### Phase 3+ Features
- GPS/Maps integration
- Real payment processing
- SMS notifications
- Email communications
- Analytics and reporting

See `docs/future-modules.md` for detailed roadmap.

## Support

For local development issues:
1. Check this guide first
2. Review error messages and logs
3. Test in a fresh XAMPP installation
4. Check all prerequisites are met
5. Verify file permissions and configurations

## Useful Commands

### Restart Services

```bash
# In XAMPP Control Panel, click:
# Stop All Services
# Wait 2 seconds
# Start Apache
# Start MySQL
```

### Access Database via Command Line

```bash
mysql -u root -p ecopickdb
```

### View PHP Error Log

```bash
# Windows - use Notepad
notepad C:\xampp\php\logs\php_error.log

# Or tail in PowerShell
Get-Content C:\xampp\php\logs\php_error.log -Tail 20 -Wait
```

### Clear Browser Cache

- Chrome/Edge: Ctrl + Shift + Delete
- Firefox: Ctrl + Shift + Delete
- Safari: Cmd + Option + E

## Version Information

- **EcoPick:** Phase 1
- **Bootstrap:** 5.3+
- **PHP:** 8.0+
- **MySQL:** 5.7+ / MariaDB 10.3+
- **Created:** 2026-08-29
