<?php
/**
 * Bootstrap. Every PHP page starts by requiring this file.
 *
 * Order matters: config first (it defines the constants everything else
 * reads), then the autoloader, then session and security headers, which
 * must both run before a single byte of output is sent.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/**
 * Autoloader.
 *
 * The old version used a relative path ("classes/"), which resolved
 * against the current working directory. That happened to work for pages
 * in the project root and broke for anything in a subfolder or run from
 * the CLI. __DIR__ is absolute, so it works from anywhere.
 */
spl_autoload_register(static function (string $class): void {
    // Only ever load from our own classes folder, and only plain class
    // names — no namespaces, no traversal.
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) {
        return;
    }

    $file = APP_ROOT . '/classes/' . $class . '.class.php';

    if (is_file($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/helpers.inc.php';

/**
 * Session cookie settings, applied before the session starts.
 *
 *  httponly  — JavaScript cannot read the cookie, so an XSS bug cannot
 *              simply exfiltrate the session.
 *  samesite  — the cookie is not attached to cross-site POSTs, which is
 *              a second line of defence behind the CSRF tokens.
 *  secure    — HTTPS only, but only switched on when the request
 *              actually is HTTPS, otherwise local development over
 *              plain http could never log in.
 */
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('AFSESSID');
    session_start();
}

/**
 * Security response headers.
 *
 * Skipped when headers are already sent (which only happens if a page
 * echoed something before requiring this file) so we never emit a
 * "headers already sent" warning on top of whatever went wrong.
 */
if (!headers_sent()) {
    // Stops a browser from guessing that your .txt upload is really HTML.
    header('X-Content-Type-Options: nosniff');
    // Blocks the site being framed by another origin (clickjacking).
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');

    // A conservative CSP. 'unsafe-inline' is still needed for style
    // because the printable invoice sets a few inline styles; scripts are
    // restricted to this origin, which is the half that matters for XSS.
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com data:; "
        . "img-src 'self' data:; "
        . "form-action 'self'; "
        . "base-uri 'self'; "
        . "frame-ancestors 'self'; "
        . "object-src 'none'"
    );
}

/**
 * Idle timeout. An unattended browser on a shared office machine should
 * not stay logged into the admin panel indefinitely.
 */
if (!empty($_SESSION['loggedin'])) {
    $idleLimit = SESSION_IDLE_MINUTES * 60;

    if (isset($_SESSION['last_seen']) && (time() - (int) $_SESSION['last_seen']) > $idleLimit) {
        $_SESSION = [];
        session_destroy();
        session_start();
        $_SESSION['flash'] = ['type' => 'info', 'text' => 'You were signed out after a period of inactivity.'];
    } else {
        $_SESSION['last_seen'] = time();
    }
}

/**
 * Last-resort error handling.
 *
 * Any uncaught exception shows the visitor a plain apology and writes
 * the real detail to the error log. In development the detail is shown
 * on screen instead, because hunting a stack trace through a log file
 * while building is miserable.
 */
set_exception_handler(static function (Throwable $e): void {
    error_log('[uncaught] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
    }

    if (APP_ENV === 'development' || APP_DEBUG) {
        echo '<pre style="padding:2rem;font:14px/1.6 monospace;background:#0C1B2A;color:#E8C87A">';
        echo htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8');
        echo '</pre>';

        return;
    }

    echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>'
       . '<div style="font:16px/1.6 system-ui;max-width:32rem;margin:4rem auto;padding:0 1rem">'
       . '<h1 style="font-size:1.5rem">Something went wrong</h1>'
       . '<p>The page could not be loaded. The problem has been logged. '
       . 'Try again, or <a href="contact.html">get in touch</a> if it keeps happening.</p></div>';
});
