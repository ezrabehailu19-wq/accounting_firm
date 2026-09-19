<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$session   = require_login();
$controler = new Controler();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard('profile.php');

    if (post_str('action') === 'details') {
        $result = $controler->updateProfile((int) $session['id'], [
            'full_name' => post_str('full_name'),
            'phone'     => post_str('phone'),
            'company'   => post_str('company'),
            'email'     => post_str('email'),
        ]);

        if ($result['ok']) {
            // The header shows the name from the session, so refresh it
            // or the page would still greet them by the old one.
            $_SESSION['full_name'] = post_str('full_name') ?: $session['username'];
        }

        flash($result['ok'] ? 'success' : 'error',
            $result['ok'] ? 'Your details are saved.' : $result['error']);
    } elseif (post_str('action') === 'password') {
        $result = $controler->changePassword(
            (int) $session['id'],
            $_POST['current'] ?? '',
            $_POST['password'] ?? '',
            $_POST['confirm'] ?? ''
        );

        flash($result['ok'] ? 'success' : 'error',
            $result['ok'] ? 'Password changed.' : $result['error']);
    }

    redirect('profile.php');
}

$user = $controler->user((int) $session['id']);

if ($user === null) {
    redirect('logout.php');
}

portal_head('Profile', 'profile', ['subtitle' => 'Your details and password']);
?>

<div class="grid-2">
    <section class="panel">
        <div class="panel__head"><div><h2>Your details</h2><p>Used on invoices and when we reply</p></div></div>
        <div class="panel__body">
            <form method="post" action="profile.php" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="details">

                <div class="form-group">
                    <label for="full_name">Full name</label>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= e($user['full_name']) ?>" autocomplete="name">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required
                           value="<?= e($user['email']) ?>" autocomplete="email">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone"
                               value="<?= e($user['phone']) ?>" autocomplete="tel">
                    </div>
                    <div class="form-group">
                        <label for="company">Business</label>
                        <input type="text" id="company" name="company"
                               value="<?= e($user['company']) ?>" autocomplete="organization">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save details</button>
            </form>
        </div>
    </section>

    <div class="stack">
        <section class="panel">
            <div class="panel__head"><div><h2>Change password</h2></div></div>
            <div class="panel__body">
                <form method="post" action="profile.php" class="stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">

                    <div class="form-group">
                        <label for="current">Current password</label>
                        <input type="password" id="current" name="current" required autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label for="password">New password</label>
                        <input type="password" id="password" name="password" required
                               minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
                        <span class="field-hint">At least <?= PASSWORD_MIN_LENGTH ?> characters.</span>
                    </div>

                    <div class="form-group">
                        <label for="confirm">Confirm new password</label>
                        <input type="password" id="confirm" name="confirm" required
                               minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-secondary">Change password</button>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head"><div><h2>Account</h2></div></div>
            <div class="panel__body">
                <dl class="dl">
                    <div class="dl__row"><dt>Username</dt><dd><?= e($user['username']) ?></dd></div>
                    <div class="dl__row">
                        <dt>Access</dt>
                        <dd><?= status_pill($user['role'], $user['role'] === 'admin' ? 'Staff' : 'Client') ?></dd>
                    </div>
                    <div class="dl__row"><dt>Member since</dt><dd><?= e(fmt_date($user['created_at'])) ?></dd></div>
                    <div class="dl__row">
                        <dt>Last signed in</dt>
                        <dd><?= e($user['last_login_at'] ? fmt_datetime($user['last_login_at']) : 'This is your first visit') ?></dd>
                    </div>
                </dl>
            </div>
        </section>
    </div>
</div>

<?php portal_foot();
