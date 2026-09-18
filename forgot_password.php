<?php
include 'includes/includes.inc.php';
$controler = new Controler();
$submitted = false;
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $formError = 'Your session expired. Please refresh the page and try again.';
    } else {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            $formError = 'Please enter a valid email address.';
        } else {
            $controler->requestPasswordReset($email);
            // Always show the same success message, whether or not the
            // email is registered — this prevents attackers from using
            // this form to discover which emails have accounts.
            $submitted = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Selamawit H/Mariam</title>
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
                    <span class="section-badge">Account Recovery</span>
                    <h2 class="section-title">Forgot Password</h2>
                    <p class="section-subtitle">Enter your account email and we'll send you a reset link.</p>
                </div>
                <div style="max-width:400px; margin:0 auto; background:white; padding:2rem; border-radius:var(--radius); box-shadow:var(--shadow-lg);">
                    <?php if ($submitted): ?>
                        <p style="
                            margin-top:0.5rem;
                            padding:0.75rem 1rem;
                            border-radius:var(--radius);
                            background:#d1fae5;
                            color:#065f46;
                            border:1px solid #6ee7b7;
                        ">
                            If that email is registered, a password reset link has been sent. It expires in 30 minutes.
                        </p>
                    <?php else: ?>
                        <form action="" method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" placeholder="you@example.com" required>
                            </div>
                            <?php if ($formError): ?>
                                <p style="
                                    margin-top:0.5rem;
                                    padding:0.75rem 1rem;
                                    border-radius:var(--radius);
                                    background:#fee2e2;
                                    color:#991b1b;
                                    border:1px solid #fca5a5;
                                ">
                                    <?php echo htmlspecialchars($formError); ?>
                                </p>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary btn-full" style="margin-top:1rem;">Send Reset Link</button>
                        </form>
                    <?php endif; ?>
                    <p style="text-align:center; margin-top:1rem;"><a href="login.php">Back to login</a></p>
                </div>
            </div>
        </section>
    </main>
    <script src="script.js"></script>
</body>
</html>