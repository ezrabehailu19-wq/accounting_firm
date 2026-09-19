<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$controler = new Controler();

// The token travels in the query string on first load and in a hidden
// field on submit, so a failed attempt doesn't lose it.
$token = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? post_str('token')
    : get_str('token');

$tokenValid = $controler->resetTokenIsValid($token);
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard('reset_password.php');

    $result = $controler->completePasswordReset(
        $token,
        $_POST['password'] ?? '',
        $_POST['confirm'] ?? ''
    );

    if ($result['ok']) {
        redirect('login.php?reset=1');
    }

    $error      = $result['error'];
    $tokenValid = $controler->resetTokenIsValid($token);
}

auth_head('Choose a new password');

if (!$tokenValid): ?>
    <div class="notice notice--error">
        This link has expired or has already been used.
    </div>
    <p class="gate__lede mt-1">Reset links last <?= RESET_TOKEN_MINUTES ?> minutes and work once.</p>
    <a class="btn btn-primary btn-full mt-1" href="forgot_password.php">Request a new link</a>
<?php else: ?>
    <?php if ($error !== ''): ?>
        <div class="notice notice--error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="reset_password.php" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="form-group">
            <label for="password">New password</label>
            <input type="password" id="password" name="password" required autofocus
                   minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
            <span class="field-hint">At least <?= PASSWORD_MIN_LENGTH ?> characters.</span>
        </div>

        <div class="form-group">
            <label for="confirm">Confirm new password</label>
            <input type="password" id="confirm" name="confirm" required
                   minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary btn-full">Update password</button>
    </form>
<?php endif;

auth_foot();
