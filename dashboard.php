<?php
require_once __DIR__ . '/api/config.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$config = loadConfig();
$appName = $config['app_name'] ?? 'Zenith Mail';
$accountEmail = $config['imap']['user'] ?? '';
$csrfToken = csrfGenerate();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= sanitize($appName) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-primary: #e8dcc8;
            --bg-secondary: #f5f0e6;
            --bg-tertiary: #dccfb8;
            --border-color: #c9b896;
            --border-dark: #8b7355;
            --text-primary: #4a3f35;
            --text-secondary: #7a6b5a;
            --text-muted: #9a8a7a;
            --accent: #e87b35;
            --accent-hover: #d66a2a;
            --accent-light: rgba(232, 123, 53, 0.1);
            --danger: #c44;
            --success: #4a4;
            --shadow: rgba(0,0,0,0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .app-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--bg-secondary);
            border-right: 2px solid var(--border-color);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 2px solid var(--border-color);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-icon svg {
            width: 24px;
            height: 24px;
            fill: white;
        }

        .brand-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .compose-btn {
            margin: 16px 20px;
            padding: 14px 20px;
            background: var(--accent);
            color: white;
            border: 2px solid var(--border-dark);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .compose-btn:hover {
            background: var(--accent-hover);
        }

        .compose-btn svg {
            width: 18px;
            height: 18px;
        }

        .nav-menu {
            list-style: none;
            padding: 10px;
        }

        .nav-item {
            margin-bottom: 4px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border: 2px solid transparent;
            cursor: pointer;
        }

        .nav-link:hover, .nav-link.active {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        .nav-link.active {
            border-left: 4px solid var(--accent);
        }

        .nav-link svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .nav-badge {
            margin-left: auto;
            background: var(--accent);
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            min-width: 20px;
            text-align: center;
        }

        .nav-label {
            flex: 1;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 16px;
            border-top: 2px solid var(--border-color);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        .user-details {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-email {
            font-size: 12px;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .logout-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            color: var(--text-muted);
        }

        .logout-btn:hover {
            color: var(--danger);
        }

        .logout-btn svg {
            width: 20px;
            height: 20px;
        }

        /* Main content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .toolbar {
            padding: 12px 20px;
            background: var(--bg-secondary);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .toolbar-btn {
            background: none;
            border: 2px solid var(--border-color);
            padding: 8px 12px;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
        }

        .toolbar-btn:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .toolbar-btn svg {
            width: 16px;
            height: 16px;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 10px 16px 10px 40px;
            border: 2px solid var(--border-color);
            background: white;
            font-size: 14px;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--accent);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: var(--text-muted);
        }

        .content-header {
            padding: 16px 20px;
            background: var(--bg-secondary);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .content-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .content-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .refresh-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            color: var(--text-secondary);
        }

        .refresh-btn:hover {
            color: var(--accent);
        }

        .refresh-btn svg {
            width: 20px;
            height: 20px;
        }

        /* Email list */
        .email-list-container {
            flex: 1;
            overflow-y: auto;
        }

        .email-list {
            list-style: none;
        }

        .email-item {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.15s;
        }

        .email-item:hover {
            background: var(--bg-secondary);
        }

        .email-item.unread {
            background: #f0ebe0;
            font-weight: 600;
        }

        .email-item.selected {
            background: var(--accent-light);
        }

        .email-checkbox {
            width: 18px;
            height: 18px;
            margin-right: 12px;
            accent-color: var(--accent);
        }

        .email-star {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            color: var(--text-muted);
            cursor: pointer;
        }

        .email-star.starred {
            color: #e8a030;
        }

        .email-star:hover {
            color: #e8a030;
        }

        .email-sender {
            width: 180px;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex-shrink: 0;
        }

        .email-subject {
            flex: 1;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-secondary);
        }

        .email-item.unread .email-subject {
            color: var(--text-primary);
        }

        .email-preview {
            flex: 1;
            font-size: 13px;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 300px;
        }

        .email-date {
            font-size: 12px;
            color: var(--text-muted);
            margin-left: 16px;
            flex-shrink: 0;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: var(--text-muted);
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--text-secondary);
        }

        .loading {
            padding: 40px;
            text-align: center;
            color: var(--text-muted);
        }

        /* Email detail */
        .email-detail {
            display: none;
            flex: 1;
            flex-direction: column;
            background: white;
            border-left: 2px solid var(--border-color);
        }

        .email-detail.show {
            display: flex;
        }

        .detail-header {
            padding: 16px 20px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .detail-back {
            background: none;
            border: 2px solid var(--border-color);
            padding: 8px 12px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .detail-back:hover {
            background: var(--bg-tertiary);
        }

        .detail-back svg {
            width: 16px;
            height: 16px;
        }

        .detail-actions {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        .detail-action {
            background: none;
            border: 2px solid var(--border-color);
            padding: 8px;
            cursor: pointer;
            color: var(--text-secondary);
        }

        .detail-action:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .detail-action svg {
            width: 18px;
            height: 18px;
        }

        .detail-subject {
            padding: 16px 20px;
            font-size: 20px;
            font-weight: 700;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-meta {
            padding: 16px 20px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .meta-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .meta-label {
            color: var(--text-muted);
            min-width: 60px;
        }

        .meta-value {
            color: var(--text-primary);
        }

        .meta-value a {
            color: var(--accent);
            text-decoration: none;
        }

        .meta-value a:hover {
            text-decoration: underline;
        }

        .detail-body {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            font-size: 14px;
            line-height: 1.6;
        }

        .detail-body img {
            max-width: 100%;
            height: auto;
        }

        .detail-body a {
            color: var(--accent);
        }

        .detail-attachments {
            padding: 16px 20px;
            border-top: 2px solid var(--border-color);
            background: var(--bg-secondary);
        }

        .attachments-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }

        .attachment-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: white;
            border: 2px solid var(--border-color);
            font-size: 13px;
            cursor: pointer;
        }

        .attachment-item:hover {
            background: var(--bg-tertiary);
        }

        .attachment-item svg {
            width: 16px;
            height: 16px;
            color: var(--accent);
        }

        /* Compose modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background: var(--bg-secondary);
            border: 3px solid var(--border-dark);
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 8px 8px 0 rgba(0,0,0,0.2);
        }

        .modal-header {
            padding: 16px 20px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 700;
        }

        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            color: var(--text-secondary);
        }

        .modal-close:hover {
            color: var(--text-primary);
        }

        .modal-close svg {
            width: 24px;
            height: 24px;
        }

        .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .form-row {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
        }

        .form-row-label {
            width: 80px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            flex-shrink: 0;
        }

        .form-row-input {
            flex: 1;
            border: none;
            background: transparent;
            font-size: 14px;
            padding: 8px;
        }

        .form-row-input:focus {
            outline: none;
        }

        .compose-body {
            flex: 1;
            min-height: 200px;
            border: 2px solid var(--border-color);
            background: white;
            padding: 12px;
            font-size: 14px;
            resize: none;
            font-family: inherit;
        }

        .compose-body:focus {
            outline: none;
            border-color: var(--accent);
        }

        .modal-footer {
            padding: 16px 20px;
            border-top: 2px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: 2px solid var(--border-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn svg {
            width: 16px;
            height: 16px;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: var(--border-color);
        }

        /* Toast notifications */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .toast {
            background: var(--text-primary);
            color: white;
            padding: 14px 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 4px 4px 0 rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
        }

        .toast.success {
            background: var(--success);
        }

        .toast.error {
            background: var(--danger);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Bulk actions bar */
        .bulk-bar {
            display: none;
            padding: 12px 20px;
            background: var(--accent);
            color: white;
            align-items: center;
            gap: 16px;
        }

        .bulk-bar.show {
            display: flex;
        }

        .bulk-count {
            font-weight: 600;
        }

        .bulk-actions {
            display: flex;
            gap: 8px;
        }

        .bulk-btn {
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.4);
            color: white;
            padding: 8px 12px;
            font-size: 13px;
            cursor: pointer;
        }

        .bulk-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -260px;
                top: 0;
                bottom: 0;
                z-index: 100;
                transition: left 0.3s;
            }

            .sidebar.show {
                left: 0;
            }

            .email-sender {
                width: 100px;
            }

            .email-preview {
                display: none;
            }

            .modal {
                max-width: 100%;
                margin: 10px;
            }
        }

        /* Select all checkbox */
        .select-all {
            margin-right: 12px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="brand">
                    <div class="brand-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </div>
                    <span class="brand-name"><?= sanitize($appName) ?></span>
                </div>
            </div>

            <button class="compose-btn" id="composeBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Compose
            </button>

            <ul class="nav-menu">
                <li class="nav-item">
                    <a class="nav-link active" data-view="inbox">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/>
                            <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>
                        </svg>
                        <span class="nav-label">Inbox</span>
                        <span class="nav-badge" id="inboxBadge" style="display: none;"></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-view="sent">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                        <span class="nav-label">Sent</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-view="drafts">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                        <span class="nav-label">Drafts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-view="starred">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                        <span class="nav-label">Starred</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-view="trash">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        <span class="nav-label">Trash</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['user'] ?? 'A', 0, 1)) ?></div>
                    <div class="user-details">
                        <div class="user-name"><?= sanitize($_SESSION['user'] ?? 'Admin') ?></div>
                        <div class="user-email"><?= sanitize($accountEmail) ?></div>
                    </div>
                    <button class="logout-btn" id="logoutBtn" title="Logout">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <div class="toolbar">
                <button class="toolbar-btn" id="refreshBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                    </svg>
                    Refresh
                </button>

                <div class="search-box">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" class="search-input" id="searchInput" placeholder="Search emails...">
                </div>
            </div>

            <div class="bulk-bar" id="bulkBar">
                <span class="bulk-count"><span id="selectedCount">0</span> selected</span>
                <div class="bulk-actions">
                    <button class="bulk-btn" id="markReadBtn">Mark read</button>
                    <button class="bulk-btn" id="markUnreadBtn">Mark unread</button>
                    <button class="bulk-btn" id="deleteSelectedBtn">Delete</button>
                </div>
            </div>

            <div class="content-header">
                <h2 class="content-title" id="viewTitle">Inbox</h2>
                <div class="content-actions">
                    <button class="refresh-btn" id="refreshListBtn" title="Refresh">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="email-list-container">
                <ul class="email-list" id="emailList"></ul>
            </div>

            <div class="email-detail" id="emailDetail">
                <div class="detail-header">
                    <button class="detail-back" id="backBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                        </svg>
                        Back
                    </button>
                    <div class="detail-actions">
                        <button class="detail-action" id="replyBtn" title="Reply">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/>
                            </svg>
                        </button>
                        <button class="detail-action" id="forwardBtn" title="Forward">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 14 20 9 15 4"/><path d="M4 20v-7a4 4 0 0 1 4-4h12"/>
                            </svg>
                        </button>
                        <button class="detail-action" id="deleteEmailBtn" title="Delete">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="detail-subject" id="detailSubject"></div>
                <div class="detail-meta" id="detailMeta"></div>
                <div class="detail-body" id="detailBody"></div>
                <div class="detail-attachments" id="detailAttachments" style="display: none;">
                    <div class="attachments-title">Attachments</div>
                    <div class="attachment-list" id="attachmentList"></div>
                </div>
            </div>
        </main>
    </div>

    <div class="modal-overlay" id="composeModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="composeTitle">New Message</h3>
                <button class="modal-close" id="closeCompose">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" id="composeCsrf" value="<?= $csrfToken ?>">
                <input type="hidden" id="replyToId">
                <input type="hidden" id="replyToEmail">
                <input type="hidden" id="replyToFolder">
                
                <div class="form-row">
                    <span class="form-row-label">To</span>
                    <input type="email" class="form-row-input" id="composeTo" placeholder="recipient@example.com" required>
                </div>
                <div class="form-row">
                    <span class="form-row-label">Cc</span>
                    <input type="email" class="form-row-input" id="composeCc" placeholder="">
                </div>
                <div class="form-row">
                    <span class="form-row-label">Bcc</span>
                    <input type="email" class="form-row-input" id="composeBcc" placeholder="">
                </div>
                <div class="form-row">
                    <span class="form-row-label">Subject</span>
                    <input type="text" class="form-row-input" id="composeSubject" placeholder="Subject" required>
                </div>
                <textarea class="compose-body" id="composeBody" placeholder="Write your message..."></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="saveDraftBtn">Save Draft</button>
                <button class="btn btn-primary" id="sendBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                    Send
                </button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        const CSRF_TOKEN = '<?= $csrfToken ?>';
        
        const state = {
            currentView: 'inbox',
            emails: [],
            selectedIds: new Set(),
            currentPage: 1,
            hasMore: false,
            search: '',
            loading: false,
            currentEmail: null
        };

        function csrfHeaders() {
            return { 'X-CSRF-TOKEN': CSRF_TOKEN };
        }

        function escHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 86400000 && date.getDate() === now.getDate()) {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } else if (diff < 604800000) {
                return date.toLocaleDateString([], { weekday: 'short' });
            } else {
                return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
            }
        }

        function getInitials(name) {
            if (!name) return '?';
            const parts = name.split(' ');
            return parts.map(p => p[0]).slice(0, 2).join('').toUpperCase();
        }

        async function loadEmails(reset = true) {
            if (state.loading) return;
            state.loading = true;

            if (reset) {
                state.currentPage = 1;
                state.emails = [];
            }

            try {
                const url = `api/mail.php?action=${state.currentView}&page=${state.currentPage}&search=${encodeURIComponent(state.search)}`;
                const res = await fetch(url, { headers: csrfHeaders() });
                const data = await res.json();

                if (!res.ok) throw new Error(data.error || 'Failed to load emails');

                if (reset) {
                    state.emails = data.emails || [];
                } else {
                    state.emails = [...state.emails, ...(data.emails || [])];
                }
                state.hasMore = data.has_more;
                state.currentPage++;

                renderEmailList();
                updateUnreadBadge();
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                state.loading = false;
            }
        }

        function renderEmailList() {
            const list = document.getElementById('emailList');
            
            if (state.emails.length === 0) {
                list.innerHTML = `
                    <li class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <h3>No emails found</h3>
                        <p>${state.search ? 'Try a different search term' : 'Your inbox is empty'}</p>
                    </li>
                `;
                return;
            }

            list.innerHTML = state.emails.map(email => `
                <li class="email-item ${email.unread ? 'unread' : ''} ${state.selectedIds.has(email.id) ? 'selected' : ''}" data-id="${email.id}">
                    <input type="checkbox" class="email-checkbox" ${state.selectedIds.has(email.id) ? 'checked' : ''}>
                    <span class="email-star ${email.flagged ? 'starred' : ''}" data-id="${email.id}">
                        <svg viewBox="0 0 24 24" fill="${email.flagged ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                    </span>
                    <span class="email-sender">${escHtml(email.from_name || email.from || 'Unknown')}</span>
                    <span class="email-subject">${escHtml(email.subject || '(No subject)')}</span>
                    <span class="email-preview">${escHtml(email.preview || '')}</span>
                    <span class="email-date">${formatDate(email.date)}</span>
                </li>
            `).join('');

            list.querySelectorAll('.email-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    if (e.target.type !== 'checkbox') {
                        viewEmail(parseInt(item.dataset.id));
                    }
                });
            });

            list.querySelectorAll('.email-checkbox').forEach(cb => {
                cb.addEventListener('change', (e) => {
                    const id = parseInt(e.target.closest('.email-item').dataset.id);
                    if (e.target.checked) {
                        state.selectedIds.add(id);
                    } else {
                        state.selectedIds.delete(id);
                    }
                    updateBulkBar();
                });
            });

            list.querySelectorAll('.email-star').forEach(star => {
                star.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const id = parseInt(star.dataset.id);
                    await toggleFlag(id);
                });
            });
        }

        async function toggleFlag(id) {
            const email = state.emails.find(e => e.id === id);
            if (!email) return;

            try {
                const res = await fetch('api/mail.php?action=mark', {
                    method: 'POST',
                    headers: { ...csrfHeaders(), 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: [id], flag: email.flagged ? 'unflagged' : 'flagged', folder: state.currentView })
                });
                const data = await res.json();
                if (data.success) {
                    email.flagged = !email.flagged;
                    renderEmailList();
                }
            } catch (err) {
                showToast('Failed to update flag', 'error');
            }
        }

        function updateBulkBar() {
            const bar = document.getElementById('bulkBar');
            const count = document.getElementById('selectedCount');
            count.textContent = state.selectedIds.size;
            bar.classList.toggle('show', state.selectedIds.size > 0);
        }

        async function updateUnreadBadge() {
            try {
                const res = await fetch('api/mail.php?action=unread_count', { headers: csrfHeaders() });
                const data = await res.json();
                const badge = document.getElementById('inboxBadge');
                if (data.unread > 0) {
                    badge.textContent = data.unread;
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                }
            } catch (err) {}
        }

        async function viewEmail(id) {
            try {
                const res = await fetch(`api/mail.php?action=view&id=${id}&folder=${state.currentView}`, { headers: csrfHeaders() });
                const email = await res.json();

                if (!res.ok) throw new Error(email.error || 'Failed to load email');

                state.currentEmail = email;
                document.getElementById('emailDetail').classList.add('show');
                document.querySelector('.email-list-container').style.display = 'none';

                document.getElementById('detailSubject').textContent = email.subject || '(No subject)';
                
                document.getElementById('detailMeta').innerHTML = `
                    <div class="meta-row">
                        <span class="meta-label">From:</span>
                        <span class="meta-value">${escHtml(email.from_name || '')} &lt;<a href="mailto:${escHtml(email.from)}">${escHtml(email.from)}</a>&gt;</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">To:</span>
                        <span class="meta-value">${escHtml(email.to_name || '')} &lt;${escHtml(email.to)}&gt;</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Date:</span>
                        <span class="meta-value">${new Date(email.date).toLocaleString()}</span>
                    </div>
                    ${email.reply_to ? `
                    <div class="meta-row">
                        <span class="meta-label">Reply-To:</span>
                        <span class="meta-value"><a href="mailto:${escHtml(email.reply_to)}">${escHtml(email.reply_to)}</a></span>
                    </div>
                    ` : ''}
                `;

                document.getElementById('detailBody').innerHTML = email.body || '<p>No content</p>';

                if (email.attachments && email.attachments.length > 0) {
                    document.getElementById('detailAttachments').style.display = 'block';
                    document.getElementById('attachmentList').innerHTML = email.attachments.map(att => `
                        <a class="attachment-item" href="api/mail.php?action=attachment&id=${email.id}&part=${escHtml(att.part)}&name=${escHtml(att.name)}&folder=${state.currentView}" target="_blank">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                            </svg>
                            ${escHtml(att.name)}
                        </a>
                    `).join('');
                } else {
                    document.getElementById('detailAttachments').style.display = 'none';
                }

                if (email.unread && email.id) {
                    await fetch(`api/mail.php?action=mark&folder=${state.currentView}`, {
                        method: 'POST',
                        headers: { ...csrfHeaders(), 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: [email.id], flag: 'read' })
                    });
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        function openCompose(data = {}) {
            document.getElementById('composeModal').classList.add('show');
            document.getElementById('composeTo').value = data.to || '';
            document.getElementById('composeCc').value = data.cc || '';
            document.getElementById('composeBcc').value = '';
            document.getElementById('composeSubject').value = data.subject || '';
            document.getElementById('composeBody').value = data.body || '';
            document.getElementById('replyToId').value = data.reply_id || '';
            document.getElementById('replyToEmail').value = data.reply_to || '';
            document.getElementById('replyToFolder').value = data.folder || '';
            
            document.getElementById('composeTitle').textContent = data.isForward ? 'Forward' : (data.reply_id ? 'Reply' : 'New Message');
            
            if (data.isForward) {
                document.getElementById('composeSubject').value = 'Fwd: ' + (data.subject || '');
            } else if (data.reply_id) {
                document.getElementById('composeSubject').value = 'Re: ' + (data.subject || '');
            }
            
            document.getElementById('composeTo').focus();
        }

        function closeCompose() {
            document.getElementById('composeModal').classList.remove('show');
        }

        async function sendEmail() {
            const to = document.getElementById('composeTo').value.trim();
            const cc = document.getElementById('composeCc').value.trim();
            const bcc = document.getElementById('composeBcc').value.trim();
            const subject = document.getElementById('composeSubject').value.trim();
            const body = document.getElementById('composeBody').value;
            const replyTo = document.getElementById('replyToEmail').value;
            const replyId = document.getElementById('replyToId').value;

            if (!to) {
                showToast('Please enter a recipient', 'error');
                return;
            }
            if (!subject) {
                showToast('Please enter a subject', 'error');
                return;
            }

            const btn = document.getElementById('sendBtn');
            btn.disabled = true;
            btn.textContent = 'Sending...';

            try {
                const res = await fetch('api/mail.php?action=send', {
                    method: 'POST',
                    headers: { ...csrfHeaders(), 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: CSRF_TOKEN,
                        to, cc, bcc, subject, body,
                        reply_to: replyTo,
                        reply_id: replyId,
                        folder: state.currentView
                    })
                });
                const data = await res.json();

                if (data.success) {
                    showToast('Email sent successfully!', 'success');
                    closeCompose();
                    if (state.currentView === 'sent' || state.currentView === 'inbox') {
                        loadEmails(true);
                    }
                } else {
                    throw new Error(data.error);
                }
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send`;
            }
        }

        async function saveDraft() {
            const to = document.getElementById('composeTo').value.trim();
            const cc = document.getElementById('composeCc').value.trim();
            const bcc = document.getElementById('composeBcc').value.trim();
            const subject = document.getElementById('composeSubject').value.trim();
            const body = document.getElementById('composeBody').value;

            if (!to && !subject && !body) {
                showToast('Nothing to save', 'info');
                return;
            }

            try {
                const res = await fetch('api/mail.php?action=draft', {
                    method: 'POST',
                    headers: { ...csrfHeaders(), 'Content-Type': 'application/json' },
                    body: JSON.stringify({ csrf_token: CSRF_TOKEN, to, cc, bcc, subject, body })
                });
                const data = await res.json();

                if (data.success) {
                    showToast('Draft saved', 'success');
                    closeCompose();
                } else {
                    throw new Error(data.error);
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function deleteSelected() {
            const ids = Array.from(state.selectedIds);
            if (ids.length === 0) return;

            try {
                const res = await fetch('api/mail.php?action=delete', {
                    method: 'POST',
                    headers: { ...csrfHeaders(), 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids, folder: state.currentView })
                });
                const data = await res.json();

                if (data.success) {
                    showToast('Deleted successfully', 'success');
                    state.selectedIds.clear();
                    updateBulkBar();
                    loadEmails(true);
                } else {
                    throw new Error(data.error);
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function markAsRead() {
            const ids = Array.from(state.selectedIds);
            if (ids.length === 0) return;

            try {
                await fetch('api/mail.php?action=mark', {
                    method: 'POST',
                    headers: { ...csrfHeaders(), 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids, flag: 'read', folder: state.currentView })
                });
                state.selectedIds.clear();
                updateBulkBar();
                loadEmails(true);
                showToast('Marked as read', 'success');
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function markAsUnread() {
            const ids = Array.from(state.selectedIds);
            if (ids.length === 0) return;

            try {
                await fetch('api/mail.php?action=mark', {
                    method: 'POST',
                    headers: { ...csrfHeaders(), 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids, flag: 'unread', folder: state.currentView })
                });
                state.selectedIds.clear();
                updateBulkBar();
                loadEmails(true);
                showToast('Marked as unread', 'success');
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        document.getElementById('composeBtn').addEventListener('click', () => openCompose());
        document.getElementById('closeCompose').addEventListener('click', closeCompose);
        document.getElementById('sendBtn').addEventListener('click', sendEmail);
        document.getElementById('saveDraftBtn').addEventListener('click', saveDraft);

        document.getElementById('replyBtn').addEventListener('click', () => {
            if (state.currentEmail) {
                openCompose({
                    reply_id: state.currentEmail.id,
                    reply_to: state.currentEmail.reply_to || state.currentEmail.from,
                    to: state.currentEmail.reply_to || state.currentEmail.from,
                    subject: state.currentEmail.subject,
                    folder: state.currentView
                });
            }
        });

        document.getElementById('forwardBtn').addEventListener('click', () => {
            if (state.currentEmail) {
                openCompose({
                    isForward: true,
                    subject: state.currentEmail.subject,
                    body: state.currentEmail.body
                });
            }
        });

        document.getElementById('deleteEmailBtn').addEventListener('click', async () => {
            if (state.currentEmail) {
                await deleteSelected();
                document.getElementById('backBtn').click();
            }
        });

        document.getElementById('backBtn').addEventListener('click', () => {
            document.getElementById('emailDetail').classList.remove('show');
            document.querySelector('.email-list-container').style.display = 'block';
            state.currentEmail = null;
            loadEmails(true);
        });

        document.getElementById('logoutBtn').addEventListener('click', async () => {
            await fetch('api/auth.php?action=logout', { method: 'POST' });
            window.location.href = 'index.php';
        });

        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                link.classList.add('active');
                state.currentView = link.dataset.view;
                document.getElementById('viewTitle').textContent = link.querySelector('.nav-label').textContent;
                state.selectedIds.clear();
                updateBulkBar();
                loadEmails(true);
                
                document.getElementById('emailDetail').classList.remove('show');
                document.querySelector('.email-list-container').style.display = 'block';
            });
        });

        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                state.search = e.target.value;
                loadEmails(true);
            }, 500);
        });

        document.getElementById('refreshBtn').addEventListener('click', () => loadEmails(true));
        document.getElementById('refreshListBtn').addEventListener('click', () => loadEmails(true));
        document.getElementById('deleteSelectedBtn').addEventListener('click', deleteSelected);
        document.getElementById('markReadBtn').addEventListener('click', markAsRead);
        document.getElementById('markUnreadBtn').addEventListener('click', markAsUnread);

        loadEmails(true);
    </script>
</body>
</html>