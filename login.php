<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

// Already signed in? Don't show a login form, just go where they belong.
if (is_logged_in()) {
    redirect(is_admin() ? 'admin.php' : 'user_dashboard.php');
}

$auth  = new Auth();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard('login.php');

    $result = $auth->attempt(post_str('username'), $_POST['password'] ?? '');

    if ($result['ok']) {
        // Send them back to whatever they were trying to reach before the
        // login wall, but only if it is a path on this site — an open
        // redirect here would let a phishing link bounce through us.
        $intended = $_SESSION['redirect_after_login'] ?? '';
        unset($_SESSION['redirect_after_login']);

        $isSafe = $intended !== ''
            && strpos($intended, '//') === false
            && strpos($intended, ':') === false;

        if ($isSafe) {
            redirect($intended);
        }

        redirect($result['user']['role'] === 'admin' ? 'admin.php' : 'user_dashboard.php');
    }

    $error = $result['error'];
}

auth_head('Sign in', 'Access your requests, documents and invoices.');
?>

<?php if (isset($_GET['registered'])): ?>
    <div class="notice notice--success">Account created. Sign in to continue.</div>
<?php endif; ?>

<?php if (isset($_GET['reset'])): ?>
    <div class="notice notice--success">Password updated. Sign in with your new password.</div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="notice notice--error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="login.php" class="stack">
    <?= csrf_field() ?>

    <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus
               value="<?= e(post_str('username')) ?>" autocomplete="username">
    </div>

    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>

    <button type="submit" class="btn btn-primary btn-full">Sign in</button>
</form>

<p class="gate__alt">
    <a href="forgot_password.php">Forgot your password?</a>
</p>
<p class="gate__alt">
    No account yet? <a href="register.php">Create one</a>
</p>

<?php auth_foot();
