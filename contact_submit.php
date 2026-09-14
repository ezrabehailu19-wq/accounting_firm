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

    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $service  = trim($_POST['service'] ?? '');
    $message  = trim($_POST['message'] ?? '');

    $errors = [];

    if (mb_strlen($fullname) < 2 || mb_strlen($fullname) > 100) {
        $errors['fullname'] = 'Please enter your full name (2-100 characters).';
    }

    $email = filter_var($email, FILTER_VALIDATE_EMAIL);
    if ($email === false || mb_strlen($email) > 150) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    // Phone is optional, but if provided it must look like a phone number
    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,30}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }

    if (mb_strlen($service) > 100) {
        $errors['service'] = 'Service value is too long.';
    }

    if (mb_strlen($message) < 10 || mb_strlen($message) > 2000) {
        $errors['message'] = 'Message must be between 10 and 2000 characters.';
    }

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error'   => 'Please fix the highlighted fields and try again.',
            'errors'  => $errors,
        ]);
        exit();
    }

    $controler->submitContact($fullname, $email, $phone, $service, $message);
    // Return JSON so your existing JS can handle it
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>