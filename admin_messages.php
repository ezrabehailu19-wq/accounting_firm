<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
include 'includes/includes.inc.php';
$controler = new Controler();
$messages = $controler->getMessages();
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