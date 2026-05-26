<?php
include 'includes/includes.inc.php';
$view = new View();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $error = $view->login($user, $pass);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login | Selamawit H/Mariam</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header" id="header">
        <div class="header-container">
            <a href="index.html" class="logo">
                <div class="logo-box">SH</div>
                <div class="logo-text">
                    <h1>Selamawit H/Mariam</h1>
                    <p>Accounting & Financial Consulting</p>
                </div>
            </a>
        </div>
    </header>

    <main>
        <section class="page-section">
            <div class="container">
                <div class="section-header">
                    <span class="section-badge">Welcome</span>
                    <h2 class="section-title">Login</h2>
                </div>
                <div style="max-width:400px; margin:0 auto; background:white; padding:2rem; border-radius:var(--radius); box-shadow:var(--shadow-lg);">
                    <?php if(isset($_GET['registered'])): ?>
                            <p style="
                                margin-top:0.5rem;
                                padding:0.75rem 1rem;
                                border-radius:var(--radius);
                                background:#d1fae5;
                                color:#065f46;
                                border:1px solid #6ee7b7;
                            ">
                                Account created successfully! Please login.
                            </p>
                    <?php endif; ?>

                    <form action="" method="post">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" placeholder="Username" required>
                        </div>
                        <div class="form-group" style="margin-top:1rem;">
                            <label>Password</label>
                            <input type="password" name="password" placeholder="Password" required>
                        </div>
                        <?php if($error): ?>
                            <p style="
                                margin-top:0.5rem;
                                padding:0.75rem 1rem;
                                border-radius:var(--radius);
                                background:#fee2e2;
                                color:#991b1b;
                                border:1px solid #fca5a5;
                            ">
                                <?php echo $error; ?>
                            </p>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-full" style="margin-top:1rem;">Login</button>
                    </form>
                    <p style="text-align:center; margin-top:1rem;">No account? <a href="register.php">Register</a></p>
                </div>
            </div>
        </section>
    </main>
    <script src="script.js"></script>
</body>
</html>