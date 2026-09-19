<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$admin     = require_admin();
$controler = new Controler();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard('admin_users.php');

    $action = post_str('action');
    $id     = post_int('id');

    if ($action === 'role') {
        $result = $controler->changeUserRole($id, post_str('role'), $admin);
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Role updated.' : $result['error']);
    } elseif ($action === 'status') {
        $result = $controler->changeUserStatus($id, post_str('status'), $admin);
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Account updated.' : $result['error']);
    }

    redirect(query_with([]));
}

$search = get_str('q');
$role   = get_str('role');
$page   = current_page();

if ($role !== '' && !in_array($role, ['user', 'admin'], true)) {
    $role = '';
}

$result = $controler->users($page, $search, $role);

portal_head('Clients', 'clients', [
    'subtitle' => 'Everyone with an account on the portal',
    'wide'     => true,
]);
?>

<section class="panel">
    <div class="filters">
        <div class="tabs">
            <a class="tab<?= $role === '' ? ' is-active' : '' ?>" href="admin_users.php">Everyone</a>
            <a class="tab<?= $role === 'user' ? ' is-active' : '' ?>" href="admin_users.php?role=user">Clients</a>
            <a class="tab<?= $role === 'admin' ? ' is-active' : '' ?>" href="admin_users.php?role=admin">Staff</a>
        </div>

        <form method="get" action="admin_users.php" role="search">
            <?php if ($role !== ''): ?>
                <input type="hidden" name="role" value="<?= e($role) ?>">
            <?php endif; ?>
            <input type="search" name="q" value="<?= e($search) ?>"
                   placeholder="Name, username, email or business" aria-label="Search clients">
            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
            <?php if ($search !== ''): ?>
                <a class="btn btn-ghost btn-sm" href="admin_users.php">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($result['rows'] === []): ?>
        <div class="panel__body">
            <?= empty_state(
                'No accounts match',
                'Try a different search, or clear the filter to see everyone.',
                '<a class="btn btn-secondary" href="admin_users.php">Clear filters</a>'
            ) ?>
        </div>
    <?php else: ?>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Business</th>
                            <th>Contact</th>
                            <th>Access</th>
                            <th>Last seen</th>
                            <th>Joined</th>
                            <th class="num">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <tr>
                            <td>
                                <span class="table__primary"><?= e($row['full_name'] ?: $row['username']) ?></span>
                                <span class="table__muted">@<?= e($row['username']) ?></span>
                            </td>
                            <td><?= e($row['company'] ?: '—') ?></td>
                            <td>
                                <span class="table__muted"><?= e($row['email']) ?></span>
                                <?php if ($row['phone']): ?>
                                    <span class="table__muted"><?= e($row['phone']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="nowrap">
                                <?= status_pill($row['role'], $row['role'] === 'admin' ? 'Staff' : 'Client') ?>
                                <?php if ($row['status'] === 'suspended'): ?>
                                    <?= status_pill('suspended', 'Suspended') ?>
                                <?php endif; ?>
                            </td>
                            <td class="table__muted nowrap"><?= e($row['last_login_at'] ? time_ago($row['last_login_at']) : 'Never') ?></td>
                            <td class="table__muted nowrap"><?= e(fmt_date($row['created_at'])) ?></td>
                            <td>
                                <div class="table__actions">
                                    <a class="btn btn-ghost btn-sm"
                                       href="admin_invoices.php?client=<?= (int) $row['id'] ?>">Invoices</a>
                                    <a class="btn btn-ghost btn-sm"
                                       href="documents.php?owner=<?= (int) $row['id'] ?>">Files</a>

                                    <?php if ((int) $row['id'] !== (int) $admin['id']): ?>
                                        <form method="post" action="admin_users.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="role">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="role" value="<?= $row['role'] === 'admin' ? 'user' : 'admin' ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm">
                                                <?= $row['role'] === 'admin' ? 'Make client' : 'Make staff' ?>
                                            </button>
                                        </form>

                                        <form method="post" action="admin_users.php"
                                              data-confirm="<?= $row['status'] === 'suspended' ? 'Reactivate' : 'Suspend' ?> @<?= e($row['username']) ?>?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="status" value="<?= $row['status'] === 'suspended' ? 'active' : 'suspended' ?>">
                                            <button type="submit" class="btn <?= $row['status'] === 'suspended' ? 'btn-success' : 'btn-danger' ?> btn-sm">
                                                <?= $row['status'] === 'suspended' ? 'Reactivate' : 'Suspend' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="table__muted small">That's you</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?= render_pagination($result['total'], $page, 'admin_users.php') ?>
    <?php endif; ?>
</section>

<?php portal_foot();
