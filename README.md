# WebMail - Retro Webmail Application

A full-featured webmail client built with PHP and vanilla JavaScript.

## Features

- IMAP email reading with full folder support
- Compose emails with Quill.js rich text editor
- Contact management
- Email signatures
- Filters and rules
- Search functionality
- Draft auto-save
- Keyboard shortcuts
- Real-time progress bars

## Requirements

- PHP 7.4+ with `imap` extension
- Composer
- Write permissions on `/data/` directory

## Installation

### 1. Configure Mail Credentials

Edit `config.php` and set your mail password:

```php
define('MAIL_PASS', 'YOUR_PASSWORD_HERE');
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Set Permissions

```bash
chmod -R 755 data/
chmod -R 777 data/
```

On Windows (XAMPP), ensure the data folder has write permissions.

### 4. Enable IMAP Extension

Ensure PHP has the IMAP extension enabled in `php.ini`:

```ini
extension=imap
```

### 5. Start PHP Server

```bash
php -S localhost:8000
```

Then open http://localhost:8000 in your browser.

## Configuration

### Mail Server Settings

Edit `config.php` to configure your mail server:

```php
define('MAIL_HOST', 'mail.zenithkandel.com.np');
define('MAIL_USER', 'admin@zenithkandel.com.np');
define('IMAP_PORT', 993);
define('SMTP_PORT', 465);
```

### Automatic Scheduled Emails

To send scheduled emails, add a cron job:

```bash
* * * * * php /path/to/cron.php
```

### Running Filters

To apply filters to existing emails:

```bash
php apply_filters.php INBOX 100
```

## Keyboard Shortcuts

| Key | Action |
|-----|--------|
| C | Compose new |
| R | Reply |
| F | Forward |
| D | Delete selected |
| U | Mark unread |
| S | Star |
| J | Next email |
| K | Previous email |
| G I | Go to Inbox |
| G S | Go to Sent |
| G D | Go to Drafts |
| ? | Show shortcuts |
| Esc | Close modal |

## Security Notes

- Sessions expire after 24 hours of inactivity
- CSRF tokens protect all POST requests
- External images are blocked by default
- Password is validated against `config.php`

## License

MIT