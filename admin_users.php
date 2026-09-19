<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
include 'includes/includes.inc.php';
$controler = new Controler();
$users = $controler->getAllUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users | Selamawit H/Mariam</title>
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
                    <li><a href="admin_messages.php" class="nav-link">Messages</a></li>
                    <li><a href="admin_users.php" class="nav-link active">Users</a></li>
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
                    <h2 class="section-title">Registered Users</h2>
                    <p class="section-subtitle">All users registered on the platform</p>
                </div>

                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; background:white; border-radius:var(--radius); box-shadow:var(--shadow-lg); overflow:hidden;">
                        <thead>
                            <tr style="background:var(--primary); color:white;">
                                <th style="padding:1rem; text-align:left;">#</th>
                                <th style="padding:1rem; text-align:left;">Username</th>
                                <th style="padding:1rem; text-align:left;">Role</th>
                                <th style="padding:1rem; text-align:left;">Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $users->fetch_assoc()): ?>
                            <tr style="border-bottom:1px solid var(--gray-200);">
                                <td style="padding:1rem;"><?php echo $row['id']; ?></td>
                                <td style="padding:1rem;"><?php echo htmlspecialchars($row['username']); ?></td>
                                <td style="padding:1rem;">
                                    <span style="
                                        padding:0.3rem 0.8rem;
                                        border-radius:999px;
                                        font-size:0.8rem;
                                        background: <?php echo $row['role'] === 'admin' ? '#dbeafe' : '#d1fae5'; ?>;
                                        color: <?php echo $row['role'] === 'admin' ? '#1e3a8a' : '#065f46'; ?>;
                                    ">
                                        <?php echo $row['role']; ?>
                                    </span>
                                </td>
                                <td style="padding:1rem;"><?php echo $row['created_at']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
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
</html><?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
include 'includes/includes.inc.php';
$controler = new Controler();
$messages = $controler->getMessages();
$total = $messages->num_rows;
$messages = $controler->getMessages();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Selamawit H/Mariam</title>
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
                    <li><a href="admin.php" class="nav-link active">Dashboard</a></li>
                    <li><a href="admin_messages.php" class="nav-link">Messages</a></li>
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
                    <h2 class="section-title">Welcome, Selamawit!</h2>
                    <p class="section-subtitle">Manage your website from here</p>
                </div>

                <div class="stats-grid" style="margin-bottom:3rem;">
                    <div class="stat-item">
                        <div class="stat-icon">✉️</div>
                        <span class="stat-number"><?php echo $total; ?></span>
                        <p class="stat-label">Total Messages</p>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">🆕</div>
                        <span class="stat-number"><?php echo $controler->getNewMessagesCount(); ?></span>
                        <p class="stat-label">New Messages</p>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">👥</div>
                        <span class="stat-number"><?php echo $controler->countUsers(); ?></span>
                        <p class="stat-label">Registered Users</p>
                    </div>
                </div>

                <div class="section-header">
                    <span class="section-badge">Inbox</span>
                    <h2 class="section-title">Recent Messages</h2>
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
                            <?php while ($row = $messages->fetch_assoc()): ?>
                            <tr style="border-bottom:1px solid var(--gray-200);">
                                <td style="padding:1rem;"><?php echo $row['id']; ?></td>
                                <td style="padding:1rem;"><?php echo htmlspecialchars($row['fullname']); ?></td>
                                <td style="padding:1rem;"><?php echo htmlspecialchars($row['email']); ?></td>
                                <td style="padding:1rem;"><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td style="padding:1rem;"><?php echo htmlspecialchars($row['service']); ?></td>
                                <td style="padding:1rem;"><?php echo htmlspecialchars($row['message']); ?></td>
                                <td style="padding:1rem;"><?php echo $row['submitted_at']; ?></td>
                                <td style="padding:1rem;">
                                    <?php
                                        $dashColors = [
                                            'new'     => ['bg' => '#dbeafe', 'text' => '#1e40af'],
                                            'read'    => ['bg' => '#fef3c7', 'text' => '#92400e'],
                                            'replied' => ['bg' => '#d1fae5', 'text' => '#065f46'],
                                        ];
                                        $c = $dashColors[$row['status']] ?? $dashColors['new'];
                                    ?>
                                    <span style="background:<?php echo $c['bg']; ?>; color:<?php echo $c['text']; ?>; padding:0.25rem 0.6rem; border-radius:999px; font-size:0.75rem; font-weight:600; white-space:nowrap;">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td style="padding:1rem;">
                                    <form action="delete_message.php" method="post"
                                          onsubmit="return confirm('Delete this message?')" style="margin:0;">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                        <button type="submit"
                                                style="color:white; background:#ef4444; padding:0.4rem 0.8rem; border:none; border-radius:var(--radius); cursor:pointer; font-size:0.85rem;">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
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