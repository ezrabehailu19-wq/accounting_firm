<?php
include 'includes/includes.inc.php';
$controler = new Controler();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid or expired security token. Please refresh the page and try again.',
        ]);
        exit();
    }

    $fullname = $_POST['fullname'];
    $email    = $_POST['email'];
    $phone    = $_POST['phone'] ?? '';
    $service  = $_POST['service'] ?? '';
    $message  = $_POST['message'];

    $controler->submitContact($fullname, $email, $phone, $service, $message);
    // Return JSON so your existing JS can handle it
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>