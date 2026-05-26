<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard | Selamawit H/Mariam</title>
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
                    <li><a href="index.html" class="nav-link">Home</a></li>
                    <li><a href="services.html" class="nav-link">Services</a></li>
                    <li><a href="about.html" class="nav-link">About</a></li>
                    <li><a href="faq.html" class="nav-link">FAQ</a></li>
                    <li><a href="contact.html" class="nav-link">Contact</a></li>
                </ul>
                <a href="logout.php" class="btn btn-primary nav-cta">Logout</a>
            </nav>
        </div>
    </header>

    <main style="flex:1;">
        <section class="page-section">
            <div class="container">
                <div class="section-header">
                    <span class="section-badge">My Account</span>
                    <h2 class="section-title">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                    <p class="section-subtitle">Manage your account and services here</p>
                </div>

                <div class="services-preview-grid">
                    <div class="service-preview-card">
                        <div class="service-icon-wrapper">
                            <span class="service-icon">📋</span>
                        </div>
                        <h3>My Requests</h3>
                        <p>View your submitted contact requests and their status.</p>
                        <a href="contact.html" class="btn btn-primary" style="margin-top:1rem;">New Request</a>
                    </div>
                    <div class="service-preview-card">
                        <div class="service-icon-wrapper">
                            <span class="service-icon">📊</span>
                        </div>
                        <h3>Our Services</h3>
                        <p>Browse all available accounting and financial services.</p>
                        <a href="services.html" class="btn btn-primary" style="margin-top:1rem;">View Services</a>
                    </div>
                    <div class="service-preview-card">
                        <div class="service-icon-wrapper">
                            <span class="service-icon">📞</span>
                        </div>
                        <h3>Contact Us</h3>
                        <p>Get in touch with our team for personalized assistance.</p>
                        <a href="contact.html" class="btn btn-primary" style="margin-top:1rem;">Contact</a>
                    </div>
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