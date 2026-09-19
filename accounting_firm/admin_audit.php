<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$admin     = require_admin();
$controler = new Controler();

$action  = get_str('action');
$search  = get_str('q');
$page    = current_page();

$result  = $controler->auditEntries($page, $action, $search);
$actions = $controler->auditActions();

/** Turn 'invoice.status_change' into something a person reads. */
function audit_action_label(string $action): string
{
    $parts = explode('.', $action);
    $noun  = str_replace('_', ' ', $parts[0] ?? $action);
    $verb  = str_replace('_', ' ', $parts[1] ?? '');

    return ucfirst($noun) . ($verb !== '' ? ' — ' . $verb : '');
}

portal_head('Audit trail', 'audit', [
    'subtitle' => 'Who changed what, and when. Append-only.',
    'wide'     => true,
]);
?>

<section class="panel">
    <div class="filters">
        <form method="get" action="admin_audit.php" role="search">
            <select name="action" aria-label="Filter by action">
                <option value="">All actions</option>
                <?php foreach ($actions as $option): ?>
                    <option value="<?= e($option) ?>" <?= $action === $option ? 'selected' : '' ?>>
                        <?= e(audit_action_label($option)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="search" name="q" value="<?= e($search) ?>"
                   placeholder="Staff member or description" aria-label="Search the audit trail">
            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if ($search !== '' || $action !== ''): ?>
                <a class="btn btn-ghost btn-sm" href="admin_audit.php">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($result['rows'] === []): ?>
        <div class="panel__body">
            <?= empty_state(
                'Nothing logged yet',
                'Deleting a request, changing an invoice or promoting a user all leave a record here.'
            ) ?>
        </div>
    <?php else: ?>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Who</th>
                            <th>Action</th>
                            <th>What happened</th>
                            <th>Record</th>
                            <th>From</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <tr>
                            <td class="table__muted nowrap" title="<?= e(fmt_datetime($row['created_at'])) ?>">
                                <?= e(fmt_datetime($row['created_at'])) ?>
                            </td>
                            <td class="table__primary"><?= e($row['actor_name']) ?></td>
                            <td class="nowrap"><?= e(audit_action_label($row['action'])) ?></td>
                            <td><?= e($row['summary'] ?: '—') ?></td>
                            <td class="table__muted nowrap">
                                <?= e($row['entity_type'] ?: '—') ?><?= $row['entity_id'] ? ' #' . e($row['entity_id']) : '' ?>
                            </td>
                            <td class="table__muted num"><?= e($row['ip_address'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?= render_pagination($result['total'], $page, 'admin_audit.php') ?>
    <?php endif; ?>
</section>

<?php portal_foot();
