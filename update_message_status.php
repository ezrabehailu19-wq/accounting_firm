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

// Rebuild the redirect from whitelisted, validated pieces only —
// never trust a raw redirect URL from POST data (open redirect risk).
$statusFilter = $_POST['status_filter'] ?? 'all';
if (!in_array($statusFilter, ['all', 'new', 'read', 'replied'], true)) {
    $statusFilter = 'all';
}
$page = max(1, (int) ($_POST['page'] ?? 1));

if (isset($_POST['id'], $_POST['status'])) {
    $controler->updateMessageStatus((int) $_POST['id'], $_POST['status']);
}

header('Location: admin_messages.php?status=' . urlencode($statusFilter) . '&page=' . $page);
exit();
?>