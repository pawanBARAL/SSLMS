<?php

// Call after session_start(), before processing any submitted form.
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_token_is_valid($token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && $_SESSION['csrf_token'] !== ''
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_require_valid_request(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Your form has expired or could not be verified. Reload the page and try again.');
    }
}
