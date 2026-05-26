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
</html>