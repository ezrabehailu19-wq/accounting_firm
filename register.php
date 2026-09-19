<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin.php' : 'user_dashboard.php');
}

$controler = new Controler();
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard('register.php');

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        $result = $controler->register(
            post_str('username'),
            post_str('email'),
            $password,
            [
                'full_name' => post_str('full_name'),
                'phone'     => post_str('phone'),
                'company'   => post_str('company'),
            ]
        );

        if ($result['ok']) {
            redirect('login.php?registered=1');
        }

        $error = $result['error'];
    }
}

auth_head('Create an account', 'Track your requests, share documents and view invoices in one place.');
?>

<?php if ($error !== ''): ?>
    <div class="notice notice--error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="register.php" class="stack">
    <?= csrf_field() ?>

    <div class="form-group">
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" value="<?= e(post_str('full_name')) ?>"
               autocomplete="name" placeholder="Abebe Kebede">
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required
                   value="<?= e(post_str('username')) ?>" autocomplete="username">
        </div>
        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="tel" id="phone" name="phone" value="<?= e(post_str('phone')) ?>"
                   autocomplete="tel" placeholder="+251 9xx xxx xxx">
        </div>
    </div>

    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required
               value="<?= e(post_str('email')) ?>" autocomplete="email">
        <span class="field-hint">Use the address you contacted us from and we'll link your past requests.</span>
    </div>

    <div class="form-group">
        <label for="company">Business name <span class="muted small">(optional)</span></label>
        <input type="text" id="company" name="company" value="<?= e(post_str('company')) ?>"
               autocomplete="organization">
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required
                   minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="confirm">Confirm password</label>
            <input type="password" id="confirm" name="confirm" required
                   minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-full">Create account</button>
</form>

<p class="gate__alt">Already registered? <a href="login.php">Sign in</a></p>

<?php auth_foot();
