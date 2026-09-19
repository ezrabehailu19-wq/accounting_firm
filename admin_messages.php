<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
include 'includes/includes.inc.php';
$controler = new Controler();

$statusFilter = $_GET['status'] ?? 'all';
if (!in_array($statusFilter, ['all', 'new', 'read', 'replied'], true)) {
    $statusFilter = 'all';
}
$page = max(1, (int) ($_GET['page'] ?? 1));

$messages = $controler->getMessagesPage($statusFilter, $page);
$totalPages = $controler->getMessagesTotalPages($statusFilter);

$statusBadgeColors = [
    'new'     => ['bg' => '#dbeafe', 'text' => '#1e40af'],
    'read'    => ['bg' => '#fef3c7', 'text' => '#92400e'],
    'replied' => ['bg' => '#d1fae5', 'text' => '#065f46'],
];

$tabs = ['all' => 'All', 'new' => 'New', 'read' => 'Read', 'replied' => 'Replied'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Selamawit H/Mariam</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="display:flex; flex-direction:column; min-height:100vh;">
    <header class="header" id="header">
        <div class="header-container">
            <a href="index.html" class="logo">
                <div class="logo-box">SH</div>
                <div class="logo-text">
                    <h1>Selamawit H/Mariam</h1>
                    <p>Accounting & Financial Consulting</p>
                </div>
            </a>
            <nav class="navigation" id="navMenu">
                <ul>
                    <li><a href="admin.php" class="nav-link">Dashboard</a></li>
                    <li><a href="admin_messages.php" class="nav-link active">Messages</a></li>
                    <li><a href="admin_users.php" class="nav-link">Users</a></li>
                    <li><a href="index.html" class="nav-link">View Site</a></li>
                </ul>
                <a href="logout.php" class="btn btn-primary nav-cta">Logout</a>
            </nav>
        </div>
    </header>

    <main style="flex:1;">
        <section class="page-section">
            <div class="container">
                <div class="section-header">
                    <span class="section-badge">Admin Panel</span>
                    <h2 class="section-title">All Messages</h2>
                    <p class="section-subtitle">All contact form submissions from clients</p>
                </div>

                <div style="display:flex; gap:0.5rem; margin-bottom:1.5rem; flex-wrap:wrap;">
                    <?php foreach ($tabs as $key => $label): ?>
                        <a href="admin_messages.php?status=<?php echo $key; ?>"
                           style="padding:0.5rem 1rem; border-radius:var(--radius); text-decoration:none; font-size:0.9rem;
                                  background: <?php echo $statusFilter === $key ? 'var(--primary)' : '#e5e7eb'; ?>;
                                  color: <?php echo $statusFilter === $key ? 'white' : '#374151'; ?>;">
                            <?php echo $label; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; background:white; border-radius:var(--radius); box-shadow:var(--shadow-lg); overflow:hidden;">
                        <thead>
                            <tr style="background:var(--primary); color:white;">
                                <th style="padding:1rem; text-align:left;">#</th>
                                <th style="padding:1rem; text-align:left;">Name</th>
                                <th style="padding:1rem; text-align:left;">Email</th>
                                <th style="padding:1rem; text-align:left;">Phone</th>
                                <th style="padding:1rem; text-align:left;">Service</th>
                                <th style="padding:1rem; text-align:left;">Message</th>
                                <th style="padding:1rem; text-align:left;">Date</th>
                                <th style="padding:1rem; text-align:left;">Status</th>
                                <th style="padding:1rem; text-align:left;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($messages->num_rows === 0): ?>
                                <tr>
                                    <td colspan="9" style="padding:2rem; text-align:center; color:#6b7280;">
                                        No messages here.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php while ($row = $messages->fetch_assoc()): ?>
                            <tr style="border-bottom:1px solid var(--gray-200);">
                                <td style="padding:1rem;"><?php echo $row['id']; ?></td>
                                <td style="padding:1rem;"><?php echo htmlspecialchars($row['fullname']); ?></td>
                                <td style="padding:1rem;"><?php echo htmlspecialchars($row['email']); ?></td>
                                <td style="padding:1rem;"><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td style="padding:1rem;"><?php echo htmlspecialchars($row['service']); ?></td>
                                <td style="padding:1rem; max-width:250px;"><?php echo htmlspecialchars($row['message']); ?></td>
                                <td style="padding:1rem; white-space:nowrap;"><?php echo $row['submitted_at']; ?></td>
                                <td style="padding:1rem;">
                                    <?php $colors = $statusBadgeColors[$row['status']] ?? $statusBadgeColors['new']; ?>
                                    <span style="background:<?php echo $colors['bg']; ?>; color:<?php echo $colors['text']; ?>; padding:0.25rem 0.6rem; border-radius:999px; font-size:0.75rem; font-weight:600; white-space:nowrap;">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td style="padding:1rem;">
                                    <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                        <form action="update_message_status.php" method="post" style="display:flex; gap:0.4rem; margin:0;">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                            <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">
                                            <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
                                            <select name="status" style="padding:0.3rem; border-radius:var(--radius); border:1px solid var(--gray-200); font-size:0.8rem;">
                                                <option value="new" <?php echo $row['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                                <option value="read" <?php echo $row['status'] === 'read' ? 'selected' : ''; ?>>Read</option>
                                                <option value="replied" <?php echo $row['status'] === 'replied' ? 'selected' : ''; ?>>Replied</option>
                                            </select>
                                            <button type="submit"
                                                    style="color:white; background:var(--primary); padding:0.3rem 0.6rem; border:none; border-radius:var(--radius); cursor:pointer; font-size:0.8rem;">
                                                Save
                                            </button>
                                        </form>
                                        <form action="delete_message.php" method="post"
                                              onsubmit="return confirm('Delete this message?')" style="margin:0;">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                            <button type="submit"
                                                    style="color:white; background:#ef4444; padding:0.4rem 0.8rem; border:none; border-radius:var(--radius); cursor:pointer; font-size:0.85rem;">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <div style="display:flex; justify-content:center; align-items:center; gap:1rem; margin-top:1.5rem;">
                    <?php if ($page > 1): ?>
                        <a href="admin_messages.php?status=<?php echo urlencode($statusFilter); ?>&page=<?php echo $page - 1; ?>"
                           style="padding:0.5rem 1rem; border-radius:var(--radius); background:#e5e7eb; color:#374151; text-decoration:none; font-size:0.9rem;">
                            Previous
                        </a>
                    <?php endif; ?>
                    <span style="font-size:0.9rem; color:#374151;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="admin_messages.php?status=<?php echo urlencode($statusFilter); ?>&page=<?php echo $page + 1; ?>"
                           style="padding:0.5rem 1rem; border-radius:var(--radius); background:#e5e7eb; color:#374151; text-decoration:none; font-size:0.9rem;">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <span id="currentYear">2025</span> Selamawit H/Mariam Accounting Firm. All rights reserved.</p>
            </div>
        </div>
    </footer>
    <script src="script.js"></script>
</body>
</html>