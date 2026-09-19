<?php
/**
 * Legacy endpoint, kept so old bookmarks and any cached admin page
 * still work. The real implementation now lives in admin_messages.php,
 * which handles the CSRF check, the audit entry and the redirect.
 *
 * Still POST-only: the original version accepted GET, which meant a
 * message could be destroyed by anything that merely *loaded* a URL —
 * a prefetching browser, a link checker, an image tag in an email.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    flash('error', 'Deleting a request needs to be confirmed from the inbox.');
    redirect('admin_messages.php');
}

csrf_guard('admin_messages.php');

$controler = new Controler();
$result    = $controler->removeRequest(post_int('id'), $admin);

flash($result['ok'] ? 'success' : 'error',
    $result['ok'] ? 'Request deleted.' : $result['error']);

redirect('admin_messages.php');
