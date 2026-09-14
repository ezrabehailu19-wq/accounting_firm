<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
include 'includes/includes.inc.php';
$controler = new Controler();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_messages.php');
    exit();
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    header('Location: admin_messages.php?error=invalid_token');
    exit();
}

if (isset($_POST['id'])) {
    $id = $_POST['id'];
    $controler->deleteMessage($id);
}
header('Location: admin.php');
exit();
?>