<?php
/**
 * One-time setup: create the first staff account.
 *
 * This exists so that no default password ever ships in schema.sql.
 * It refuses to run once any admin account exists, so leaving it on the
 * server by accident does not hand anyone a second way in — but delete
 * it anyway once you are done.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$controler = new Controler();
$error     = '';
$done      = false;

// The gate: exactly one chance to use this page, ever.
$adminExists = (new class extends Model {
    public function any(): bool
    {
        return (int) $this->selectValue("SELECT COUNT(*) FROM users WHERE role = 'admin'", [], 0) > 0;
    }
})->any();

if (!$adminExists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard('setup_admin.php');

    $username = post_str('username');
    $email    = post_str('email');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $fullName = post_str('full_name');

    if ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        $result = $controler->register($username, $email, $password, ['full_name' => $fullName]);

        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            // Registered as a normal user, then promoted — reusing the
            // same validated path rather than writing a second insert.
            (new class extends Model {
                public function promote(int $id): void { $this->setUserRole($id, 'admin'); }
            })->promote((int) $result['id']);

            $controler->audit(
                ['id' => $result['id'], 'username' => $username],
                'system.setup',
                'user',
                (string) $result['id'],
                'First staff account created'
            );

            $done = true;
        }
    }
}

auth_head(
    $adminExists ? 'Already set up' : 'Create your staff account',
    $adminExists ? '' : 'This runs once. Choose the credentials you will sign in with.'
);

if ($adminExists): ?>
    <div class="notice notice--info">
        A staff account already exists, so this page is closed.
        Delete <strong>setup_admin.php</strong> from the server.
    </div>
    <p class="gate__alt"><a href="login.php">Go to sign in</a></p>

<?php elseif ($done): ?>
    <div class="notice notice--success">
        Your staff account is ready.
    </div>
    <p class="gate__lede mt-1">
        Now delete <strong>setup_admin.php</strong> from the server, then sign in.
    </p>
    <a class="btn btn-primary btn-full mt-1" href="login.php">Sign in</a>

<?php else: ?>
    <?php if ($error !== ''): ?>
        <div class="notice notice--error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="setup_admin.php" class="stack">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="full_name">Your name</label>
            <input type="text" id="full_name" name="full_name" value="<?= e(post_str('full_name')) ?>"
                   placeholder="Selamawit H/Mariam" autocomplete="name">
        </div>

        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required
                   value="<?= e(post_str('username')) ?>" autocomplete="username"
                   placeholder="selamawit">
            <span class="field-hint">Letters, numbers, dot, dash, underscore.</span>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required
                   value="<?= e(post_str('email')) ?>" autocomplete="email"
                   placeholder="you@yourfirm.et">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required
                   minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
            <span class="field-hint">At least <?= PASSWORD_MIN_LENGTH ?> characters. Length beats symbols.</span>
        </div>

        <div class="form-group">
            <label for="confirm">Confirm password</label>
            <input type="password" id="confirm" name="confirm" required
                   minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary btn-full">Create account</button>
    </form>
<?php endif;

auth_foot();
