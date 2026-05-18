# Zenith Mail — Admin Mailer App

A simple, deployable PHP mailer app. No Composer, no database — just drop and run.

---

## Features

- Admin login with username/password
- View received emails (IMAP)
- Send emails (SMTP via socket)
- View sent mail history (JSON log)
- Unread count badge
- Search within inbox
- Load-more pagination
- CSRF protection
- XSS sanitization on email display
- Beige + burnt orange minimal design

---

## Requirements

- PHP 7.4+ with `imap` extension enabled
- IMAP mailbox (any email provider)
- SMTP access (same or different provider)
- Web server (Apache/Nginx) with PHP

---

## Deployment

### Step 1: Enable PHP IMAP Extension

**XAMPP:** Open `C:\xampp\php\php.ini`, find `;extension=imap`, remove the `;` to uncomment it. Restart Apache.

**Linux:** `sudo apt-get install php-imap` or `sudo dnf install php-imap`

**cPanel/Shared Hosting:** Look for "PHP Extensions" in your panel, enable `imap`.

### Step 2: Upload Files

Copy all files to your web root or a subfolder.

Example paths:
- XAMPP: `C:\xampp\htdocs\mailer-app\`
- Linux: `/var/www/html/mailer-app/`
- cPanel: `public_html/mailer-app/`

### Step 3: Configure

Edit `data/config.json`:

```json
{
  "app_name": "Your Mail",
  "admin_user": "admin",
  "admin_pass": "your_password",
  "smtp": {
    "host": "mail.yourdomain.com",
    "port": 465,
    "security": "ssl",
    "user": "you@yourdomain.com",
    "pass": "your_email_password",
    "from_email": "you@yourdomain.com",
    "from_name": "Your Name"
  },
  "imap": {
    "host": "mail.yourdomain.com",
    "port": 993,
    "security": "ssl",
    "user": "you@yourdomain.com",
    "pass": "your_email_password"
  }
}
```

### Step 4: Set File Permissions

On Linux/Apache, ensure the logs directory is writable:

```bash
chmod 755 logs/
chmod 755 data/
```

### Step 5: Access the App

Visit `http://localhost/mailer-app/` (or your domain path) and login with the credentials you set in `config.json`.

---

## File Structure

```
mailer-app/
├── index.php          Login page
├── dashboard.php      Main dashboard
├── api/
│   ├── config.php     Shared configuration
│   ├── csrf.php       CSRF token helpers
│   ├── helpers.php    Email utilities & SMTP class
│   ├── login.php      Login handler
│   ├── logout.php     Logout handler
│   ├── inbox.php      IMAP email fetching
│   ├── send.php       SMTP email sending
│   └── sent.php       Sent mail JSON reader
├── assets/
│   ├── css/style.css  All styles
│   └── js/app.js      Frontend JS
├── data/
│   ├── config.json    Your credentials (protected by .htaccess)
│   └── .htaccess      Blocks direct web access
├── logs/
│   ├── .htaccess      Blocks direct web access
│   └── sent.json      Outbound email log
└── README.md
```

---

## Security Notes

- `data/` and `logs/` directories are protected by `.htaccess` — credentials are not publicly accessible
- CSRF tokens are generated per session and validated on all POST requests
- Email bodies are sanitized (script tags stripped) before display
- Session cookies have `HttpOnly` flag set
- Change the default password in `config.json` before deploying

---

## SMTP Compatibility

Tested with common email providers:
- cPanel / Plesk hosted email (mail.domain.com)
- Gmail / Google Workspace (smtp.gmail.com — requires App Password)
- Outlook / Microsoft 365
- Any standard SMTP server on ports 465 (SSL) or 587 (TLS)

For Gmail, you'll need an **App Password** (not your regular password). Generate one at: myaccount.google.com → Security → 2-Step Verification → App Passwords.

---

## Troubleshooting

**"IMAP extension is not available"** → Enable `extension=imap` in php.ini and restart Apache.

**"Failed to connect to IMAP server"** → Check your `host`, `port`, and `security` settings in config.json. Make sure the IMAP port (usually 993) is open and not blocked by firewall.

**"Failed to connect to SMTP server"** → Check your SMTP credentials. Try port 587 with `tls` security if 465 with `ssl` doesn't work. Some hosts require an App Password instead of the account password.

**Login not working** → Make sure `data/config.json` has valid JSON syntax (no trailing commas). Check the `admin_user` and `admin_pass` fields match exactly.

---

## Design

- Background: `#F5F0E8` (warm beige)
- Accent: `#E87B35` (burnt orange)
- Font: Inter (Google Fonts)
- Style: Minimal, sharp edges, clean grid
- Fully responsive down to mobile