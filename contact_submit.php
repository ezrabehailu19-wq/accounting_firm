<?php
/**
 * Contact form endpoint.
 *
 * Answers JSON so the page can show the result without a reload, and
 * returns the tracking reference the client can quote back to us.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'This endpoint only accepts POST.']);
    exit;
}

// Sends JSON rather than a redirect on failure, which is what the
// front-end expects here.
csrf_guard();

$controler = new Controler();

$result = $controler->submitRequest(
    [
        'fullname' => post_str('fullname'),
        'email'    => post_str('email'),
        'phone'    => post_str('phone'),
        'service'  => post_str('service'),
        'message'  => post_str('message'),
    ],
    is_logged_in() ? (int) $_SESSION['user_id'] : null
);

if (!$result['ok']) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error'   => $result['error'],
        'errors'  => $result['errors'] ?? [],
    ]);
    exit;
}

echo json_encode([
    'success'   => true,
    'reference' => $result['reference'],
    'message'   => 'Thank you. Your reference is ' . $result['reference']
                 . '. We reply within one working day.',
]);
