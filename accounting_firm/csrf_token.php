<?php
/**
 * Hands the front-end a CSRF token for the AJAX contact form.
 *
 * Safe to expose: the token is bound to this visitor's session, and a
 * page on another origin cannot read the response (the browser's
 * same-origin policy stops it) nor send the session cookie needed to
 * get a matching one.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode(['token' => csrf_token()]);
