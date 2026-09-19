<?php
/**
 * Sign out.
 *
 * Three steps, all of them necessary: empty the data, kill the cookie in
 * the browser, then destroy the session server-side. The original version
 * called session_destroy() alone, which left the session cookie sitting
 * in the browser and never sent the user anywhere (no exit after the
 * redirect header).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

session_destroy();

header('Location: index.html');
exit;
