# Zenith Mail - Agent Instructions

## Quick Start
```bash
# Access the app
http://localhost/codes/mailer-app/
# Login: admin / 8038@Zenith (from config.json)
```

## Architecture
- **Dashboard**: `dashboard.php` - nav bar + iframe that loads `pages/*.php`
- **Pages**: `pages/inbox.php`, `pages/compose.php`, `pages/sent.php`, `pages/drafts.php`, `pages/starred.php`, `pages/trash.php`, `pages/settings.php`
- **API**: `api/mail.php`, `api/auth.php`, `api/settings.php`, `api/smtp.php`
- **Config**: `data/config.json` - all SMTP/IMAP settings (passwords stored here)

## Key Files
- `dashboard.php` - Main entry, handles nav and iframe routing (?view=inbox/compose/etc)
- `pages/inbox.php` - Email list in iframe, communicates with parent via parent.viewEmail()
- `api/mail.php` - IMAP fetch, send, delete, mark operations
- `api/smtp.php` - Raw socket SMTP sending (no PHPMailer)

## Common Commands
```bash
# Check PHP syntax
php -l dashboard.php
php -l api/mail.php
```

## Important Notes
- IMAP extension required: enable in php.ini (`extension=imap`)
- Passwords in config.json preserved if left empty during settings save
- URL is bookmarkable: `dashboard.php?view=compose` loads compose page
- Mobile: bottom nav appears on screens < 768px
- Iframe pages share session via PHP session_start()

## Issues to Watch
- IMAP not loaded: Check XAMPP php.ini, enable `extension=imap`
- Empty JSON response: Ensure logged in (session required)
- Nested ternary syntax error: Use if/else instead of `? : ?:` in PHP

## Testing Email Flow
1. Configure SMTP/IMAP in `data/config.json` or via Settings page
2. Use "Test SMTP"/"Test IMAP" buttons in Settings to verify
3. Compose sends via raw sockets in `api/smtp.php` (not phpMailer)