<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$admin     = require_admin();
$controler = new Controler();

// Status changes post back to this page so the filters and page number
// the user was looking at survive the round trip.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard('admin_messages.php');

    $action = post_str('action');
    $id     = post_int('id');

    if ($action === 'status') {
        $result = $controler->updateRequestStatus($id, post_str('status'), $admin);
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Status updated.' : $result['error']);
    } elseif ($action === 'delete') {
        $result = $controler->removeRequest($id, $admin);
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Request deleted.' : $result['error']);
    }

    // Redirect after POST so a refresh doesn't repeat the action.
    redirect(query_with([]));
}

$status = get_str('status');
$search = get_str('q');
$page   = current_page();

$allowed = ['new', 'read', 'in_progress', 'replied', 'closed'];
if ($status !== '' && !in_array($status, $allowed, true)) {
    $status = '';
}

$result = $controler->requests($page, $status, $search);
$counts = $controler->requestStatusCounts();

portal_head('Requests', 'inbox', [
    'subtitle' => 'Everything that came in through the contact form',
    'wide'     => true,
]);
?>

<section class="panel">
    <div class="filters">
        <div class="tabs">
            <?php
            $tabs = [
                ''            => ['All', $counts['all']],
                'new'         => ['New', $counts['new']],
                'read'        => ['Read', $counts['read']],
                'in_progress' => ['In progress', $counts['in_progress']],
                'replied'     => ['Replied', $counts['replied']],
                'closed'      => ['Closed', $counts['closed']],
            ];
            foreach ($tabs as $key => [$label, $count]):
                $href = 'admin_messages.php' . ($key !== '' ? '?status=' . urlencode($key) : '');
                if ($search !== '') {
                    $href .= ($key !== '' ? '&' : '?') . 'q=' . urlencode($search);
                }
                ?>
                <a class="tab<?= $status === $key ? ' is-active' : '' ?>" href="<?= e($href) ?>">
                    <?= e($label) ?><span class="tab__count"><?= (int) $count ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="get" action="admin_messages.php" role="search">
            <?php if ($status !== ''): ?>
                <input type="hidden" name="status" value="<?= e($status) ?>">
            <?php endif; ?>
            <input type="search" name="q" value="<?= e($search) ?>"
                   placeholder="Name, email, reference or text" aria-label="Search requests">
            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
            <?php if ($search !== ''): ?>
                <a class="btn btn-ghost btn-sm" href="admin_messages.php<?= $status !== '' ? '?status=' . url_attr($status) : '' ?>">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($result['rows'] === []): ?>
        <div class="panel__body">
            <?= empty_state(
                $search !== '' ? 'No requests match that search' : 'Nothing here yet',
                $search !== ''
                    ? 'Try a shorter search term, or clear the filter to see everything.'
                    : 'Requests submitted through the website contact form land in this list.',
                $search !== '' ? '<a class="btn btn-secondary" href="admin_messages.php">Clear filters</a>' : ''
            ) ?>
        </div>
    <?php else: ?>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>From</th>
                            <th>Service</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Received</th>
                            <th class="num">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <tr<?= $row['status'] === 'new' ? ' class="row--unread"' : '' ?>>
                            <td class="num"><?= e($row['reference'] ?: '—') ?></td>
                            <td>
                                <span class="table__primary"><?= e($row['fullname']) ?></span>
                                <span class="table__muted"><?= e($row['email']) ?></span>
                            </td>
                            <td><?= e($row['service'] ?: '—') ?></td>
                            <td>
                                <span class="table__truncate table__muted"><?= e($row['message']) ?></span>
                            </td>
                            <td><?= status_pill($row['status'], request_status_label($row['status'])) ?></td>
                            <td class="table__muted nowrap" title="<?= e(fmt_datetime($row['submitted_at'])) ?>">
                                <?= e(time_ago($row['submitted_at'])) ?>
                            </td>
                            <td>
                                <div class="table__actions">
                                    <a class="btn btn-secondary btn-sm"
                                       href="admin_message.php?id=<?= (int) $row['id'] ?>">Open</a>

                                    <form method="post" action="admin_messages.php"
                                          data-confirm="Delete <?= e($row['reference'] ?: 'this request') ?>? This cannot be undone.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?= render_pagination($result['total'], $page, 'admin_messages.php') ?>
    <?php endif; ?>
</section>

<?php portal_foot();
