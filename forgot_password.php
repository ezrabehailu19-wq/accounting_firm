<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$controler = new Controler();
$sent      = false;
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard('forgot_password.php');

    $result = $controler->requestPasswordReset(post_str('email'));

    if ($result['ok']) {
        $sent = true;
    } else {
        $error = $result['error'];
    }
}

auth_head('Reset your password', $sent ? '' : 'Enter the email on your account and we will send a link.');

if ($sent): ?>
    <div class="notice notice--success">
        If that address has an account, a reset link is on its way.
        It works for <?= RESET_TOKEN_MINUTES ?> minutes.
    </div>
    <p class="gate__lede mt-1">
        Nothing arrived? Check spam, or try again with a different address.
    </p>
    <a class="btn btn-secondary btn-full mt-1" href="login.php">Back to sign in</a>
<?php else: ?>
    <?php if ($error !== ''): ?>
        <div class="notice notice--error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="forgot_password.php" class="stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus
                   value="<?= e(post_str('email')) ?>" autocomplete="email">
        </div>
        <button type="submit" class="btn btn-primary btn-full">Send reset link</button>
    </form>

    <p class="gate__alt"><a href="login.php">Back to sign in</a></p>
<?php endif;

auth_foot();
