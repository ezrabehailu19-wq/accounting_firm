<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$admin     = require_admin();
$controler = new Controler();

$statusCounts = $controler->requestStatusCounts();
$invoices     = $controler->invoiceSummary();      // also refreshes overdue
$userCount    = $controler->users(1)['total'];
$docCount     = $controler->documentCount();
$activity     = $controler->requestActivity(14);
$recent       = $controler->requests(1, 'new')['rows'];
$auditFeed    = $controler->recentActivity(7);

// Fill in the days with no messages so the chart reads as a real
// fortnight rather than a handful of bars with gaps between them.
$byDay = [];
foreach ($activity as $row) {
    $byDay[$row['day']] = (int) $row['n'];
}

$series = [];
for ($i = 13; $i >= 0; $i--) {
    $day      = date('Y-m-d', strtotime("-{$i} days"));
    $series[] = ['day' => $day, 'n' => $byDay[$day] ?? 0];
}
$peak = max(1, max(array_column($series, 'n')));

portal_head('Overview', 'dashboard', [
    'subtitle' => 'How the practice is tracking right now',
    'actions'  => '<a class="btn btn-brass btn-sm" href="admin_invoice_form.php">New invoice</a>',
]);
?>

<section class="metrics">
    <article class="metric metric--brass">
        <span class="metric__label">Unopened requests</span>
        <span class="metric__value"><?= (int) $statusCounts['new'] ?></span>
        <span class="metric__note">
            <?= (int) $statusCounts['in_progress'] ?> in progress ·
            <?= (int) $statusCounts['all'] ?> all time
        </span>
    </article>

    <article class="metric metric--debit">
        <span class="metric__label">Outstanding</span>
        <span class="metric__value metric__value--money"><?= e(money($invoices['outstanding'])) ?></span>
        <span class="metric__note">
            <strong><?= (int) $invoices['overdue_count'] ?></strong> overdue,
            <?= e(money($invoices['overdue_amount'])) ?>
        </span>
    </article>

    <article class="metric metric--credit">
        <span class="metric__label">Collected</span>
        <span class="metric__value metric__value--money"><?= e(money($invoices['billed_paid'])) ?></span>
        <span class="metric__note"><?= (int) $invoices['count_all'] ?> invoices issued</span>
    </article>

    <article class="metric">
        <span class="metric__label">Clients &amp; files</span>
        <span class="metric__value"><?= (int) $userCount ?></span>
        <span class="metric__note"><?= (int) $docCount ?> documents on file</span>
    </article>
</section>

<div class="grid-side">
    <div class="stack">
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2>Requests, last 14 days</h2>
                    <p>Each bar is one day. The tallest is highlighted.</p>
                </div>
            </div>
            <div class="panel__body">
                <?php if (array_sum(array_column($series, 'n')) === 0): ?>
                    <?= empty_state('Nothing came in this fortnight', 'New contact form submissions will appear here as they arrive.') ?>
                <?php else: ?>
                    <div class="chart" role="img"
                         aria-label="Requests per day over the last 14 days">
                        <?php foreach ($series as $point): ?>
                            <?php $height = max(3, (int) round(($point['n'] / $peak) * 100)); ?>
                            <div class="chart__col<?= $point['n'] === $peak && $peak > 0 ? ' chart__col--peak' : '' ?>"
                                 title="<?= e(fmt_date($point['day'])) ?>: <?= (int) $point['n'] ?>">
                                <span class="chart__bar" style="height: <?= $height ?>%"></span>
                                <span class="chart__tick"><?= e(date('j', strtotime($point['day']))) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2>Waiting on you</h2>
                    <p>Requests nobody has opened yet</p>
                </div>
                <a class="btn btn-secondary btn-sm" href="admin_messages.php">Open the inbox</a>
            </div>

            <?php if ($recent === []): ?>
                <div class="panel__body">
                    <?= empty_state('Inbox clear', 'Every request has been opened. New ones land here first.') ?>
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
                                    <th>Received</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach (array_slice($recent, 0, 6) as $row): ?>
                                <tr class="row--unread">
                                    <td class="num"><?= e($row['reference']) ?></td>
                                    <td>
                                        <span class="table__primary"><?= e($row['fullname']) ?></span>
                                        <span class="table__muted"><?= e($row['email']) ?></span>
                                    </td>
                                    <td><?= e($row['service'] ?: '—') ?></td>
                                    <td class="table__muted nowrap"><?= e(time_ago($row['submitted_at'])) ?></td>
                                    <td class="table__actions">
                                        <a class="btn btn-secondary btn-sm"
                                           href="admin_message.php?id=<?= (int) $row['id'] ?>">Open</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <aside class="stack">
        <section class="panel">
            <div class="panel__head"><div><h2>Recent activity</h2><p>Every change staff have made</p></div></div>
            <div class="panel__body">
                <?php if ($auditFeed === []): ?>
                    <p class="muted small">Nothing logged yet. Actions you take will show up here.</p>
                <?php else: ?>
                    <div class="feed">
                        <?php foreach ($auditFeed as $entry): ?>
                            <?php
                            $kind = 'change';
                            if (strpos($entry['action'], '.delete') !== false) {
                                $kind = 'delete';
                            } elseif (strpos($entry['action'], '.create') !== false
                                   || strpos($entry['action'], '.upload') !== false) {
                                $kind = 'create';
                            }
                            ?>
                            <div class="feed__item">
                                <span class="feed__dot feed__dot--<?= e($kind) ?>"></span>
                                <div>
                                    <p class="feed__text"><?= e($entry['summary'] ?: $entry['action']) ?></p>
                                    <p class="feed__meta">
                                        <?= e($entry['actor_name']) ?> · <?= e(time_ago($entry['created_at'])) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="panel__foot">
                <a class="btn btn-ghost btn-sm" href="admin_audit.php">See the full audit trail</a>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head"><div><h2>Quick actions</h2></div></div>
            <div class="panel__body stack-sm">
                <a class="btn btn-secondary btn-full" href="admin_invoice_form.php">Draft an invoice</a>
                <a class="btn btn-secondary btn-full" href="documents.php">Review documents</a>
                <a class="btn btn-secondary btn-full" href="admin_users.php">Manage clients</a>
            </div>
        </section>
    </aside>
</div>

<?php portal_foot();
