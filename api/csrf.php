<?php

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function validateCsrf(): bool {
    if (!isset($_POST['csrf_token'])) {
        return false;
    }
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function csrfHeader(): string {
    $token = csrfToken();
    return "X-CSRF-TOKEN: {$token}";
}