<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
include 'includes/includes.inc.php';
$controler = new Controler();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $controler->deleteMessage($id);
}
header('Location: admin.php');
exit();
?>