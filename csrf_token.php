<?php
/**
 * Returns a CSRF token for use by JS-driven forms on static HTML pages
 * (e.g. contact.html) that can't render a PHP hidden field directly.
 */
include 'includes/includes.inc.php';

header('Content-Type: application/json');
echo json_encode(['token' => csrf_token()]);