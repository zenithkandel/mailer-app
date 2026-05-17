<?php
require_once 'config.php';

if (empty($_SESSION['authenticated'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZenithMail</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div id="progress-bar">
        <div id="progress-fill"></div>
    </div>

    <div class="app-container">
        <header class="topbar">
            <div class="topbar-left">
                <h1 class="logo">ZenithMail</h1>
            </div>
            <div class="topbar-center">
                <div class="search-box">
                    <input type="text" id="search-input" placeholder="Search emails...">
                    <select id="search-scope">
                        <option value="current">Current folder</option>
                        <option value="all">All folders</option>
                    </select>
                    <select id="search-field">
                        <option value="subject">Subject</option>
                        <option value="from">From</option>
                        <option value="body">Body</option>
                    </select>
                    <button id="search-btn" class="btn btn-small">Search</button>
                </div>
            </div>
            <div class="topbar-right">
                <button id="compose-btn" class="btn btn-primary">Compose</button>
                <button id="logout-btn" class="btn btn-secondary">Logout</button>
            </div>
        </header>

        <div class="main-layout">
            <aside class="sidebar">
                <nav class="folder-list" id="folder-list">
                </nav>
                <div class="sidebar-footer">
                    <button id="empty-trash-btn" class="btn btn-small btn-danger" style="display: none;">Empty Trash</button>
                </div>
            </aside>

            <div class="message-list-container">
                <div class="message-list-header">
                    <div class="bulk-actions" id="bulk-actions" style="display: none;">
                        <span id="selected-count">0 selected</span>
                        <button class="btn btn-small" data-action="mark-read">Mark Read</button>
                        <button class="btn btn-small" data-action="mark-unread">Mark Unread</button>
                        <button class="btn btn-small" data-action="delete">Delete</button>
                        <select id="move-to-folder" class="select-small">
                            <option value="">Move to...</option>
                        </select>
                    </div>
                    <div class="list-info" id="list-info">
                        <span id="folder-name">Inbox</span>
                        <span id="message-count"></span>
                    </div>
                </div>
                <div class="message-list" id="message-list">
                    <div class="loading">Loading messages...</div>
                </div>
                <div class="pagination" id="pagination">
                </div>
            </div>

            <div class="message-pane" id="message-pane">
                <div class="pane-empty">
                    <p>Select a message to read</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="compose-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="compose-title">New Message</h2>
                <button class="modal-close" id="close-compose">&times;</button>
            </div>
            <form id="compose-form">
                <div class="form-row">
                    <label>To:</label>
                    <div class="chips-input" id="to-input">
                        <input type="text" id="to-field" placeholder="Enter email address">
                    </div>
                </div>
                <div class="form-row">
                    <label></label>
                    <a href="#" id="add-cc">Add Cc</a>
                    <a href="#" id="add-bcc">Add Bcc</a>
                </div>
                <div class="form-row cc-row" id="cc-row" style="display: none;">
                    <label>Cc:</label>
                    <div class="chips-input" id="cc-input">
                        <input type="text" id="cc-field" placeholder="Enter email address">
                    </div>
                </div>
                <div class="form-row bcc-row" id="bcc-row" style="display: none;">
                    <label>Bcc:</label>
                    <div class="chips-input" id="bcc-input">
                        <input type="text" id="bcc-field" placeholder="Enter email address">
                    </div>
                </div>
                <div class="form-row">
                    <label>Subject:</label>
                    <input type="text" id="subject-field" placeholder="Subject">
                </div>
                <div class="form-row">
                    <label>Signature:</label>
                    <select id="signature-select">
                        <option value="">No signature</option>
                    </select>
                </div>
                <div class="form-row body-row">
                    <textarea id="body-field" placeholder="Write your message..."></textarea>
                </div>
                <div class="attachments-preview" id="attachments-preview"></div>
                <div class="attachment-upload">
                    <input type="file" id="file-input" multiple>
                    <button type="button" class="btn btn-small" id="attach-btn">Attach Files</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="discard-btn">Discard</button>
                    <button type="button" class="btn btn-secondary" id="save-draft-btn">Save Draft</button>
                    <button type="submit" class="btn btn-primary" id="send-btn">Send</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="settings-modal">
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <h2>Settings</h2>
                <button class="modal-close" id="close-settings">&times;</button>
            </div>
            <div class="settings-tabs">
                <button class="tab-btn active" data-tab="signatures">Signatures</button>
            </div>
            <div class="settings-content">
                <div class="tab-content" id="signatures-tab">
                    <div class="signatures-list" id="signatures-list"></div>
                    <div class="signature-form" id="signature-form" style="display: none;">
                        <h3>Create Signature</h3>
                        <div class="form-group">
                            <label>Name:</label>
                            <input type="text" id="sig-name" placeholder="Signature name">
                        </div>
                        <div class="form-group">
                            <label>Body:</label>
                            <textarea id="sig-body" placeholder="Signature text"></textarea>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="sig-default"> Set as default
                            </label>
                        </div>
                        <div class="form-actions">
                            <button class="btn btn-secondary" id="cancel-sig">Cancel</button>
                            <button class="btn btn-primary" id="save-sig">Save</button>
                        </div>
                    </div>
                    <button class="btn btn-primary" id="new-sig-btn" style="display: none;">New Signature</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toast-container"></div>

    <script src="assets/app.js"></script>
</body>
</html>