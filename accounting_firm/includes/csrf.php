<?php
/**
 * Cross-site request forgery protection.
 *
 * The problem: your admin is logged in here, then visits a page
 * somewhere else that quietly posts a form to delete_message.php. The
 * browser attaches the session cookie automatically, so without a token
 * the server cannot tell that request apart from a real click.
 *
 * The fix: every state-changing form carries a random per-session value
 * that the other site has no way to read. No token, no action.
 *
 * In a form:
 *     <?= csrf_field() ?>
 *
 * Before acting on a POST:
 *     csrf_guard();          // exits with 403 on failure
 * or
 *     if (!csrf_verify($_POST['csrf_token'] ?? '')) { ... }
 */

declare(strict_types=1);

function csrf_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function csrf_token(): string
{
    csrf_start();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Ready-made hidden input, so no page can forget the htmlspecialchars. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify(?string $token): bool
{
    csrf_start();

    if (empty($_SESSION['csrf_token']) || $token === null || $token === '') {
        return false;
    }

    // hash_equals compares in constant time. A normal === returns as soon
    // as two characters differ, which leaks how much of a guessed token
    // was correct.
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify or stop. Use at the top of any POST handler.
 *
 * @param string|null $redirectTo Where to send a browser; null sends JSON
 */
function csrf_guard(?string $redirectTo = null): void
{
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        return;
    }

    http_response_code(403);

    if ($redirectTo !== null) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'text' => 'That form expired before it was submitted. Please try again.',
        ];
        header('Location: ' . $redirectTo);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'That form expired before it was submitted. Reload the page and try again.',
    ]);
    exit;
}
