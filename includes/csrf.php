<?php
/**
 * CSRF protection helpers.
 *
 * A CSRF token is a random, per-session secret. Every form that changes
 * state (login, register, contact) includes it as a hidden field. When the
 * form is submitted, we check the submitted token matches the one stored
 * server-side in the session. A malicious site tricking a user's browser
 * into submitting the form can't know this token, so the forged request
 * gets rejected.
 *
 * Usage in a PHP-rendered form:
 *   <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
 *
 * Usage before processing a POST:
 *   if (!csrf_verify($_POST['csrf_token'] ?? '')) {
 *       // reject the request
 *   }
 *
 * For forms served from static HTML (no PHP rendering, e.g. contact.html),
 * the token is fetched via csrf_token.php over AJAX instead — see script.js.
 */

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

function csrf_verify(?string $token): bool
{
    csrf_start();

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}