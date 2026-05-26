<?php
include 'includes/includes.inc.php';
$controler = new Controler();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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