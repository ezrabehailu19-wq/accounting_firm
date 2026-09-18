<?php
include 'includes/includes.inc.php';
$controler = new Controler();

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$tokenValid = ($token !== '') ? $controler->validateResetToken($token) !== null : false;
$formError = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $formError = 'Your session expired. Please refresh the page and try again.';
    } elseif (!$tokenValid) {
        $formError = 'This reset link is invalid or has expired. Please request a new one.';
    } else {
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 8) {
            $formError = 'Password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $formError = 'Passwords do not match.';
        } else {
            $result = $controler->completePasswordReset($token, $newPassword);
            if ($result === 'success') {
                $success = true;
            } else {
                $formError = $result;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Selamawit H/Mariam</title>
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
                    <h2 class="section-title">Reset Password</h2>
                </div>
                <div style="max-width:400px; margin:0 auto; background:white; padding:2rem; border-radius:var(--radius); box-shadow:var(--shadow-lg);">
                    <?php if ($success): ?>
                        <p style="
                            margin-top:0.5rem;
                            padding:0.75rem 1rem;
                            border-radius:var(--radius);
                            background:#d1fae5;
                            color:#065f46;
                            border:1px solid #6ee7b7;
                        ">
                            Your password has been reset. You can now log in with your new password.
                        </p>
                        <p style="text-align:center; margin-top:1rem;"><a href="login.php">Go to login</a></p>
                    <?php elseif (!$tokenValid): ?>
                        <p style="
                            margin-top:0.5rem;
                            padding:0.75rem 1rem;
                            border-radius:var(--radius);
                            background:#fee2e2;
                            color:#991b1b;
                            border:1px solid #fca5a5;
                        ">
                            This reset link is invalid or has expired.
                        </p>
                        <p style="text-align:center; margin-top:1rem;"><a href="forgot_password.php">Request a new link</a></p>
                    <?php else: ?>
                        <form action="" method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="password" placeholder="At least 8 characters" required minlength="8">
                            </div>
                            <div class="form-group" style="margin-top:1rem;">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" placeholder="Re-enter password" required minlength="8">
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
                            <button type="submit" class="btn btn-primary btn-full" style="margin-top:1rem;">Reset Password</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
    <script src="script.js"></script>
</body>
</html>