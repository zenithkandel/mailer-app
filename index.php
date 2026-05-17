<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebMail - Retro Email Client</title>
    <link rel="stylesheet" href="https://cdn.quilljs.com/1.3.7/quill.snow.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div id="login-screen" class="hidden">
        <div class="login-box">
            <h1>WebMail Login</h1>
            <form id="login-form">
                <label>Password:</label>
                <input type="password" id="login-password" required>
                <button type="submit">Login</button>
            </form>
            <div id="login-error" class="error"></div>
        </div>
    </div>

    <div id="app-container" class="hidden">
        <header id="header-bar">
            <div class="logo">WebMail</div>
            <div class="search-container">
                <input type="text" id="global-search" placeholder="Search emails...">
                <button id="search-btn">Search</button>
            </div>
            <div class="account-info">
                <span id="account-email">admin@zenithkandel.com.np</span>
                <button id="logout-btn">Logout</button>
            </div>
        </header>

        <div id="main-layout">
            <aside id="sidebar">
                <button id="compose-btn">+ Compose</button>

                <div id="folder-list">
                    <div class="folder-item active" data-folder="INBOX">
                        <span class="folder-name">Inbox</span>
                        <span class="folder-count" id="inbox-count">0</span>
                    </div>
                </div>

                <div class="sidebar-section">
                    <h3>Drafts</h3>
                    <div id="drafts-list"></div>
                </div>

                <div class="sidebar-actions">
                    <button id="empty-trash-btn">Empty Trash</button>
                    <button id="empty-spam-btn">Empty Spam</button>
                </div>

                <div class="sidebar-bottom">
                    <button id="contacts-btn">Contacts</button>
                    <button id="settings-btn">Settings</button>
                    <button id="filters-btn">Filters</button>
                </div>
            </aside>

            <div id="message-list-panel">
                <div class="toolbar">
                    <div class="toolbar-left">
                        <button class="tool-btn" id="refresh-btn" title="Refresh">Refresh</button>
                        <button class="tool-btn" id="delete-btn" title="Delete">Delete</button>
                        <button class="tool-btn" id="mark-read-btn" title="Mark Read">Mark Read</button>
                        <button class="tool-btn" id="mark-unread-btn" title="Mark Unread">Mark Unread</button>
                        <button class="tool-btn" id="spam-btn" title="Spam">Spam</button>
                    </div>
                    <div class="toolbar-right">
                        <select id="sort-select">
                            <option value="date">Date</option>
                            <option value="from">From</option>
                            <option value="subject">Subject</option>
                            <option value="size">Size</option>
                        </select>
                        <select id="sort-dir-select">
                            <option value="DESC">Desc</option>
                            <option value="ASC">Asc</option>
                        </select>
                    </div>
                </div>

                <div id="message-list-container">
                    <table id="message-table">
                        <thead>
                            <tr>
                                <th class="col-check"><input type="checkbox" id="select-all"></th>
                                <th class="col-from">From</th>
                                <th class="col-subject">Subject</th>
                                <th class="col-date">Date</th>
                                <th class="col-size">Size</th>
                            </tr>
                        </thead>
                        <tbody id="message-list"></tbody>
                    </table>
                </div>

                <div id="pagination">
                    <button id="prev-page">Prev</button>
                    <span id="page-info">Page 1 of 1</span>
                    <button id="next-page">Next</button>
                </div>
            </div>

            <div id="message-viewer-panel">
                <div id="viewer-empty" class="empty-state">
                    Select an email to view
                </div>

                <div id="message-viewer" class="hidden">
                    <div class="message-toolbar">
                        <button class="tool-btn" id="reply-btn">Reply</button>
                        <button class="tool-btn" id="reply-all-btn">Reply All</button>
                        <button class="tool-btn" id="forward-btn">Forward</button>
                        <button class="tool-btn" id="delete-msg-btn">Delete</button>
                        <button class="tool-btn" id="raw-btn">Raw</button>
                        <button class="tool-btn" id="print-btn">Print</button>
                    </div>

                    <div class="message-headers">
                        <div class="header-row"><strong>From:</strong> <span id="msg-from"></span></div>
                        <div class="header-row"><strong>To:</strong> <span id="msg-to"></span></div>
                        <div class="header-row" id="msg-cc-row"><strong>CC:</strong> <span id="msg-cc"></span></div>
                        <div class="header-row"><strong>Date:</strong> <span id="msg-date"></span></div>
                        <div class="header-row"><strong>Subject:</strong> <span id="msg-subject"></span></div>
                    </div>

                    <div class="message-body-container">
                        <div id="show-images-banner" class="hidden">
                            <button id="show-images-btn">Show External Images</button>
                        </div>
                        <div id="message-body"></div>
                    </div>

                    <div id="attachments-section" class="hidden">
                        <h4>Attachments</h4>
                        <div id="attachments-list"></div>
                        <button id="download-all-btn">Download All</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="compose-modal" class="modal hidden">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="compose-title">New Message</h2>
                    <button class="close-btn" id="close-compose">&times;</button>
                </div>
                <form id="compose-form">
                    <div class="compose-field">
                        <label>To:</label>
                        <input type="text" id="compose-to" autocomplete="off">
                        <div class="autocomplete-dropdown" id="to-autocomplete"></div>
                    </div>
                    <div class="compose-field">
                        <label>CC:</label>
                        <input type="text" id="compose-cc" autocomplete="off">
                        <div class="autocomplete-dropdown" id="cc-autocomplete"></div>
                    </div>
                    <div class="compose-field">
                        <label>BCC:</label>
                        <input type="text" id="compose-bcc" autocomplete="off">
                        <div class="autocomplete-dropdown" id="bcc-autocomplete"></div>
                    </div>
                    <div class="compose-field">
                        <label>Subject:</label>
                        <input type="text" id="compose-subject">
                    </div>
                    <div class="compose-field">
                        <label>Signature:</label>
                        <select id="compose-signature">
                            <option value="">No Signature</option>
                        </select>
                    </div>
                    <div class="compose-field">
                        <label>Priority:</label>
                        <select id="compose-priority">
                            <option value="1">High</option>
                            <option value="3" selected>Normal</option>
                            <option value="5">Low</option>
                        </select>
                        <label><input type="checkbox" id="compose-read-receipt"> Read Receipt</label>
                    </div>
                    <div id="compose-editor-container">
                        <div id="compose-editor"></div>
                    </div>
                    <div class="compose-attachments">
                        <div id="drop-zone">
                            <span>Drag files here or click to upload</span>
                        </div>
                        <input type="file" id="file-input" multiple hidden>
                        <div id="attachment-list"></div>
                    </div>
                    <div class="compose-actions">
                        <button type="button" id="save-draft-btn">Save Draft</button>
                        <button type="button" id="schedule-btn">Schedule</button>
                        <button type="submit" id="send-btn">Send</button>
                    </div>
                </form>
                <div id="compose-status"></div>
            </div>
        </div>

        <div id="contacts-modal" class="modal hidden">
            <div class="modal-content wide">
                <div class="modal-header">
                    <h2>Contacts</h2>
                    <button class="close-btn" id="close-contacts">&times;</button>
                </div>
                <div class="contacts-toolbar">
                    <input type="text" id="contacts-search" placeholder="Search contacts...">
                    <button id="add-contact-btn">Add Contact</button>
                    <button id="import-vcf-btn">Import VCF</button>
                    <button id="export-vcf-btn">Export VCF</button>
                </div>
                <div id="contacts-list"></div>
            </div>
        </div>

        <div id="settings-modal" class="modal hidden">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Settings</h2>
                    <button class="close-btn" id="close-settings">&times;</button>
                </div>
                <div class="settings-tabs">
                    <button class="settings-tab active" data-tab="account">Account</button>
                    <button class="settings-tab" data-tab="reading">Reading</button>
                    <button class="settings-tab" data-tab="compose">Compose</button>
                    <button class="settings-tab" data-tab="notifications">Notifications</button>
                    <button class="settings-tab" data-tab="display">Display</button>
                    <button class="settings-tab" data-tab="signatures">Signatures</button>
                </div>
                <div id="settings-content"></div>
            </div>
        </div>

        <div id="filters-modal" class="modal hidden">
            <div class="modal-content wide">
                <div class="modal-header">
                    <h2>Email Filters</h2>
                    <button class="close-btn" id="close-filters">&times;</button>
                </div>
                <div class="filters-toolbar">
                    <button id="add-filter-btn">Add Filter</button>
                    <button id="run-filters-btn">Run on Inbox</button>
                </div>
                <div id="filters-list"></div>
            </div>
        </div>

        <div id="raw-modal" class="modal hidden">
            <div class="modal-content wide">
                <div class="modal-header">
                    <h2>Raw Message</h2>
                    <button class="close-btn" id="close-raw">&times;</button>
                </div>
                <pre id="raw-content"></pre>
            </div>
        </div>

        <div id="advanced-search-modal" class="modal hidden">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Advanced Search</h2>
                    <button class="close-btn" id="close-search">&times;</button>
                </div>
                <form id="advanced-search-form">
                    <div class="search-field">
                        <label>Subject contains:</label>
                        <input type="text" id="search-subject">
                    </div>
                    <div class="search-field">
                        <label>From contains:</label>
                        <input type="text" id="search-from">
                    </div>
                    <div class="search-field">
                        <label>To contains:</label>
                        <input type="text" id="search-to">
                    </div>
                    <div class="search-field">
                        <label>Body contains:</label>
                        <input type="text" id="search-body">
                    </div>
                    <div class="search-field">
                        <label>Has Attachment:</label>
                        <select id="search-attachment">
                            <option value="">Any</option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div class="search-field">
                        <label>Date From:</label>
                        <input type="date" id="search-date-from">
                    </div>
                    <div class="search-field">
                        <label>Date To:</label>
                        <input type="date" id="search-date-to">
                    </div>
                    <div class="search-field">
                        <label>
                            <input type="checkbox" id="search-starred"> Starred only
                        </label>
                    </div>
                    <div class="search-field">
                        <label>
                            <input type="checkbox" id="search-unread"> Unread only
                        </label>
                    </div>
                    <div class="search-field">
                        <label>Folder:</label>
                        <select id="search-folder">
                            <option value="all">All Folders</option>
                            <option value="current">Current Folder</option>
                        </select>
                    </div>
                    <button type="submit">Search</button>
                </form>
            </div>
        </div>

        <div id="keyboard-help-modal" class="modal hidden">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Keyboard Shortcuts</h2>
                    <button class="close-btn" id="close-help">&times;</button>
                </div>
                <div class="shortcuts-list">
                    <div class="shortcut"><kbd>C</kbd> Compose new</div>
                    <div class="shortcut"><kbd>R</kbd> Reply</div>
                    <div class="shortcut"><kbd>F</kbd> Forward</div>
                    <div class="shortcut"><kbd>D</kbd> Delete selected</div>
                    <div class="shortcut"><kbd>U</kbd> Mark unread</div>
                    <div class="shortcut"><kbd>S</kbd> Star/unstar</div>
                    <div class="shortcut"><kbd>J</kbd> Next email</div>
                    <div class="shortcut"><kbd>K</kbd> Previous email</div>
                    <div class="shortcut"><kbd>G I</kbd> Go to Inbox</div>
                    <div class="shortcut"><kbd>G S</kbd> Go to Sent</div>
                    <div class="shortcut"><kbd>G D</kbd> Go to Drafts</div>
                    <div class="shortcut"><kbd>Esc</kbd> Close modal</div>
                    <div class="shortcut"><kbd>Ctrl+Enter</kbd> Send email</div>
                    <div class="shortcut"><kbd>?</kbd> Show shortcuts</div>
                </div>
            </div>
        </div>

        <footer id="status-bar">
            <div class="status-left">
                <span id="connection-status" class="status-connected"></span>
                <span id="status-text">Ready</span>
            </div>
            <div class="status-right">
                <span id="folder-info"></span>
                <span id="selected-info"></span>
            </div>
            <div id="global-progress-container" class="hidden">
                <div id="global-progress-label">Loading...</div>
                <div id="global-progress-track">
                    <div id="global-progress-fill"></div>
                </div>
                <div id="global-progress-percent">0%</div>
            </div>
        </footer>
    </div>

    <div id="toast-container"></div>

    <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
    <script src="app.js"></script>
</body>
</html>