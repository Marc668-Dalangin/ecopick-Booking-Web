# Vendor Dependencies - Bootstrap 5 Setup

## Overview

EcoPick uses locally cached Bootstrap 5 and Bootstrap Icons for stable local development without CDN dependency.

## Installation

### Option 1: Automatic Setup (Recommended)

Run the setup script:

**Windows (PowerShell):**
```powershell
cd assets\vendor
# Copy the setup script and run it (see setup.ps1 below)
```

**Linux/Mac:**
```bash
cd assets/vendor
chmod +x setup.sh
./setup.sh
```

### Option 2: Manual Download

1. **Bootstrap 5 CSS**
   - Download from: https://getbootstrap.com/docs/5.3/getting-started/download/
   - Extract to: `assets/vendor/bootstrap/`
   - Ensure `css/bootstrap.min.css` exists

2. **Bootstrap Icons**
   - Download from: https://icons.getbootstrap.com/
   - Extract to: `assets/vendor/bootstrap-icons/`
   - Ensure `font/bootstrap-icons.css` exists

3. **Folder Structure Should Be:**

```
assets/vendor/
├── bootstrap/
│   ├── css/
│   │   ├── bootstrap.min.css
│   │   └── bootstrap.css
│   └── js/
│       ├── bootstrap.bundle.min.js
│       └── bootstrap.bundle.js
└── bootstrap-icons/
    └── font/
        ├── bootstrap-icons.css
        ├── bootstrap-icons.woff
        └── bootstrap-icons.woff2
```

### Option 3: CDN Fallback

If local vendor files are not available, the header and footer will automatically fall back to CDN links.

Edit `app/views/header.php` to use CDN:

```php
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
```

## Troubleshooting

### Bootstrap Not Styling Correctly

1. Check browser console for 404 errors (F12 → Console)
2. Verify file paths in `app/views/header.php`
3. Clear browser cache (Ctrl+Shift+Delete)
4. Check `assets/vendor/bootstrap/css/` directory exists

### Icons Not Displaying

1. Verify `assets/vendor/bootstrap-icons/font/` directory exists
2. Check `bootstrap-icons.css` file
3. Verify `.woff` and `.woff2` font files are present
4. Clear browser cache

### Forms Looking Wrong

1. Bootstrap CSS file might be missing or corrupt
2. Try CDN version temporarily
3. Re-download Bootstrap 5 files
4. Check `app/views/header.php` includes correct path

## Updates

To update Bootstrap:

1. Download latest version from official site
2. Extract to `assets/vendor/bootstrap/`
3. Overwrite existing files
4. Test all forms and components
5. Update documentation if breaking changes

## Version Info

- **Bootstrap:** 5.3+
- **Bootstrap Icons:** 1.10+
- **Updated:** 2026-08-29

## Additional Resources

- Bootstrap Documentation: https://getbootstrap.com/docs/5.3/
- Bootstrap Icons: https://icons.getbootstrap.com/
- Bootstrap GitHub: https://github.com/twbs/bootstrap
- Icons GitHub: https://github.com/twbs/icons

## Notes

- Using local files prevents CDN dependency issues
- Faster loading times with local caching
- Offline development capability
- No tracking or analytics from vendor libraries
- Recommended for production to reduce external dependencies
