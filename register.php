<?php
include 'includes/includes.inc.php';
$controler = new Controler();
$data = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $data = 'Your session expired. Please refresh the page and try again.';
    } else {
        $user = $_POST['username'];
        $pass = $_POST['password'];
        $data = $controler->signup($user, $pass);
        if ($data === 'You are Registered!') {
            header('Location: login.php?registered=true');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Selamawit H/Mariam</title>
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
                    <span class="section-badge">Admin</span>
                    <h2 class="section-title">Register</h2>
                </div>
                <div style="max-width:400px; margin:0 auto; background:white; padding:2rem; border-radius:var(--radius); box-shadow:var(--shadow-lg);">
                    <form action="" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" placeholder="Username" required>
                        </div>
                        <div class="form-group" style="margin-top:1rem;">
                            <label>Password</label>
                            <input type="password" name="password" placeholder="Password" required>
                        </div>
                        <?php if($data): ?>
                            <p style="
                                margin-top:0.5rem; 
                                padding:0.75rem 1rem;
                                border-radius:var(--radius);
                                background: <?php echo $data === 'You are Registered!' ? '#d1fae5' : '#fee2e2'; ?>;
                                color: <?php echo $data === 'You are Registered!' ? '#065f46' : '#991b1b'; ?>;
                                border: 1px solid <?php echo $data === 'You are Registered!' ? '#6ee7b7' : '#fca5a5'; ?>;
                            ">
                                <?php echo $data; ?>
                            </p>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-full" style="margin-top:1rem;">Create Account</button>
                    </form>
                    <p style="text-align:center; margin-top:1rem;">Already have an account? <a href="login.php">Login</a></p>
                </div>
            </div>
        </section>
    </main>

    <script src="script.js"></script>
</body>
</html>