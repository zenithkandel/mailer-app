############################################

# MAIL APP CONFIGURATION

# ZenithKandel Mail Server

############################################

#########################

# APP INFO

#########################

APP_NAME=Zenith Mail
APP_URL=https://yourdomain.com

#########################

# LOGIN ACCOUNT

#########################

EMAIL=admin@zenithkandel.com.np
PASSWORD=YOUR_EMAIL_PASSWORD

#########################

# SMTP (SEND MAIL)

#########################

SMTP_HOST=mail.zenithkandel.com.np
SMTP_PORT=465
SMTP_SECURITY=ssl

SMTP_USER=admin@zenithkandel.com.np
SMTP_PASS=YOUR_EMAIL_PASSWORD

SMTP_FROM=admin@zenithkandel.com.np
SMTP_FROM_NAME=ZenithKandel

#########################

# IMAP (READ MAILS)

#########################

IMAP_HOST=mail.zenithkandel.com.np
IMAP_PORT=993
IMAP_SECURITY=ssl

IMAP_USER=admin@zenithkandel.com.np
IMAP_PASS=YOUR_EMAIL_PASSWORD

IMAP_MAILBOX={mail.zenithkandel.com.np:993/imap/ssl}INBOX

#########################

# POP3 (OPTIONAL)

#########################

POP3_HOST=mail.zenithkandel.com.np
POP3_PORT=995
POP3_SECURITY=ssl

POP3_USER=admin@zenithkandel.com.np
POP3_PASS=YOUR_EMAIL_PASSWORD

- Build a complete webmail application using ONLY pure PHP, HTML, CSS, and vanilla JavaScript
- Do NOT use any frameworks, Composer, Node.js, npm, Laravel, Symfony, React, Vue, or external dependencies
- The project must run by simply uploading files to a PHP server/XAMPP
- Use built-in PHP IMAP functions for reading emails
- Use built-in PHP mail() or raw SMTP sockets for sending emails
- Store configuration in a simple config.php or config.json file

FEATURES:

AUTHENTICATION

- Login page
- Session-based authentication
- Logout functionality
- Remember login session
- Protected routes/pages

DASHBOARD

- Gmail-like modern dashboard
- Sidebar navigation
- Responsive design
- Dark mode UI
- Mobile-friendly layout

MAIL FEATURES

- Inbox page
- Sent mails page
- Drafts page
- Starred mails
- Unread mails section

EMAIL READING

- Open/read emails
- HTML email rendering
- Plain text fallback
- Email header details
- Sender information
- Recipient information
- Date/time display
- Attachment listing
- Inline image support

EMAIL COMPOSING

- Compose new email
- Reply to email
- Reply all
- Forward email
- Rich textarea editor
- HTML email support
- Attachment upload
- Multiple recipients
- CC support
- BCC support
- Save draft

EMAIL MANAGEMENT

- Delete email
- Mark read/unread
- Star/unstar emails
- Search emails
- Filter emails
- Refresh inbox
- Bulk email actions
- Pagination/infinite scrolling

IMAP FEATURES

- Fetch emails from IMAP server
- Sync folders
- Detect unread emails
- Count unread messages
- Fetch attachments
- Move emails between folders
- Delete emails from server

SMTP FEATURES

- Send emails through SMTP
- SSL/TLS support
- SMTP authentication
- Custom sender name
- HTML email sending

CONFIGURATION

- config.php or config.json setup
- SMTP config
- IMAP config
- Mailbox config
- Folder mapping config

SECURITY

- CSRF protection
- XSS sanitization
- Session security
- Secure password handling
- Input validation

OPTIONAL ADVANCED FEATURES

- Live inbox refresh
- Notification sounds
- Email threading/conversations
- Keyboard shortcuts
- Drag and drop attachments
- Theme switcher
- PWA support

IMPORTANT

- No database unless absolutely necessary
- Prefer JSON/file-based storage
- Clean code structure
- Classic beige backgorund sharp edges orangish premium UI
- Fast loading
- Minimal setup required
